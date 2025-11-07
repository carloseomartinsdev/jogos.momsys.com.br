<?php
// Ludo Alternativo (grafo modular) — PHP 7+
// Estado em JSON na pasta rooms/
header('Content-Type: application/json; charset=utf-8');

$DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'rooms';
$BOARD_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'boards';
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0777, true); }

try{
  $a = isset($_POST['action']) ? $_POST['action'] : '';
  switch($a){
    case 'create': respond(action_create(safe($_POST,'board'), intval($_POST['maxp']??2))); break;
    case 'join':   respond(action_join(up($_POST,'room'))); break;
    case 'poll':   respond(action_poll(up($_POST,'room'), gp('token'), gint('version'), gint('last_chat_id'))); break;
    case 'roll':   respond(action_roll(up($_POST,'room'), gp('token'))); break;
    case 'move':   respond(action_move(up($_POST,'room'), gp('token'), intval($_POST['piece']??-1), safe($_POST,'toType'), safe($_POST,'toId'))); break;
    case 'restart':respond(action_restart(up($_POST,'room'), gp('token'))); break;
    case 'chat_send': respond(action_chat_send(up($_POST,'room'), gp('token'), trim((string)($_POST['text']??'')))); break;
    default: throw new Exception('ação inválida');
  }
}catch(Exception $e){
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit;
}

/* ===== Helpers ===== */
function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function gp($k){ return isset($_POST[$k])?$_POST[$k]:''; }
function gint($k){ return intval(isset($_POST[$k])?$_POST[$k]:0); }
function safe($arr,$k){ return preg_replace('/[^A-Za-z0-9 _@.,;:!?+-]/u','', isset($arr[$k])?$arr[$k]:''); }
function up($arr,$k){ return strtoupper(safe($arr,$k)); }
function codefile($room){ global $DATA_DIR; return $DATA_DIR.DIRECTORY_SEPARATOR.$room.'.json'; }
function randCode($n=6){ $c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s=''; for($i=0;$i<$n;$i++){$s.=$c[random_int(0,strlen($c)-1)];} return $s; }
function randToken(){ return bin2hex(random_bytes(16)); }
function load_json($file){ $t=@file_get_contents($file); if(!$t) return null; return json_decode($t,true); }

function load_state($room){ $f=codefile($room); if(!file_exists($f)) return null;
  $fp=fopen($f,'r'); flock($fp,LOCK_SH); $txt=stream_get_contents($fp); flock($fp,LOCK_UN); fclose($fp);
  return json_decode($txt,true);
}
function save_state($room,$st){
  $f=codefile($room); $fp=fopen($f,'c+'); if(!$fp) throw new Exception('Falha ao abrir arquivo');
  flock($fp,LOCK_EX); ftruncate($fp,0); fwrite($fp,json_encode($st,JSON_UNESCAPED_UNICODE)); fflush($fp); flock($fp,LOCK_UN); fclose($fp);
}

/* ===== Engine ===== */
function new_state($boardName, $board, $maxp){
  $order = ['A','B','C','D'];
  $order = array_slice($order, 0, max(2, min(4, $maxp)));
  $pieces=[]; $finished=[]; $finished_count=[];
  foreach(['A','B','C','D'] as $L){
    $pieces[$L]=[];
    for($i=0;$i<4;$i++){ $pieces[$L][]=['pos'=>'BASE']; }
    $finished[$L]=false; $finished_count[$L]=0;
  }
  return [
    'board_name'=>$boardName,
    'players'=>['A'=>null,'B'=>null,'C'=>null,'D'=>null],
    'order'=>$order,
    'turn'=>'A',
    'last_die'=>null,
    'rolled'=>false,              // já rolou neste turno
    'must_move'=>false,           // aguarda mover depois do roll
    'legal'=>[],                  // movimentos válidos calculados
    'pieces'=>$pieces,
    'finished'=>$finished,
    'finished_count'=>$finished_count,
    'winner'=>null, 'finished_all'=>false,
    'version'=>1, 'updated_at'=>time(),
    'chat'=>[], 'chat_last_id'=>0,
    // opções básicas
    'rules'=>['extra_on_six'=>true, 'extra_on_capture'=>true]
  ];
}

function everyone_connected($st){
  foreach($st['order'] as $L){ if(empty($st['players'][$L])) return false; } return true;
}

