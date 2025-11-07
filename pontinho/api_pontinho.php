<?php
// API Pontinho em rede — 2 a 4 jogadores (A,B,C,D)
// Salva estado como JSON em rooms/{CODE}.json
// Compatível com PHP 7.0+

header('Content-Type: application/json; charset=utf-8');

$DATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'rooms';
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0777, true); }

try {
  $action = isset($_POST['action']) ? $_POST['action'] : '';
  switch ($action) {
    case 'create':
      $size = isset($_POST['size']) ? $_POST['size'] : '4x4';
      $maxp = isset($_POST['maxp']) ? intval($_POST['maxp']) : 2;
      if($maxp < 2 || $maxp > 4) $maxp = 2;
      respond(action_create($size, $maxp));
      break;

    case 'join':
      $room = strtoupper(safe($_POST, 'room'));
      if(!$room) throw new Exception('room ausente');
      respond(action_join($room));
      break;

    case 'poll':
      $room = strtoupper(safe($_POST, 'room'));
      $version = intval(isset($_POST['version']) ? $_POST['version'] : 0);
      respond(action_poll($room, $version));
      break;

    case 'move':
      $room = strtoupper(safe($_POST, 'room'));
      $token = isset($_POST['token']) ? $_POST['token'] : '';
      $o = safe($_POST, 'o'); // 'h'|'v'
      $r = intval(isset($_POST['r'])?$_POST['r']:-1);
      $c = intval(isset($_POST['c'])?$_POST['c']:-1);
      respond(action_move($room, $token, $o, $r, $c));
      break;

    case 'restart':
      $room = strtoupper(safe($_POST, 'room'));
      $token = isset($_POST['token']) ? $_POST['token'] : '';
      respond(action_restart($room, $token));
      break;

    default:
      throw new Exception('ação inválida');
  }
} catch (Exception $e) {
  echo json_encode(array('success'=>false, 'error'=>$e->getMessage()));
  exit;
}

/* ---------- Helpers ---------- */
function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function safe($arr, $k){ return preg_replace('/[^A-Za-z0-9_-]/','', isset($arr[$k])?$arr[$k]:''); }
function codefile($room){ global $DATA_DIR; return $DATA_DIR . DIRECTORY_SEPARATOR . $room . '.json'; }
function randCode($n=6){
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $s=''; for($i=0;$i<$n;$i++){ $s .= $chars[random_int(0, strlen($chars)-1)]; }
  return $s;
}
function randToken(){ return bin2hex(random_bytes(16)); }

function file_read_state($room){
  $path = codefile($room);
  if(!file_exists($path)) return null;
  $fp = fopen($path, 'r');
  if(!$fp) return null;
  flock($fp, LOCK_SH);
  $txt = stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return json_decode($txt, true);
}
function file_write_state($room, $state){
  $path = codefile($room);
  $fp = fopen($path, 'c+');
  if(!$fp) throw new Exception('Falha ao abrir arquivo');
  flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE));
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
}

/* ---------- Estado inicial ---------- */
function new_state($rows, $cols, $maxp){
  return array(
    'rows'=>$rows, 'cols'=>$cols, 'max_players'=>$maxp,
    'current'=>'A', // começa em A
    'score'=>array('A'=>0, 'B'=>0, 'C'=>0, 'D'=>0),
    'edges'=>array('h'=>array(), 'v'=>array()), // "h:r:c"/"v:r:c" => 'A'|'B'|'C'|'D'
    'boxes'=>array_fill(0, $rows, array_fill(0, $cols, null)), // 'A'|'B'|'C'|'D'|null
    'finished'=>false,
    'version'=>1,
    'updated_at'=>time(),
    'players'=>array('A'=>null, 'B'=>null, 'C'=>null, 'D'=>null) // tokens
  );
}

