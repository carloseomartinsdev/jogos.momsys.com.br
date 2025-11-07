<?php
// api.php — backend para Damas Online (versão com diagnósticos)
// Requisitos: PHP 7.4+
// IMPORTANTE: criar a pasta rooms/ com permissão de escrita pelo usuário do servidor (www-data, apache, etc.)

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// DEBUG opcional (comente em produção)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// ====== CONFIG ======
define('ROOMS_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'rooms'); // ajuste se quiser caminho absoluto
define('ROOMS_MODE', 0777); // Linux: se necessário, 0777 para testes
// ====================

$action = $_POST['action'] ?? '';

try {
  // Garantir pasta rooms
  ensureRoomsDir();

  switch ($action) {
    case 'create':
      respond(okCreate());
    case 'join':
      $room = strtoupper(safeText($_POST['room'] ?? ''));
      if ($room === '') throw new Exception('room ausente');
      respond(okJoin($room));
    case 'poll':
      $room = strtoupper(safeText($_POST['room'] ?? ''));
      $version = intval($_POST['version'] ?? 0);
      if ($room === '') throw new Exception('room ausente');
      respond(okPoll($room, $version));
    case 'move':
      $room  = strtoupper(safeText($_POST['room'] ?? ''));
      $token = $_POST['token'] ?? '';
      $move  = json_decode($_POST['move'] ?? 'null', true);
      if ($room === '' || $token === '' || !is_array($move)) {
        throw new Exception('parâmetros inválidos');
      }
      respond(okMove($room, $token, $move));
    case 'restart':
      $room  = strtoupper(safeText($_POST['room'] ?? ''));
      $token = $_POST['token'] ?? '';
      if ($room === '' || $token === '') throw new Exception('parâmetros inválidos');
      respond(okRestart($room, $token));
    default:
      throw new Exception('ação inválida');
  }
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}

// ===== Helpers base =====
function respond(array $arr): void { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function safeText(string $s): string { return preg_replace('/[^A-Za-z0-9_-]/','', $s); }
function randCode(int $n=6): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $s=''; for($i=0;$i<$n;$i++){ $s .= $chars[random_int(0, strlen($chars)-1)]; }
  return $s;
}
function randToken(): string { return bin2hex(random_bytes(16)); }

function ensureRoomsDir(): void {
  if (!is_dir(ROOMS_DIR)) {
    @mkdir(ROOMS_DIR, ROOMS_MODE, true);
  }
  if (!is_dir(ROOMS_DIR)) {
    throw new Exception('Pasta rooms não existe e não pôde ser criada em: ' . ROOMS_DIR);
  }
  if (!is_writable(ROOMS_DIR)) {
    throw new Exception('Pasta rooms não possui permissão de escrita: ' . ROOMS_DIR);
  }
}

function pathRoom(string $room): string { return ROOMS_DIR . DIRECTORY_SEPARATOR . $room . '.json'; }

function readState(string $room): ?array {
  $file = pathRoom($room);
  if (!file_exists($file)) return null;
  $fp = fopen($file, 'r');
  if (!$fp) return null;
  @flock($fp, LOCK_SH);
  $txt = stream_get_contents($fp);
  @flock($fp, LOCK_UN);
  fclose($fp);
  $data = json_decode($txt, true);
  return is_array($data) ? $data : null;
}

function writeState(string $room, array $state): void {
  $file = pathRoom($room);
  $fp = fopen($file, 'c+');
  if (!$fp) throw new Exception('Falha ao abrir arquivo de estado');
  @flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE));
  fflush($fp);
  @flock($fp, LOCK_UN);
  fclose($fp);
}