/* ==== Graph helpers ==== */
function board_load($name){
  global $BOARD_DIR;
  $path = $BOARD_DIR.DIRECTORY_SEPARATOR.$name.'.json';
  $b = load_json($path);
  if(!$b) throw new Exception('board não encontrado');
  // monta mapas
  $map=[]; foreach($b['nodes'] as $n){ $map[$n['id']]=$n; }
  $b['nodeMap']=$map;
  // startBases fallback
  if(!isset($b['startBases'])) $b['startBases']=['A'=>['x'=>5,'y'=>95],'B'=>['x'=>95,'y'=>5],'C'=>['x'=>5,'y'=>5],'D'=>['x'=>95,'y'=>95]];
  // metaNodes fallback (usa último de homePaths)
  if(!isset($b['metaNodes'])){
    $b['metaNodes']=[];
    foreach(['A','B','C','D'] as $L){
      $hp=$b['homePaths'][$L]??[];
      $last=end($hp);
      if($last && isset($map[$last])) $b['metaNodes'][$L]=['x'=>$map[$last]['x'],'y'=>$map[$last]['y']];
      else $b['metaNodes'][$L]=['x'=>50,'y'=>50];
    }
  }
  return $b;
}

/* ==== Legal moves ==== */
function compute_legal($st, $board, $who){
  $die = $st['last_die'];
  if(!$die) return [];
  $legal = [];
  // para cada peça do jogador
  foreach($st['pieces'][$who] as $idx=>$p){
    if($p['pos']==='META') continue;
    // SAIR DA BASE com 6
    if($p['pos']==='BASE'){
      if($die==6){
        $entry = $board['homeEntrances'][$who]; // nó início
        if($entry){
          // se a casa estiver livre ou ocupada por adversário (pode capturar se não segura)
          if(can_land_on($st,$board,$who,$entry)){
            $legal[]=['pieceIdx'=>$idx, 'toType'=>'START', 'toId'=>$entry, 'path'=>[$entry]];
          }
        }
      }
      continue;
    }

    // caminhar pelo grafo N passos a partir do nó atual
    $from = $p['pos'];
    $targets = walk_targets($st,$board,$who,$from,$die);
    foreach($targets as $t){
      $legal[]=['pieceIdx'=>$idx, 'toType'=>$t['toType'], 'toId'=>$t['toId'], 'path'=>$t['path']];
    }
  }
  return $legal;
}

function walk_targets($st,$board,$who,$from,$steps){
  // percorre edges direcionais contando passos; suporta entrar na homePath quando passar pelo portal de entrada
  $out=[];
  $visited=[];

  // DFS limitado por passos
  $stack=[ ['at'=>$from, 'left'=>$steps, 'path'=>[]] ];
  while($stack){
    $cur=array_pop($stack);
    $at=$cur['at']; $left=$cur['left']; $path=$cur['path'];
    $key=$at.'|'.$left; if(isset($visited[$key])) continue; $visited[$key]=1;

    // se estamos em nó de entrada da home e ainda restam passos, seguimos a home
    $entry = $board['homeEntrances'][$who];
    if($at===$entry){
      $hp = $board['homePaths'][$who] ?? [];
      if($hp){
        $posIndex = 0; // primeira casa da trilha da home
        $adv = min($left, count($hp));
        if($adv>0){
          $dest = $hp[$adv-1];
          if($adv==$left){ // landing
            if(can_land_on_home($st,$board,$who,$dest)){
              $out[]=['toType'=>'HOME','toId'=>$dest,'path'=>array_merge($path, array_slice($hp,0,$adv))];
            }
          }else{
            $stack[]=['at'=>$hp[$adv-1],'left'=>$left-$adv,'path'=>array_merge($path, array_slice($hp,0,$adv))];
          }
        }
        // não seguir edges normais se no entry (para forçar ir à home)
        continue;
      }
    }

    if($left===0){
      // posição final é um nó normal
      if(can_land_on($st,$board,$who,$at)){
        $out[]=['toType'=>'NODE','toId'=>$at,'path'=>$path];
      }
      continue;
    }

    $node = $board['nodeMap'][$at] ?? null;
    if(!$node) continue;
    $edges = $node['edges'] ?? [];

    // portal: nó com "to" salta
    if(isset($node['to']) && $node['to']!==''){
      $to=$node['to'];
      $stack[]=['at'=>$to,'left'=>$left-1,'path'=>array_merge($path,[$to])];
      continue;
    }

    foreach($edges as $nx){
      $stack[]=['at'=>$nx,'left'=>$left-1,'path'=>array_merge($path,[$nx])];
    }
  }

  // remove duplicados de destino
  $uniq=[]; $final=[];
  foreach($out as $t){
    $k=$t['toType'].':'.$t['toId'];
    if(isset($uniq[$k])) continue; $uniq[$k]=1; $final[]=$t;
  }
  return $final;
}

