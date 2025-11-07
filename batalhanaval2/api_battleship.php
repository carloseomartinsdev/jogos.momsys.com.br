<?php
// Batalha Naval Online (2–4 jogadores) — PHP 7+
// Estado salvo em rooms/{CODE}.json
header('Content-Type: application/json; charset=utf-8');

$DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'rooms';
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0777, true); }

try{
  $a = isset($_POST['action']) ? $_POST['action'] : '';
  switch($a){
    case 'create': {
      $size = isset($_POST['size']) ? $_POST['size'] : '10x10';
      $maxp = isset($_POST['maxp']) ? intval($_POST['maxp']) : 2;
      if($maxp<2||$maxp>4) $maxp=2;
      respond(action_create($size,$maxp));
    }
    case 'join': {
      respond(action_join(up($_POST,'room')));
    }
    case 'poll': {
      respond(action_poll(up($_POST,'room'), gp('token'), gint('version'), gint('last_chat_id')));
    }
    case 'place_random': {
      respond(action_place_random(up($_POST,'room'), gp('token')));
    }
    case 'ready': {
      respond(action_ready(up($_POST,'room'), gp('token')));
    }
    case 'shoot': {
      respond(action_shoot(up($_POST,'room'), gp('token'), gint('r'), gint('c'), up($_POST,'target')));
    }
    case 'restart': {
      respond(action_restart(up($_POST,'room'), gp('token')));
    }
    case 'chat_send': {
      respond(action_chat_send(up($_POST,'room'), gp('token'), trim((string)($_POST['text']??''))));
    }
    default: throw new Exception('ação inválida');
  }
}catch(Exception $e){
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit;
}

/* ===== Helpers ===== */
function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function up($arr,$k){ return strtoupper(safe($arr,$k)); }
function gp($k){ return isset($_POST[$k])?$_POST[$k]:''; }
function gint($k){ return intval(isset($_POST[$k])?$_POST[$k]:0); }
function safe($arr,$k){ return preg_replace('/[^A-Za-z0-9 _@.,;:!?+-]/u','', isset($arr[$k])?$arr[$k]:''); }
function codefile($room){ global $DATA_DIR; return $DATA_DIR.DIRECTORY_SEPARATOR.$room.'.json'; }
function randCode($n=6){ $c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s=''; for($i=0;$i<$n;$i++){$s.=$c[random_int(0,strlen($c)-1)];} return $s; }
function randToken(){ return bin2hex(random_bytes(16)); }

function load_state($room){
  $f = codefile($room);
  if(!file_exists($f)) return null;
  $fp=fopen($f,'r'); if(!$fp) return null;
  flock($fp,LOCK_SH); $txt=stream_get_contents($fp); flock($fp,LOCK_UN); fclose($fp);
  return json_decode($txt,true);
}
function save_state($room,$st){
  $f=codefile($room); $fp=fopen($f,'c+'); if(!$fp) throw new Exception('Falha ao abrir arquivo');
  flock($fp,LOCK_EX); ftruncate($fp,0); fwrite($fp,json_encode($st,JSON_UNESCAPED_UNICODE)); fflush($fp);
  flock($fp,LOCK_UN); fclose($fp);
}

/* ===== Regras ===== */
function fleet(){ return [5,4,3,3,2]; } // tamanhos padrão

function new_state($rows,$cols,$maxp){
  $letters = ['A','B','C','D'];
  $boards = [];
  $ready = []; $remaining = []; $players=[];
  foreach($letters as $L){ $boards[$L]=array_fill(0,$rows,array_fill(0,$cols,'0')); $ready[$L]=false; $remaining[$L]=0; $players[$L]=null; }
  return [
    'rows'=>$rows,'cols'=>$cols,'max_players'=>$maxp,
    'players'=>$players,          // tokens por jogador
    'boards'=>$boards,            // tabuleiros
    'ready'=>$ready,
    'remaining'=>$remaining,      // células de navio restantes por jogador
    'alive'=>['A'=>false,'B'=>false,'C'=>($maxp>=3?false:false),'D'=>($maxp>=4?false:false)], // será true quando tiver token e >0 navios
    'alive_order'=>array_slice($letters,0,$maxp),
    'turn'=>'A',
    'both_ready'=>false,          // (na verdade: todos prontos)
    'finished'=>false,
    'winner'=>null,
    'version'=>1,
    'updated_at'=>time(),
    // chat
    'chat'=>[],                   // [{id, who, text, ts}]
    'chat_last_id'=>0
  ];
}

