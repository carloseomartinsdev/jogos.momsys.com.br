<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$roomsDir = __DIR__ . '/rooms/';
if (!is_dir($roomsDir)) {
    mkdir($roomsDir, 0777, true);
}

function generateRoomId() {
    return strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
}

function getRoomFile($roomId) {
    global $roomsDir;
    return $roomsDir . $roomId . '.json';
}

function loadRoom($roomId) {
    $file = getRoomFile($roomId);
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function saveRoom($roomId, $data) {
    $file = getRoomFile($roomId);
    file_put_contents($file, json_encode($data));
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'create_room':
                    $roomId = generateRoomId();
                    $room = [
                        'id' => $roomId,
                        'players' => [],
                        'maxPlayers' => $input['maxPlayers'] ?? 4,
                        'gameState' => [
                            'started' => false,
                            'currentPlayer' => 1,
                            'players' => [],
                            'properties' => [],
                            'gameData' => []
                        ],
                        'created' => time()
                    ];
                    saveRoom($roomId, $room);
                    echo json_encode(['success' => true, 'roomId' => $roomId]);
                    break;

                case 'join_room':
                    $roomId = $input['roomId'];
                    $playerName = $input['playerName'];
                    $room = loadRoom($roomId);
                    
                    if (!$room) {
                        echo json_encode(['success' => false, 'error' => 'Sala não encontrada']);
                        break;
                    }
                    
                    if (count($room['players']) >= $room['maxPlayers']) {
                        echo json_encode(['success' => false, 'error' => 'Sala lotada']);
                        break;
                    }
                    
                    $playerId = count($room['players']) + 1;
                    $room['players'][] = [
                        'id' => $playerId,
                        'name' => $playerName,
                        'joined' => time()
                    ];
                    
                    saveRoom($roomId, $room);
                    echo json_encode(['success' => true, 'playerId' => $playerId, 'room' => $room]);
                    break;

                case 'update_game':
                    $roomId = $input['roomId'];
                    $gameState = $input['gameState'];
                    $room = loadRoom($roomId);
                    
                    if ($room) {
                        $room['gameState'] = $gameState;
                        saveRoom($roomId, $room);
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Sala não encontrada']);
                    }
                    break;
            }
        }
        break;

    case 'GET':
        if (isset($_GET['roomId'])) {
            $room = loadRoom($_GET['roomId']);
            if ($room) {
                echo json_encode(['success' => true, 'room' => $room]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Sala não encontrada']);
            }
        }
        break;
}
?>