function can_land_on($st,$board,$who,$nodeId){
  $n = $board['nodeMap'][$nodeId] ?? null; if(!$n) return false;
  // verifica ocupação
  $occup = occupants($st, $nodeId);
  if(!$occup) return true; // livre
  // se segura, não captura
  if($n['type']==='segura') return false;
  // se só aliados, pode empilhar? (simplo: permite empilhar 2+ do mesmo player)
  foreach($occup as $o){ if($o['L']!==$who) return true; }
  // todos aliados -> permitir (empilhar)
  return true;
}
function can_land_on_home($st,$board,$who,$nodeId){
  // nós da home só podem ser ocupados pelas suas peças; pro simplo, 1 peça por casa
  $occ = occupants($st, $nodeId);
  if(!$occ) return true;
  foreach($occ as $o){ if($o['L']!==$who) return false; }
  // se já tem sua peça, bloqueia (1 por casa)
  return false;
}
function occupants($st,$nodeId){
  $list=[];
  foreach($st['order'] as $L){
    foreach($st['pieces'][$L] as $idx=>$p){
      if($p['pos']===$nodeId){ $list[]=['L'=>$L,'idx'=>$idx]; }
    }
  }
  return $list;
}

/* ==== Ações ==== */
function action_create($boardName,$maxp){
  $boardName = $boardName ?: 'oito';
  $board = board_load($boardName);

  $st = new_state($boardName, $board, $maxp);
  $room = randCode(6);
  $tok = randToken();
  $st['players']['A']=$tok; // criador
  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  $view = pack_view($st,'A',$board);
  $view['room']=$room; $view['token']=$tok; $view['you_side']='A';
  $view['last_chat_id']=$st['chat_last_id'];
  return $view;
}

function action_join($room){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $board = board_load($st['board_name']);

  foreach($st['order'] as $L){
    if(empty($st['players'][$L])){
      $tok=randToken(); $st['players'][$L]=$tok; $st['version']++; $st['updated_at']=time();
      save_state($room,$st);
      $view = pack_view($st,$L,$board);
      $view['room']=$room; $view['token']=$tok; $view['you_side']=$L;
      $view['last_chat_id']=$st['chat_last_id'];
      return $view;
    }
  }
  return ['success'=>false,'error'=>'Sala cheia'];
}

function action_poll($room,$token,$clientV,$lastChatId){
  $st = load_state($room); if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = whoami($st,$token); if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  $board = board_load($st['board_name']);

  $out=['success'=>true,'update'=>false];
  if($st['version']>$clientV){
    $out['update']=true;
    $view = pack_view($st,$me,$board);
    $out = array_merge($out, $view);
  }
  if($st['chat_last_id']>$lastChatId){
    $msgs = [];
    foreach($st['chat'] as $m){ if($m['id']>$lastChatId) $msgs[]=$m; }
    $out['chat']=$msgs;
  }else $out['chat']=[];

  return $out;
}

function action_roll($room,$token){
  $st = load_state($room); if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = whoami($st,$token); if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if($st['turn']!==$me) return ['success'=>false,'error'=>'Não é sua vez'];
  if(!$st['players'][$me]) return ['success'=>false,'error'=>'Jogador ausente'];
  if($st['finished_all']) return ['success'=>false,'error'=>'Jogo finalizado'];
  if($st['rolled'] && $st['must_move']) return ['success'=>false,'error'=>'Você precisa mover primeiro'];

  $die = random_int(1,6);
  $st['last_die']=$die; $st['rolled']=true;

  $board = board_load($st['board_name']);
  $legal = compute_legal($st,$board,$me);
  $st['legal']=$legal; $st['must_move']= count($legal)>0;

  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return pack_view($st,$me,$board);
}