// ===== Lógica do jogo =====
function initialBoard(): array {
  $ROWS=8; $COLS=8;
  $b = array_fill(0,$ROWS, array_fill(0,$COLS, null));
  for($r=0;$r<3;$r++){
    for($c=0;$c<$COLS;$c++){
      if( (($r+$c)%2)==1 ) $b[$r][$c] = 'b';
    }
  }
  for($r=$ROWS-3;$r<$ROWS;$r++){
    for($c=0;$c<$COLS;$c++){
      if( (($r+$c)%2)==1 ) $b[$r][$c] = 'r';
    }
  }
  return $b;
}
function newStateTemplate(): array {
  return [
    'board' => initialBoard(),
    'current' => 'r',
    'scores' => ['r'=>12,'b'=>12],
    'version' => 1,
    'updated_at' => time(),
    'players' => ['r'=>null,'b'=>null]
  ];
}
function isKingPiece($p): bool { return $p==='R' || $p==='B'; }
function ownerOf($p): ?string { if(!$p) return null; return strtolower($p)==='r'?'r':'b'; }
function isDark(int $r,int $c): bool { return (($r+$c)%2)==1; }
function inside(int $r,int $c): bool { return $r>=0 && $r<8 && $c>=0 && $c<8; }
function dirsFor($p): array {
  if(isKingPiece($p)) return [[-1,-1],[-1,1],[1,-1],[1,1]];
  return (ownerOf($p)==='r') ? [[-1,-1],[-1,1]] : [[1,-1],[1,1]];
}
function legalMovesFrom(int $r,int $c,array $board,bool $mustCaptureOnly): array {
  $p = $board[$r][$c] ?? null;
  if(!$p) return [];
  $me = ownerOf($p);
  $d = dirsFor($p);
  $list = [];
  // Capturas
  foreach($d as $v){
    [$dr,$dc] = $v;
    $r1=$r+$dr; $c1=$c+$dc;
    $r2=$r+2*$dr; $c2=$c+2*$dc;
    if(inside($r2,$c2) && !empty($board[$r1][$c1]) && ownerOf($board[$r1][$c1]) !== $me && empty($board[$r2][$c2])){
      $list[] = ['tr'=>$r2, 'tc'=>$c2, 'capture'=>['r'=>$r1,'c'=>$c1]];
    }
  }
  if(count($list)>0) return $list;
  // Passo simples
  if(!$mustCaptureOnly){
    foreach($d as $v){
      [$dr,$dc] = $v;
      $r1=$r+$dr; $c1=$c+$dc;
      if(inside($r1,$c1) && empty($board[$r1][$c1])){
        $list[] = ['tr'=>$r1, 'tc'=>$c1];
      }
    }
  }
  return $list;
}
function playerHasAnyCapture(string $player, array $board): bool {
  for($r=0;$r<8;$r++){
    for($c=0;$c<8;$c++){
      $p = $board[$r][$c] ?? null;
      if($p && ownerOf($p)===$player){
        $ms = legalMovesFrom($r,$c,$board,false);
        foreach($ms as $m){ if(!empty($m['capture'])) return true; }
      }
    }
  }
  return false;
}

// ===== Ações =====
function okCreate(): array {
  // gera código de sala que não exista
  $tries = 0;
  do {
    $room = randCode(6);
    $file = pathRoom($room);
    $tries++;
  } while(file_exists($file) && $tries < 10);

  if (file_exists($file)) {
    throw new Exception('Não foi possível gerar código de sala único.');
  }

  $state = newStateTemplate();
  $tokenR = randToken();
  $state['players']['r'] = $tokenR;

  writeState($room, $state);

  return [
    'success'=>true,
    'room'=>$room,
    'you_color'=>'r',
    'token'=>$tokenR,
    'state'=>$state
  ];
}

function okJoin(string $room): array {
  $state = readState($room);
  if(!$state) return ['success'=>false, 'error'=>'Sala não encontrada'];
  if(!empty($state['players']['b'])){
    return ['success'=>false, 'error'=>'Sala já tem dois jogadores'];
  }
  $tokenB = randToken();
  $state['players']['b'] = $tokenB;
  $state['version']++;
  $state['updated_at'] = time();
  writeState($room, $state);
  return ['success'=>true, 'room'=>$room, 'you_color'=>'b', 'token'=>$tokenB, 'state'=>$state];
}

function okPoll(string $room, int $clientVersion): array {
  $state = readState($room);
  if(!$state) return ['success'=>false, 'error'=>'Sala não encontrada'];
  if(($state['version'] ?? 0) > $clientVersion){
    return ['success'=>true, 'update'=>true, 'state'=>$state];
  }
  return ['success'=>true, 'update'=>false];
}

function okRestart(string $room, string $token): array {
  $state = readState($room);
  if(!$state) return ['success'=>false, 'error'=>'Sala não encontrada'];

  if($token !== ($state['players']['r'] ?? '') && $token !== ($state['players']['b'] ?? '')){
    return ['success'=>false, 'er]()
