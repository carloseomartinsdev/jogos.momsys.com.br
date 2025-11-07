<?php
// Ludo Alternativo (API) — PHP 7+
// Armazena estado de salas em JSON dentro de rooms/
// Este endpoint fala com ludo.js via POST (form-urlencoded) ou JSON.

header('Content-Type: application/json; charset=utf-8');

$DATA_DIR  = __DIR__ . DIRECTORY_SEPARATOR . 'rooms';
$BOARD_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'boards';
if (!is_dir($DATA_DIR))  { @mkdir($DATA_DIR, 0777, true); }
if (!is_dir($BOARD_DIR)) { @mkdir($BOARD_DIR, 0777, true); }

// =====================
// Entrada robusta
// =====================
$request = $_POST;

// Aceita JSON também
if (empty($request)) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) $request = $json;
        }
    }
}

// Alias para contornar WAF que bloqueia "action"
if (!isset($request['action']) && isset($request['a'])) {
    $request['action'] = $request['a'];
}

try {
    $action = strtolower(trim($request['action'] ?? ''));
    switch ($action) {
        case 'create':  respond(action_create(safe($request, 'board', 'oito'), intval($request['maxp'] ?? 2))); break;
        case 'join':    respond(action_join(up($request, 'room'))); break;
        case 'poll':    respond(action_poll(up($request, 'room'), gp($request, 'token'), gint($request, 'version'), gint($request, 'last_chat_id'))); break;
        case 'roll':    respond(action_roll(up($request, 'room'), gp($request, 'token'))); break;
        case 'move':    respond(action_move(up($request, 'room'), gp($request, 'token'), intval($request['piece'] ?? -1), safe($request, 'toType'), safe($request, 'toId'))); break;
        case 'restart': respond(action_restart(up($request, 'room'), gp($request, 'token'))); break;
        case 'chat_send': respond(action_chat_send(up($request, 'room'), gp($request, 'token'), trim((string)($request['text'] ?? '')))); break;
        default: throw new Exception('ação inválida');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================
// Helpers
// =====================
function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function gp($arr,$k){ return isset($arr[$k]) ? $arr[$k] : ''; }
function gint($arr,$k){ return intval(isset($arr[$k]) ? $arr[$k] : 0); }
function safe($arr,$k,$def=''){
    // permite letras/números/espaco/_ @ . , ; : ! ? + - (evita quebrar room/token)
    $v = isset($arr[$k]) ? $arr[$k] : $def;
    return preg_replace('/[^A-Za-z0-9 _@.,;:!?+\-]/u','', $v);
}
function up($arr,$k,$def=''){ return strtoupper(safe($arr,$k,$def)); }

function codefile($room){ global $DATA_DIR; return $DATA_DIR . DIRECTORY_SEPARATOR . $room . '.json'; }
function randCode($n=6){ $c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s=''; for($i=0;$i<$n;$i++){$s.=$c[random_int(0,strlen($c)-1)];} return $s; }
function randToken(){ return bin2hex(random_bytes(16)); }

function load_state($room){
    $f = codefile($room);
    if (!file_exists($f)) return null;
    $fp = fopen($f, 'r');
    if (!$fp) return null;
    flock($fp, LOCK_SH);
    $txt = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $st = json_decode($txt, true);
    return is_array($st) ? $st : null;
}
function save_state($room,$st){
    $f = codefile($room);
    $fp = fopen($f, 'c+');
    if (!$fp) throw new Exception('Falha ao abrir arquivo de estado');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($st, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function load_json($file){
    $t = @file_get_contents($file);
    if ($t === false || $t === '') return null;
    $j = json_decode($t, true);
    return is_array($j) ? $j : null;
}

// =====================
// Board / Engine auxiliares
// =====================
function board_load($name){
    global $BOARD_DIR;
    $path = $BOARD_DIR . DIRECTORY_SEPARATOR . $name . '.json';
    $b = load_json($path);
    if (!$b) throw new Exception('board não encontrado');

    // nodeMap
    $map = [];
    foreach ($b['nodes'] as $n) { $map[$n['id']] = $n; }
    $b['nodeMap'] = $map;

    // startBases fallback
    if (!isset($b['startBases'])) $b['startBases']=['A'=>['x'=>5,'y'=>95],'B'=>['x'=>95,'y'=>5],'C'=>['x'=>5,'y'=>5],'D'=>['x'=>95,'y'=>95]];
    // metaNodes fallback
    if (!isset($b['metaNodes'])){
        $b['metaNodes']=[];
        foreach(['A','B','C','D'] as $L){
            $hp = $b['homePaths'][$L] ?? [];
            $last = end($hp);
            if ($last && isset($map[$last])) $b['metaNodes'][$L] = ['x'=>$map[$last]['x'],'y'=>$map[$last]['y']];
            else $b['metaNodes'][$L] = ['x'=>50,'y'=>50];
        }
    }
    return $b;
}

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
        'rolled'=>false,
        'must_move'=>false,
        'legal'=>[],
        'pieces'=>$pieces,
        'finished'=>$finished,
        'finished_count'=>$finished_count,
        'winner'=>null, 'finished_all'=>false,
        'version'=>1, 'updated_at'=>time(),
        'chat'=>[], 'chat_last_id'=>0,
        'rules'=>['extra_on_six'=>true, 'extra_on_capture'=>true]
    ];
}

function whoami($st,$token){
    foreach($st['players'] as $L=>$t){ if($t === ($token ?: '')) return $L; }
    return null;
}

function occupants($st,$nodeId){
    $list=[];
    foreach($st['order'] as $L){
        foreach($st['pieces'][$L] as $idx=>$p){
            if($p['pos'] === $nodeId){ $list[]=['L'=>$L,'idx'=>$idx]; }
        }
    }
    return $list;
}
function can_land_on($st,$board,$who,$nodeId){
    $n = $board['nodeMap'][$nodeId] ?? null; if(!$n) return false;
    $occ = occupants($st,$nodeId);
    if(!$occ) return true;
    if(($n['type'] ?? '') === 'segura') return false; // segura não captura nem ocupa
    foreach($occ as $o){ if($o['L'] !== $who) return true; } // pode capturar inimigos
    return true; // empilhar aliados permitido (simples)
}
function can_land_on_home($st,$board,$who,$nodeId){
    $occ = occupants($st,$nodeId);
    if(!$occ) return true;
    foreach($occ as $o){ if($o['L'] !== $who) return false; }
    return false; // 1 por casa (bloqueia se já tem sua)
}

function compute_legal($st, $board, $who){
    $die = $st['last_die'];
    if(!$die) return [];
    $legal = [];
    foreach($st['pieces'][$who] as $idx=>$p){
        if($p['pos'] === 'META') continue;

        // sair da base
        if($p['pos'] === 'BASE'){
            if($die == 6){
                $entry = $board['homeEntrances'][$who] ?? null;
                if($entry && can_land_on($st,$board,$who,$entry)){
                    $legal[] = ['pieceIdx'=>$idx, 'toType'=>'START', 'toId'=>$entry, 'path'=>[$entry]];
                }
            }
            continue;
        }

        // caminhar grafo
        $from = $p['pos'];
        foreach(walk_targets($st,$board,$who,$from,$die) as $t){
            $legal[] = $t + ['pieceIdx'=>$idx];
        }
    }
    // dedup
    $uniq=[]; $out=[];
    foreach($legal as $m){ $k=$m['pieceIdx'].'|'.$m['toType'].'|'.$m['toId']; if(isset($uniq[$k])) continue; $uniq[$k]=1; $out[]=$m; }
    return $out;
}

function walk_targets($st,$board,$who,$from,$steps){
    $out=[]; $visited=[]; $stack=[ ['at'=>$from,'left'=>$steps,'path'=>[]] ];
    while($stack){
        $cur=array_pop($stack);
        $at=$cur['at']; $left=$cur['left']; $path=$cur['path'];
        $key=$at.'|'.$left; if(isset($visited[$key])) continue; $visited[$key]=1;

        // entrada da home
        $entry = $board['homeEntrances'][$who] ?? null;
        if($at === $entry){
            $hp = $board['homePaths'][$who] ?? [];
            if($hp){
                $adv = min($left, count($hp));
                if($adv > 0){
                    $dest = $hp[$adv-1];
                    if($adv == $left){
                        if(can_land_on_home($st,$board,$who,$dest)){
                            $out[] = ['toType'=>'HOME','toId'=>$dest,'path'=>array_merge($path, array_slice($hp,0,$adv))];
                        }
                    } else {
                        $stack[] = ['at'=>$hp[$adv-1],'left'=>$left-$adv,'path'=>array_merge($path, array_slice($hp,0,$adv))];
                    }
                }
                // não segue edges normais a partir do entry
                continue;
            }
        }

        if($left === 0){
            if(can_land_on($st,$board,$who,$at)){
                $out[] = ['toType'=>'NODE','toId'=>$at,'path'=>$path];
            }
            continue;
        }

        $node = $board['nodeMap'][$at] ?? null; if(!$node) continue;

        // portal (salto conta como 1 passo)
        if(isset($node['to']) && $node['to']!==''){
            $to=$node['to'];
            $stack[]=['at'=>$to,'left'=>$left-1,'path'=>array_merge($path,[$to])];
            continue;
        }

        $edges = $node['edges'] ?? [];
        foreach($edges as $nx){
            $stack[]=['at'=>$nx,'left'=>$left-1,'path'=>array_merge($path,[$nx])];
        }
    }
    // dedup destino
    $u=[]; $final=[];
    foreach($out as $t){ $k=$t['toType'].':'.$t['toId']; if(isset($u[$k])) continue; $u[$k]=1; $final[]=$t; }
    return $final;
}

function next_turn($st,$cur){
    $order=$st['order']; $present=[];
    foreach($order as $L){ if(!empty($st['players'][$L]) && !$st['finished'][$L]) $present[]=$L; }
    if(!$present) return $cur;
    $idx = array_search($cur,$present,true);
    if($idx === false) return $present[0];
    $idx = ($idx+1) % count($present);
    return $present[$idx];
}

function pack_view($st,$me,$board){
    return [
        'success'=>true,
        'you_side'=>$me,
        'board'=>[
            'nodes'=>$board['nodes'],
            'edges'=>$board['edges'],
            'nodeMap'=>$board['nodeMap'],
            'homeEntrances'=>$board['homeEntrances'],
            'homePaths'=>$board['homePaths'],
            'startBases'=>$board['startBases'],
            'metaNodes'=>$board['metaNodes']
        ],
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

// =====================
// Ações
// =====================
function action_create($boardName,$maxp){
    $boardName = $boardName ?: 'oito';
    $board = board_load($boardName);

    $st = new_state($boardName, $board, $maxp);
    $room = randCode(6);
    $tok  = randToken();
    $st['players']['A'] = $tok;
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
            $tok = randToken();
            $st['players'][$L]=$tok;
            $st['version']++; $st['updated_at']=time();
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
    $st = load_state($room);
    if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
    $me = whoami($st,$token);
    if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];

    $board = board_load($st['board_name']);
    $out=['success'=>true,'update'=>false];

    if($st['version'] > $clientV){
        $out['update']=true;
        $view = pack_view($st,$me,$board);
        $out = array_merge($out, $view);
    }
    if($st['chat_last_id'] > $lastChatId){
        $msgs=[];
        foreach($st['chat'] as $m){ if($m['id']>$lastChatId) $msgs[]=$m; }
        $out['chat']=$msgs;
    } else {
        $out['chat']=[];
    }
    return $out;
}

function action_roll($room,$token){
    $st = load_state($room);
    if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
    $me = whoami($st,$token);
    if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
    if($st['turn'] !== $me) return ['success'=>false,'error'=>'Não é sua vez'];
    if($st['finished_all']) return ['success'=>false,'error'=>'Jogo finalizado'];
    if($st['rolled'] && $st['must_move']) return ['success'=>false,'error'=>'Você precisa mover primeiro'];

    $die = random_int(1,6);
    $st['last_die']=$die; $st['rolled']=true;

    $board = board_load($st['board_name']);
    $st['legal'] = compute_legal($st,$board,$me);
    $st['must_move'] = count($st['legal'])>0;

    $st['version']++; $st['updated_at']=time();
    save_state($room,$st);

    return pack_view($st,$me,$board);
}

function action_move($room,$token,$pieceIdx,$toType,$toId){
    $st = load_state($room);
    if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
    $me = whoami($st,$token);
    if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];
    if($st['turn'] !== $me) return ['success'=>false,'error'=>'Não é sua vez'];
    if(!$st['rolled']) return ['success'=>false,'error'=>'Role o dado primeiro'];

    $board = board_load($st['board_name']);

    // valida legais
    $mv=null;
    foreach($st['legal'] as $m){
        if($m['pieceIdx']===$pieceIdx && $m['toType']===$toType && $m['toId']===$toId){ $mv=$m; break; }
    }
    if(!$mv) return ['success'=>false,'error'=>'Movimento inválido'];

    $die = $st['last_die'];
    $capture=false;

    if($toType==='START' || $toType==='NODE'){
        $n = $board['nodeMap'][$toId] ?? null;
        if($n){
            $occ = occupants($st,$toId);
            if($occ && (($n['type'] ?? '') !== 'segura')){
                foreach($occ as $o){
                    if($o['L'] !== $me){
                        $st['pieces'][$o['L']][$o['idx']]['pos']='BASE';
                        $capture=true;
                    }
                }
            }
        }
        $st['pieces'][$me][$pieceIdx]['pos'] = $toId;
    } elseif($toType==='HOME'){
        $hp = $board['homePaths'][$me] ?? [];
        $last = end($hp);
        if($toId === $last){
            $st['pieces'][$me][$pieceIdx]['pos']='META';
            $st['finished_count'][$me] += 1;
            if($st['finished_count'][$me] >= 4){ $st['finished'][$me]=true; }
        } else {
            $st['pieces'][$me][$pieceIdx]['pos']=$toId;
        }
    } else {
        return ['success'=>false,'error'=>'Destino inválido'];
    }

    // reset flags
    $st['rolled']=false; $st['must_move']=false; $st['legal']=[];

    // vencedor?
    $alive=[]; foreach($st['order'] as $L){ $alive[$L] = !$st['finished'][$L]; }
    $aliveCount=0; $last=null;
    foreach($alive as $L=>$al){ if($st['players'][$L] && $al){ $aliveCount++; $last=$L; } }
    if($aliveCount<=1){ $st['winner']=$last; $st['finished_all']=true; }

    // próximo turno
    if(!$st['finished_all']){
        if(($st['rules']['extra_on_six'] && $die==6) || ($st['rules']['extra_on_capture'] && $capture)){
            // mantém turno
        } else {
            $st['turn'] = next_turn($st, $st['turn']);
        }
    }

    $st['version']++; $st['updated_at']=time();
    save_state($room,$st);

    return pack_view($st,$me,$board);
}

function action_restart($room,$token){
    $st = load_state($room);
    if(!$st) return ['success'=>false,'error'=>'Sala não encontrada'];
    $me = whoami($st,$token);
    if(!$me) return ['success'=>false,'error'=>'Jogador não reconhecido'];

    $board = board_load($st['board_name']);
    $players = $st['players']; $order=$st['order']; $name=$st['board_name'];

    $st = new_state($name,$board,count($order));
    $st['players']=$players; $st['order']=$order;

    $st['version']++; $st['updated_at']=time();
    save_state($room,$st);

    $view = pack_view($st,$me,$board);
    $view['last_chat_id']=$st['chat_last_id'];
    return $view;
}

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