function action_move($room,$token,$pieceIdx,$toType,$toId){
  $st = load_state($room); if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = whoami($st,$token); if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  if($st['turn']!==$me) return ['success'=>false,'error'=>'Não é sua vez'];
  if(!$st['rolled']) return ['success'=>false,'error'=>'Role o dado primeiro'];
  $board = board_load($st['board_name']);

  // valida está na lista de legais
  $ok=false; $mv=null;
  foreach($st['legal'] as $m){
    if($m['pieceIdx']==$pieceIdx && $m['toType']===$toType && $m['toId']===$toId){ $ok=true; $mv=$m; break; }
  }
  if(!$ok) return ['success'=>false,'error'=>'Movimento inválido'];

  $die = $st['last_die'];
  $capture=false;

  if($toType==='START' || $toType==='NODE'){
    // aplica captura se houver inimigos e não for segura
    if($toType==='START' || $toType==='NODE'){
      $n = $board['nodeMap'][$toId] ?? null;
      if($n){
        $occ = occupants($st,$toId);
        if($occ){
          if($n['type']!=='segura'){
            foreach($occ as $o){
              if($o['L']!==$me){
                // manda de volta pra base
                $st['pieces'][$o['L']][$o['idx']]['pos']='BASE';
                $capture=true;
              }
            }
          }else{
            // se segura e tem ocupante inimigo, não deveria estar nas legais; proteção extra
            // cancela
          }
        }
      }
    }
    $st['pieces'][$me][$pieceIdx]['pos']=$toId;
  } elseif($toType==='HOME'){
    // entrar/andar na trilha da home; se for último nó, vira META
    $hp = $board['homePaths'][$me] ?? [];
    $last = end($hp);
    if($toId===$last){
      $st['pieces'][$me][$pieceIdx]['pos']='META';
      $st['finished_count'][$me] += 1;
      if($st['finished_count'][$me] >= 4){ $st['finished'][$me]=true; }
    } else {
      $st['pieces'][$me][$pieceIdx]['pos']=$toId;
    }
  }

  // fim do lance
  $st['rolled']=false; $st['must_move']=false; $st['legal']=[]; $st['last_die']=$die;

  // vitória?
  $alive=[];
  foreach($st['order'] as $L){ $alive[$L]=!$st['finished'][$L]; }
  $aliveCount=0; $last=null; foreach($alive as $L=>$aliveL){ if($st['players'][$L] && $aliveL){ $aliveCount++; $last=$L; } }
  if($aliveCount<=1){
    $st['winner']=$last; $st['finished_all']=true;
  }

  // próximo turno
  if(!$st['finished_all']){
    if(($st['rules']['extra_on_six'] && $die==6) || ($st['rules']['extra_on_capture'] && $capture)){
      // mantém o turno
    } else {
      $st['turn'] = next_turn($st, $st['turn']);
    }
  }

  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  return pack_view($st,$me,$board);
}

function next_turn($st,$cur){
  $order=$st['order']; $present=[];
  foreach($order as $L){ if(!empty($st['players'][$L]) && !$st['finished'][$L]) $present[]=$L; }
  if(!$present) return $cur;
  $idx=array_search($cur,$present,true); if($idx===false) return $present[0];
  $idx=($idx+1)%count($present); return $present[$idx];
}

function action_restart($room,$token){
  $st = load_state($room); if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = whoami($st,$token); if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
  $board = board_load($st['board_name']);

  $players=$st['players']; $order=$st['order']; $name=$st['board_name'];
  $st = new_state($name,$board,count($order));
  $st['players']=$players; $st['order']=$order;

  $st['version']++; $st['updated_at']=time();
  save_state($room,$st);

  $view = pack_view($st,$me,$board);
  $view['last_chat_id']=$st['chat_last_id'];
  return $view;
}

/* ==== View ==== */
function pack_view($st,$me,$board){
  return [
    'success'=>true,
    'you_side'=>$me,
    'board'=>minify_board($board),
    'state'=>[
      'version'=>$st['version'],
      'turn'=>$st['turn'],
      'order'=>$st['order'],
      'players'=>$st['players'],
      'last_die'=>$st['last_die'],
      'rolled'=>$st['rolled'],
      'must_move'=>$st['must_move'],
      'legal'=>$st['legal'],
      'pieces'=>$st['pieces'],
      'finished'=>$st['finished'],
      'finished_count'=>$st['finished_count'],
      'winner'=>$st['winner'],
      'finished_all'=>$st['finished_all']
    ]
  ];
}
function minify_board($b){
  // devolve somente o necessário p/ render
  return [
    'nodes'=>$b['nodes'],
    'edges'=>$b['edges'],
    'nodeMap'=>$b['nodeMap'],
    'homeEntrances'=>$b['homeEntrances'],
    'homePaths'=>$b['homePaths'],
    'startBases'=>$b['startBases'],
    'metaNodes'=>$b['metaNodes']
  ];
}

/* ==== Utils ==== */
function whoami($st,$token){ foreach($st['players'] as $L=>$t){ if($t===($token?:'')) return $L; } return null; }

/* ==== Chat ==== */
function action_chat_send($room,$token,$text){
  $st = load_state($room);
  if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
  $me = whoami($st,$token); if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
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