/* ---------- Ações ---------- */
function action_create($size, $maxp){
  $parts = explode('x', strtolower($size));
  $rows = isset($parts[0])?intval($parts[0]):4;
  $cols = isset($parts[1])?intval($parts[1]):4;
  if($rows<2||$rows>12||$cols<2||$cols>12) { $rows=4; $cols=4; }

  // código único
  $attempts=0;
  do{
    $room = randCode(6);
    $attempts++;
  }while(file_exists(codefile($room)) && $attempts<10);

  $state = new_state($rows,$cols,$maxp);
  $tokenA = randToken();
  $state['players']['A'] = $tokenA;

  file_write_state($room, $state);

  return array(
    'success'=>true,
    'room'=>$room,
    'you_side'=>'A',
    'token'=>$tokenA,
    'state'=>$state
  );
}

function action_join($room){
  $state = file_read_state($room);
  if(!$state) return array('success'=>false, 'error'=>'Sala não encontrada');

  // encontra próximo slot disponível respeitando max_players
  $order = array('A','B','C','D');
  $maxp = isset($state['max_players']) ? intval($state['max_players']) : 2;
  for($i=0;$i<$maxp;$i++){
    $letter = $order[$i];
    if(empty($state['players'][$letter])){
      $token = randToken();
      $state['players'][$letter] = $token;
      $state['version']++;
      $state['updated_at'] = time();
      file_write_state($room, $state);
      return array(
        'success'=>true,
        'room'=>$room,
        'you_side'=>$letter,
        'token'=>$token,
        'state'=>$state
      );
    }
  }

  return array('success'=>false, 'error'=>'Sala já está completa');
}

function action_poll($room, $clientVersion){
  $state = file_read_state($room);
  if(!$state) return array('success'=>false, 'error'=>'Sala não encontrada');
  if($state['version'] > $clientVersion){
    return array('success'=>true, 'update'=>true, 'state'=>$state);
  }
  return array('success'=>true, 'update'=>false);
}

/* ---------- Regras ---------- */
function keyH($r,$c){ return 'h:'.$r.':'.$c; }
function keyV($r,$c){ return 'v:'.$r.':'.$c; }

/* Limites:
   - horizontal: r = 0..rows,  c = 0..cols-1
   - vertical:   r = 0..rows-1, c = 0..cols
*/
function insideEdge($o,$r,$c,$rows,$cols){
  if($o==='h'){
    return ($r>=0 && $r<=$rows && $c>=0 && $c<$cols);
  }else{
    return ($r>=0 && $r<$rows && $c>=0 && $c<=$cols);
  }
}

function edgeBoxes($o,$r,$c,$rows,$cols){
  $list = array();
  if($o==='h'){
    if($r>0 && $r-1<$rows && $c<$cols){ $list[] = array('r'=>$r-1,'c'=>$c); }
    if($r<$rows && $c<$cols){ $list[] = array('r'=>$r,'c'=>$c); }
  }else{
    if($c>0 && $c-1<$cols && $r<$rows){ $list[] = array('r'=>$r,'c'=>$c-1); }
    if($c<$cols && $r<$rows){ $list[] = array('r'=>$r,'c'=>$c); }
  }
  $out = array();
  for($i=0;$i<count($list);$i++){
    $br=$list[$i]['r']; $bc=$list[$i]['c'];
    if($br>=0 && $br<$rows && $bc>=0 && $bc<$cols) $out[] = array('r'=>$br,'c'=>$bc);
  }
  return $out;
}

function isBoxClosed($br,$bc,$state){
  if(!isset($state['edges']['h'][ keyH($br,$bc) ])) return false;
  if(!isset($state['edges']['h'][ keyH($br+1,$bc) ])) return false;
  if(!isset($state['edges']['v'][ keyV($br,$bc) ])) return false;
  if(!isset($state['edges']['v'][ keyV($br,$bc+1) ])) return false;
  return true;
}

function totalBoxesClaimed($state){
  $rows=$state['rows']; $cols=$state['cols'];
  $sum = 0;
  for($r=0;$r<$rows;$r++){
    for($c=0;$c<$cols;$c++){
      if(!empty($state['boxes'][$r][$c])) $sum++;
    }
  }
  return $sum;
}

