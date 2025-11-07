<?php
// Batalha Naval — Mar Global (20×20 com ilhas) — PHP 7+
header('Content-Type: application/json; charset=utf-8');

$DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'rooms';
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0777, true); }

try{
  $a = isset($_POST['action']) ? $_POST['action'] : '';
  switch($a){
    case 'create': { $maxp = isset($_POST['maxp']) ? intval($_POST['maxp']) : 2; if($maxp<2||$maxp>4) $maxp=2; respond(action_create($maxp)); }
    case 'join':   { respond(action_join(up($_POST,'room'))); }
    case 'poll':   { respond(action_poll(up($_POST,'room'), gp('token'), gint('version'), gint('last_chat_id'))); }
    case 'place_random': { respond(action_place_random(up($_POST,'room'), gp('token'))); }
    case 'ready':  { respond(action_ready(up($_POST,'room'), gp('token'))); }
    case 'shoot':  { respond(action_shoot(up($_POST,'room'), gp('token'), gint('r'), gint('c'))); } // GLOBAL
    case 'restart':{ respond(action_restart(up($_POST,'room'), gp('token'))); }
    case 'chat_send': { respond(action_chat_send(up($_POST,'room'), gp('token'), trim((string)($_POST['text']??'')))); }
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

/* ===== Regras / Terreno ===== */
function fleet(){ return [5,4,3,3,2]; } // por jogador

function generate_terrain($rows,$cols){
  // 20x20 fixo; ~15% de ilhas com pequena aleatoriedade
  $pct = 0.15 + (random_int(-5,5)/100.0); // 10%–20%
  $cells = $rows*$cols; $land = (int)round($cells*$pct);
  $t = array_fill(0,$rows, array_fill(0,$cols,'W'));
  $placed=0; $tries=0;
  while($placed < $land && $tries < $cells*10){
    $tries++;
    $r = random_int(0,$rows-1); $c = random_int(0,$cols-1);
    if($t[$r][$c]==='X') continue;
    // desestimula clusters grandes
    $adj=0;
    if($r>0 && $t[$r-1][$c]==='X') $adj++;
    if($r<$rows-1 && $t[$r+1][$c]==='X') $adj++;
    if($c>0 && $t[$r][$c-1]==='X') $adj++;
    if($c<$cols-1 && $t[$r][$c+1]==='X') $adj++;
    if($adj>=3 && random_int(0,100)<75) continue;
    $t[$r][$c]='X'; $placed++;
  }
  return $t;
}

function new_state(){
  $rows=20; $cols=20;
  $letters=['A','B','C','D'];
  $terrain = generate_terrain($rows,$cols);

  $boards=[]; $ready=[]; $remaining=[]; $players=[]; $alive=[];
  foreach($letters as $L){
    $boards[$L]=array_fill(0,$rows,array_fill(0,$cols,'0'));
    $ready[$L]=false; $remaining[$L]=0; $players[$L]=null; $alive[$L]=false;
  }

  return [
    'rows'=>$rows,'cols'=>$cols,'max_players'=>4,
    'terrain'=>$terrain,

    'players'=>$players, 'boards'=>$boards, 'ready'=>$ready,
    'remaining'=>$remaining, 'alive'=>$alive, 'alive_order'=>$letters,

    'turn'=>'A', 'both_ready'=>false, 'finished'=>false, 'winner'=>null,
    'version'=>1, 'updated_at'=>time(),

    'chat'=>[], 'chat_last_id'=>0
  ];
}

/* ===== Máscaras (inclui GLOBAL) ===== */
function build_masks_for($st,$me){
  $rows=$st['rows']; $cols=$st['cols']; $letters=$st['alive_order'];

  // own
  $own=$st['boards'][$me];

  // masks per-opponent
  $oppMasks=[];
  foreach($letters as $L){
    if($L===$me) continue;
    $mask = array_fill(0,$rows, array_fill(0,$cols,'?'));
    $b = $st['boards'][$L];
    for($r=0;$r<$rows;$r++){
      for($c=0;$c<$cols;$c++){
        if($st['terrain'][$r][$c]==='X'){ $mask[$r][$c]='X'; continue; }
        if($b[$r][$c]==='H') $mask[$r][$c]='H';
        elseif($b[$r][$c]==='M') $mask[$r][$c]='M';
        else $mask[$r][$c]='?';
      }
    }
    $oppMasks[$L]=$mask;
  }

  // global mask
  $glob = array_fill(0,$rows, array_fill(0,$cols,'?'));
  for($r=0;$r<$rows;$r++){
    for($c=0;$c<$cols;$c++){
      if($st['terrain'][$r][$c]==='X'){ $glob[$r][$c]='X'; continue; }
      $anyH=false; $allM=true; $anyUnknown=false;
      foreach($letters as $L){
        if($L===$me) continue;
        $v=$st['boards'][$L][$r][$c];
        if($v==='H'){ $anyH=true; $allM=false; }
        elseif($v==='M'){ /* mantém allM se ninguém H */ }
        else { $anyUnknown=true; $allM=false; }
      }
      if($anyH) $glob[$r][$c]='H';
      else if(!$anyUnknown && $allM) $glob[$r][$c]='M';
      else $glob[$r][$c]='?';
    }
  }

  // vivos
  $aliveMap=[]; foreach($letters as $L){ $aliveMap[$L]=$st['alive'][$L]; }

  return [
    'success'=>true,
    'state'=>[
      'rows'=>$rows, 'cols'=>$cols, 'version'=>$st['version'],
      'turn'=>$st['turn'], 'both_ready'=>everyone_ready($st),
      'finished'=>$st['finished'], 'winner'=>$st['winner'],
      'alive'=>$aliveMap, 'alive_order'=>$letters,
      'you_ready'=>$st['ready'][$me],
      'own'=>$own, 'opp_masks'=>$oppMasks,
      'global_mask'=>$glob,
      'terrain'=>$st['terrain']
    ]
  ];
}

function everyone_ready($st){
  foreach($st['alive_order'] as $L){
    if(empty($st['players'][$L])) return false;
    if(!$st['ready'][$L]) return false;
  }
  return true;
}
function alive_recalc(&$st){
  foreach($st['alive_order'] as $L){
    $st['alive'][$L] = !empty($st['players'][$L]) && $st['remaining'][$L] > 0;
  }
}
function winner_if_any($st){
  $aliveCount=0; $last=null;
  foreach($st['alive_order'] as $L){ if($st['alive'][$L]){ $aliveCount++; $last=$L; } }
  return $aliveCount===1 ? $last : null;
}

/* ===== Ações ===== */
function action_create($maxp){
  $st = new_state();
  // limita quantidade real de jogadores (A..)
  if($maxp<2) $maxp=2; if($maxp>4) $maxp=4;
  $st['alive_order']=array_slice($st['alive_order'],0,$maxp);

  $tok = randToken(); $st['players']['A']=$tok;
  $st['version']++; $st['updated_at']=time();
  save_state(new_room_file($code = new_code($st)), $st); // escreve o arquivo

  $view = build_masks_for($st,'A');
  $view['room']=$code; $view['token']=$tok; $view['you_side']='A';
  $view['last_chat_id']=$st['chat_last_id'];
  return $view;
}

function new_code($st){
  $tries=0; do{ $room=randCode(6); $tries++; } while(file_exists(codefile($room)) && $tries<20);
  return $room;
}
function new_room_file($room){ return $room; }

function action_join($room){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];

  foreach($st['alive_order'] as $L){
    if(empty($st['players'][$L])){
      $tok=randToken(); $st['players'][$L]=$tok; $st['version']++; $st['updated_at']=time();
      save_state($room,$st);
      $view = build_masks_for($st,$L);
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
    $view = build_masks_for($st,$me);
    $out['update']=true; $out['state']=$view['state'];
  }

  if($st['chat_last_id'] > $lastChatId){
    $msgs = [];
    foreach($st['chat'] as $m){ if($m['id']>$lastChatId) $msgs[]=$m; }
    $out['chat'] = $msgs;
  } else { $out['chat']=[]; }

  return $out;
}

function action_place_random($room,$token){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if($st['ready'][$me]) return ['success'=>false,'error'=>'Você já está pronto'];

  $fleet = fleet();
  $b = place_random_board($st['rows'],$st['cols'],$st['terrain'],$fleet);
  if(!$b) return ['success'=>false,'error'=>'Falha ao posicionar aleatório (terreno denso)'];

  $st['boards'][$me] = $b;
  $st['remaining'][$me] = count_ships($b);
  $st['alive'][$me] = $st['remaining'][$me] > 0;
  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return build_masks_for($st,$me);
}

function place_random_board($rows,$cols,$terrain,$fleetSizes){
  $board = array_fill(0,$rows,array_fill(0,$cols,'0'));
  foreach($fleetSizes as $len){
    $placed=false; $tries=0;
    while(!$placed && $tries<800){
      $tries++;
      $dir = random_int(0,1)==0 ? 'H' : 'V';
      if($dir==='H'){
        $r = random_int(0,$rows-1); $c = random_int(0,$cols-$len);
        $ok=true;
        for($i=0;$i<$len;$i++){
          if($terrain[$r][$c+$i]==='X' || $board[$r][$c+$i]!=='0'){ $ok=false; break; }
        }
        if($ok){ for($i=0;$i<$len;$i++){ $board[$r][$c+$i]='S'; } $placed=true; }
      }else{
        $r = random_int(0,$rows-$len); $c = random_int(0,$cols-1);
        $ok=true;
        for($i=0;$i<$len;$i++){
          if($terrain[$r+$i][$c]==='X' || $board[$r+$i][$c]!=='0'){ $ok=false; break; }
        }
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

function action_ready($room,$token){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if($st['remaining'][$me]<=0) return ['success'=>false,'error'=>'Posicione seus navios primeiro (Aleatorizar navios)'];

  $st['ready'][$me]=true;
  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return build_masks_for($st,$me);
}

function action_shoot($room,$token,$r,$c){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if(!everyone_ready($st)) return ['success'=>false,'error'=>'Aguardando todos prontos'];
  if($st['finished']) return ['success'=>false,'error'=>'Jogo finalizado'];
  if($st['turn']!==$me) return ['success'=>false,'error'=>'Não é sua vez'];

  $rows=$st['rows']; $cols=$st['cols'];
  if($r<0||$r>=$rows||$c<0||$c>=$cols) return ['success'=>false,'error'=>'Fora do tabuleiro'];
  if($st['terrain'][$r][$c]==='X') return ['success'=>false,'error'=>'Não é possível atirar em terra'];

  // verifica se ainda há pelo menos um oponente com célula não resolvida (0/S)
  $hasTarget=false;
  foreach($st['alive_order'] as $L){
    if($L===$me || !$st['alive'][$L]) continue;
    $v=$st['boards'][$L][$r][$c];
    if($v==='0' || $v==='S'){ $hasTarget=true; break; }
  }
  if(!$hasTarget) return ['success'=>false,'error'=>'Já atirado aqui para todos os oponentes'];

  $anyHit=false;
  foreach($st['alive_order'] as $L){
    if($L===$me || !$st['alive'][$L]) continue;
    $v=$st['boards'][$L][$r][$c];
    if($v==='S'){ $st['boards'][$L][$r][$c]='H'; $st['remaining'][$L]--; $anyHit=true; }
    elseif($v==='0'){ $st['boards'][$L][$r][$c]='M'; }
    // se já H/M, ignora
    if($st['remaining'][$L]<=0){ $st['alive'][$L]=false; }
  }

  // vencedor?
  alive_recalc($st); $win=winner_if_any($st);
  if($win!==null){ $st['finished']=true; $st['winner']=$win; }

  if(!$st['finished'] && !$anyHit){
    // passa para o próximo vivo
    $st['turn'] = next_turn($st, $st['turn']);
  }

  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return build_masks_for($st,$me);
}

function next_turn($st, $cur){
  $order=$st['alive_order']; $present=[];
  foreach($order as $L){ if(!empty($st['players'][$L]) && $st['alive'][$L]) $present[]=$L; }
  if(!$present) return $cur;
  $idx=array_search($cur,$present,true); if($idx===false) return $present[0];
  $idx=($idx+1)%count($present); return $present[$idx];
}

function action_restart($room,$token){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = null; foreach($st['players'] as $L=>$t){ if($token===($t?:'')){ $me=$L; break; } }
  if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];

  $players=$st['players']; $maxp=count(array_filter($st['alive_order'],fn($x)=>true));
  $st = new_state(); // novo terreno também
  $st['alive_order']=array_slice($st['alive_order'],0,$maxp);
  $st['players']=$players;

  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  $view = build_masks_for($st,$me);
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
  if(count($st['chat'])>200){ $st['chat'] = array_slice($st['chat'], -200); }
  $st['chat_last_id'] = $id;
  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return ['success'=>true, 'chat'=>[$msg], 'last_chat_id'=>$id];
}