function place_random_board($rows,$cols){
  $board = array_fill(0,$rows,array_fill(0,$cols,'0'));
  foreach(fleet() as $len){
    $placed=false; $tries=0;
    while(!$placed && $tries<300){
      $tries++;
      $dir = random_int(0,1)==0 ? 'H' : 'V';
      if($dir==='H'){
        $r = random_int(0,$rows-1);
        $c = random_int(0,$cols-$len);
        $ok=true;
        for($i=0;$i<$len;$i++){ if($board[$r][$c+$i]!=='0'){ $ok=false; break; } }
        if($ok){ for($i=0;$i<$len;$i++){ $board[$r][$c+$i]='S'; } $placed=true; }
      }else{
        $r = random_int(0,$rows-$len);
        $c = random_int(0,$cols-1);
        $ok=true;
        for($i=0;$i<$len;$i++){ if($board[$r+$i][$c]!=='0'){ $ok=false; break; } }
        if($ok){ for($i=0;$i<$len;$i++){ $board[$r+$i][$c]='S'; } $placed=true; }
      }
    }
    if(!$placed) return null;
  }
  return $board;
}

function count_ships($board){
  $n=0; foreach($board as $row){ foreach($row as $v){ if($v==='S') $n++; } } return $n;
}

function everyone_ready($st){
  foreach($st['alive_order'] as $L){
    if(empty($st['players'][$L])) return false; // ainda não entrou
    if(!$st['ready'][$L]) return false;
  }
  return true;
}

function next_present_player($st, $cur){
  $order = $st['alive_order'];
  $present = [];
  foreach($order as $L){
    if(!empty($st['players'][$L]) && $st['alive'][$L]) $present[]=$L;
  }
  if(!$present) return $cur;
  $idx = array_search($cur,$present,true);
  if($idx===false) return $present[0];
  $idx = ($idx+1) % count($present);
  return $present[$idx];
}

function alive_recalc(&$st){
  foreach($st['alive_order'] as $L){
    $st['alive'][$L] = !empty($st['players'][$L]) && $st['remaining'][$L] > 0;
  }
}

function winner_if_any($st){
  $aliveCount=0; $last=null;
  foreach($st['alive_order'] as $L){
    if($st['alive'][$L]){ $aliveCount++; $last=$L; }
  }
  if($aliveCount===1) return $last;
  return null;
}

function mask_for_view($st,$me){
  $letters = $st['alive_order'];
  // own completo
  $own = $st['boards'][$me];
  // máscaras por alvo
  $oppMasks = [];
  foreach($letters as $L){
    if($L===$me) continue;
    $mask = array_fill(0,$st['rows'], array_fill(0,$st['cols'],'?'));
    $b = $st['boards'][$L];
    for($r=0;$r<$st['rows'];$r++){
      for($c=0;$c<$st['cols'];$c++){
        if($b[$r][$c]==='H') $mask[$r][$c]='H';
        elseif($b[$r][$c]==='M') $mask[$r][$c]='M';
        else $mask[$r][$c]='?';
      }
    }
    $oppMasks[$L]=$mask;
  }

  // vivos map simples para HUD
  $aliveMap = [];
  foreach($letters as $L){ $aliveMap[$L] = $st['alive'][$L]; }

  return [
    'success'=>true,
    'state'=>[
      'rows'=>$st['rows'], 'cols'=>$st['cols'],
      'version'=>$st['version'],
      'turn'=>$st['turn'],
      'both_ready'=>everyone_ready($st),
      'finished'=>$st['finished'],
      'winner'=>$st['winner'],
      'alive'=>$aliveMap,
      'alive_order'=>$letters,
      'you_ready'=>$st['ready'][$me],
      'own'=>$own,
      'opp_masks'=>$oppMasks
    ]
  ];
}

/* ===== Ações ===== */
function action_create($size,$maxp){
  $p = explode('x', strtolower($size)); $rows=intval($p[0]); $cols=intval($p[1]);
  if($rows<6||$rows>14||$cols<6||$cols>14){ $rows=10; $cols=10; }

  $tries=0; do{ $room=randCode(6); $tries++; } while(file_exists(codefile($room)) && $tries<10);

  $st = new_state($rows,$cols,$maxp);
  $tok = randToken(); $st['players']['A']=$tok;
  $st['version']++; $st['updated_at']=time();

  save_state($room,$st);

  $view = mask_for_view($st,'A');
  $view['room']=$room; $view['token']=$tok; $view['you_side']='A';
  $view['last_chat_id']=$st['chat_last_id'];
  return $view;
}