function next_present_player($state, $current){
  $order = array('A','B','C','D');
  $maxp = isset($state['max_players']) ? intval($state['max_players']) : 2;

  // constrói ordem apenas com slots até max_players e que têm token
  $present = array();
  for($i=0;$i<$maxp;$i++){
    $l = $order[$i];
    if(!empty($state['players'][$l])) $present[] = $l;
  }
  if(empty($present)) return $current;

  // encontra próximo na ordem cíclica
  $idx = array_search($current, $present, true);
  if($idx === false) return $present[0];
  $idx = ($idx + 1) % count($present);
  return $present[$idx];
}

function action_move($room, $token, $o, $r, $c){
  $state = file_read_state($room);
  if(!$state) return array('success'=>false, 'error'=>'Sala não encontrada');

  // autenticação
  $who = null;
  foreach(array('A','B','C','D') as $l){
    if($token === (isset($state['players'][$l])?$state['players'][$l]:'')){ $who=$l; break; }
  }
  if(!$who) return array('success'=>false, 'error'=>'Jogador não reconhecido', 'state'=>$state);

  if($state['finished']) return array('success'=>false, 'error'=>'Jogo finalizado', 'state'=>$state);
  if($who !== $state['current']) return array('success'=>false, 'error'=>'Não é sua vez', 'state'=>$state);

  $rows = $state['rows']; $cols = $state['cols'];
  if($o!=='h' && $o!=='v') return array('success'=>false, 'error'=>'Orientação inválida', 'state'=>$state);
  if(!insideEdge($o,$r,$c,$rows,$cols)) return array('success'=>false, 'error'=>'Aresta fora do tabuleiro', 'state'=>$state);

  // já marcada?
  if($o==='h'){
    $k = keyH($r,$c);
    if(isset($state['edges']['h'][$k])) return array('success'=>false, 'error'=>'Aresta já marcada', 'state'=>$state);
    $state['edges']['h'][$k] = $who; // salva dono
  }else{
    $k = keyV($r,$c);
    if(isset($state['edges']['v'][$k])) return array('success'=>false, 'error'=>'Aresta já marcada', 'state'=>$state);
    $state['edges']['v'][$k] = $who; // salva dono
  }

  // caixas fechadas
  $touched = edgeBoxes($o,$r,$c,$rows,$cols);
  $made = 0;
  for($i=0;$i<count($touched);$i++){
    $br = $touched[$i]['r']; $bc = $touched[$i]['c'];
    if(!$state['boxes'][$br][$bc] && isBoxClosed($br,$bc,$state)){
      $state['boxes'][$br][$bc] = $who;
      $made++;
    }
  }
  if($made>0){
    $state['score'][$who] += $made; // mantém a vez
  }else{
    // troca a vez para o próximo jogador presente
    $state['current'] = next_present_player($state, $state['current']);
  }

  if(totalBoxesClaimed($state) >= $rows*$cols){
    $state['finished'] = true;
  }

  $state['version']++;
  $state['updated_at'] = time();
  file_write_state($room, $state);

  return array('success'=>true, 'state'=>$state);
}

function action_restart($room, $token){
  $state = file_read_state($room);
  if(!$state) return array('success'=>false, 'error'=>'Sala não encontrada');

  // qualquer jogador conectado pode reiniciar
  $ok=false;
  foreach(array('A','B','C','D') as $l){
    if($token === (isset($state['players'][$l])?$state['players'][$l]:'')){ $ok=true; break; }
  }
  if(!$ok) return array('success'=>false, 'error'=>'Sem permissão', 'state'=>$state);

  $rows=$state['rows']; $cols=$state['cols']; $maxp=$state['max_players'];
  $players = $state['players']; // preserva tokens
  $state = new_state($rows,$cols,$maxp);
  $state['players'] = $players;
  $state['version']++;
  $state['updated_at'] = time();

  file_write_state($room, $state);
  return array('success'=>true, 'state'=>$state);
}
