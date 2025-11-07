<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$dataDir = 'data/rooms';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

function json_out($arr, int $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function room_path(string $code): string {
    global $dataDir;
    return $dataDir . '/' . $code . '.json';
}

function load_room(string $code): ?array {
    $path = room_path($code);
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if ($raw === false) return null;
    $room = json_decode($raw, true);
    return is_array($room) ? $room : null;
}

function save_room(string $code, array $room): void {
    $path = room_path($code);
    $room['updatedAt'] = time();
    file_put_contents($path, json_encode($room, JSON_UNESCAPED_UNICODE));
}

function create_code(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i=0; $i<5; $i++) $code .= $alphabet[random_int(0, strlen($alphabet)-1)];
    return $code;
}

function check_winner(array $board) {
    $wins = [
        [0,1,2],[3,4,5],[6,7,8],
        [0,3,6],[1,4,7],[2,5,8],
        [0,4,8],[2,4,6]
    ];
    foreach ($wins as $w) {
        [$a,$b,$c] = $w;
        if ($board[$a] && $board[$a] === $board[$b] && $board[$b] === $board[$c]) {
            return $board[$a];
        }
    }
    if (count(array_filter($board)) === 9) return 'draw';
    return null;
}

$action = $_POST['action'] ?? '';
$clientId = $_POST['clientId'] ?? '';
if (!$action || !$clientId) json_out(['error' => 'Parâmetros ausentes (action/clientId).'], 400);

switch ($action) {
    case 'create_room':
        do { $code = create_code(); } while (file_exists(room_path($code)));
        $room = [
            'board' => array_fill(0, 9, null),
            'players' => [ $clientId => 'X' ],
            'turn' => 'X',
            'winner' => null,
            'createdAt' => time(),
            'updatedAt' => time()
        ];
        save_room($code, $room);
        json_out(['room' => $code, 'mark' => 'X']);
        break;

    case 'join_room':
        $code = strtoupper(trim($_POST['room'] ?? ''));
        if (!$code) json_out(['error' => 'Código da sala ausente.'], 400);
        $room = load_room($code);
        if (!$room) json_out(['error' => 'Sala inexistente.'], 404);
        $marks = array_values($room['players']);
        if (!isset($room['players'][$clientId])) {
            if (in_array('X', $marks) && in_array('O', $marks)) {
                json_out(['error' => 'Sala cheia.'], 403);
            }
            $mark = in_array('X', $marks) ? 'O' : 'X';
            $room['players'][$clientId] = $mark;
            save_room($code, $room);
        } else {
            $mark = $room['players'][$clientId];
        }
        json_out(['room' => $code, 'mark' => $mark]);
        break;

    case 'state':
        $code = strtoupper(trim($_POST['room'] ?? ''));
        if (!$code) json_out(['error' => 'Código da sala ausente.'], 400);
        $room = load_room($code);
        if (!$room) json_out(['error' => 'Sala inexistente.'], 404);
        json_out(['state' => $room]);
        break;

    case 'play':
        $code = strtoupper(trim($_POST['room'] ?? ''));
        $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
        if (!$code || $index < 0 || $index > 8) json_out(['error' => 'Parâmetros inválidos.'], 400);
        $room = load_room($code);
        if (!$room) json_out(['error' => 'Sala inexistente.'], 404);

        $mark = $room['players'][$clientId] ?? null;
        if (!$mark) json_out(['error' => 'Você não é jogador desta sala.'], 403);
        if ($room['winner']) json_out(['error' => 'Partida encerrada.'], 403);
        if ($room['turn'] !== $mark) json_out(['error' => 'Não é sua vez.'], 403);
        if ($room['board'][$index] !== null) json_out(['error' => 'Jogada inválida.'], 400);

        $room['board'][$index] = $mark;
        $res = check_winner($room['board']);
        if ($res) {
            $room['winner'] = $res;
        } else {
            $room['turn'] = $room['turn'] === 'X' ? 'O' : 'X';
        }
        save_room($code, $room);
        json_out(['ok' => true]);
        break;

    case 'reset':
        $code = strtoupper(trim($_POST['room'] ?? ''));
        if (!$code) json_out(['error' => 'Código da sala ausente.'], 400);
        $room = load_room($code);
        if (!$room) json_out(['error' => 'Sala inexistente.'], 404);
        $room['board'] = array_fill(0, 9, null);
        $room['turn'] = 'X';
        $room['winner'] = null;
        save_room($code, $room);
        json_out(['ok' => true]);
        break;

    default:
        json_out(['error' => 'Ação inválida.'], 400);
}