function action_join($room){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];

  $letters = $st['alive_order'];
  foreach($letters as $L){
    if(empty($st['players'][$L])){
      $tok=randToken(); $st['players'][$L]=$tok; $st['version']++; $st['updated_at']=time();
      save_state($room,$st);
      $view = mask_for_view($st,$L);
      $view['room']=$room; $view['token']=$tok; $view['you_side']=$L;
      $view['last_chat_id']=$st['chat_last_id'];
      return $view;
    }
  }
  return ['success'=>false,'error'=>'Sala cheia'];
}

function action_poll($room,$token,$clientV,$lastChatId){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];

  $out = ['success'=>true, 'update'=>false];

  if($st['version'] > $clientV){
    $view = mask_for_view($st,$me);
    $out['update']=true; $out['state']=$view['state'];
  }

  // chat desde lastChatId
  if($st['chat_last_id'] > $lastChatId){
    $msgs = [];
    foreach($st['chat'] as $m){ if($m['id']>$lastChatId) $msgs[]=$m; }
    $out['chat'] = $msgs;
  }else{
    $out['chat'] = [];
  }

  return $out;
}

function action_place_random($room,$token){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if($st['ready'][$me]) return ['success'=>false,'error'=>'Você já está pronto'];

  $b = place_random_board($st['rows'],$st['cols']);
  if(!$b) return ['success'=>false,'error'=>'Falha ao posicionar aleatório'];

  $st['boards'][$me] = $b;
  $st['remaining'][$me] = count_ships($b);
  $st['alive'][$me] = $st['remaining'][$me] > 0;
  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return mask_for_view($st,$me);
}

function action_ready($room,$token){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if($st['remaining'][$me]<=0) return ['success'=>false,'error'=>'Posicione seus navios primeiro (Aleatorizar navios)'];

  $st['ready'][$me]=true;
  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return mask_for_view($st,$me);
}

function action_shoot($room,$token,$r,$c,$target){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if(!in_array($target, $st['alive_order'])) return ['success'=>false,'error'=>'Alvo inválido'];
  if($target===$me) return ['success'=>false,'error'=>'Não pode atirar em si mesmo'];
  if(!everyone_ready($st)) return ['success'=>false,'error'=>'Aguardando todos prontos'];
  if($st['finished']) return ['success'=>false,'error'=>'Jogo finalizado'];
  if($st['turn']!==$me) return ['success'=>false,'error'=>'Não é sua vez'];
  if(!$st['alive'][$target]) return ['success'=>false,'error'=>'Alvo já eliminado'];

  $rows=$st['rows']; $cols=$st['cols'];
  if($r<0||$r>=$rows||$c<0||$c>=$cols) return ['success'=>false,'error'=>'Fora do tabuleiro'];

  $cell = $st['boards'][$target][$r][$c];
  if($cell==='H' || $cell==='M') return ['success'=>false,'error'=>'Já atirado aqui'];

  $hit=false;
  if($cell==='S'){
    $st['boards'][$target][$r][$c]='H';
    $st['remaining'][$target]--;
    if($st['remaining'][$target]<=0){
      $st['alive'][$target]=false;
    }
    $hit=true;
  }else{
    $st['boards'][$target][$r][$c]='M';
  }

  // checa vencedor
  alive_recalc($st);
  $win = winner_if_any($st);
  if($win!==null){ $st['finished']=true; $st['winner']=$win; }

  if(!$st['finished'] && !$hit){
    $st['turn'] = next_present_player($st, $st['turn']);
  }

  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return mask_for_view($st,$me);
}

function action_restart($room,$token){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];

  $rows=$st['rows']; $cols=$st['cols']; $maxp=$st['max_players'];
  $players=$st['players'];

  $st = new_state($rows,$cols,$maxp);
  $st['players']=$players; // mantém tokens conectados
  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  $view = mask_for_view($st,$me);
  $view['last_chat_id']=$st['chat_last_id'];
  return $view;
}

/* ===== Chat ===== */
function action_chat_send($room,$token,$text){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if($text==='') return ['success'=>false,'error'=>'Mensagem vazia'];

  $id = $st['chat_last_id'] + 1;
  $msg = ['id'=>$id, 'who'=>$me, 'text'=>$text, 'ts'=>time()];
  $st['chat'][] = $msg;
  // limita a 200 mensagens
  if(count($st['chat'])>200){ $st['chat'] = array_slice($st['chat'], -200); }
  $st['chat_last_id'] = $id;
  $st['version']++; // opcional: pode ou não versionar com chat; aqui versiona
  $st['updated_at']=time();
  save_state($room,$st);

  return ['success'=>true, 'chat'=>[$msg], 'last_chat_id'=>$id];
}
