<?php
/**
 * 玩家加入房间接口
 * POST /api/join.php
 * 
 * 请求: {"room":"A001","name":"张三"}
 * 响应: {"success":true,"seat":3,"role":"卧底","word":"香蕉","isAdmin":false}
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../src/RedisClient.php';
require_once __DIR__ . '/../src/RoomConfig.php';

/**
 * 返回错误响应
 */
function errorResponse(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 返回成功响应
 */
function successResponse(array $data): void
{
    echo json_encode(array_merge([
        'success' => true
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只接受 POST 请求', 405);
}

// 解析请求体
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    errorResponse('无效的 JSON 数据');
}

$roomId = trim($input['room'] ?? '');
$name = trim($input['name'] ?? '');

// 参数校验
if (empty($roomId)) {
    errorResponse('房间号不能为空');
}
if (empty($name)) {
    errorResponse('姓名不能为空');
}
if (mb_strlen($name) > 20) {
    errorResponse('姓名过长');
}

// ① 校验房间是否存在
if (!RoomConfig::exists($roomId)) {
    errorResponse('房间不存在');
}

$redis = RedisClient::getInstance();

// ② 判断是否管理员
if (strtolower($name) === 'admin') {
    successResponse([
        'seat' => 0,
        'role' => 'admin',
        'word' => '',
        'isAdmin' => true,
        'room' => $roomId
    ]);
}

// ③ 检查是否已存在（处理刷新重连）
$existingSeat = $redis->getSeatByName($roomId, $name);
if ($existingSeat !== null) {
    // 获取座位信息
    $seatInfo = RoomConfig::getSeatInfo($roomId, $existingSeat);
    if ($seatInfo) {
        successResponse([
            'seat' => $existingSeat,
            'role' => $seatInfo['role'],
            'word' => $seatInfo['word'],
            'isAdmin' => false,
            'room' => $roomId
        ]);
    }
}

// ④ 如果游戏已开始，拒绝新玩家
$status = $redis->getRoomStatus($roomId);
if ($status !== 'waiting') {
    errorResponse('游戏已开始，无法加入');
}

// ⑤ 分配 seat（顺序找未使用的座位）
$seats = RoomConfig::getSeats($roomId);
$usedSeats = $redis->getUsedSeats($roomId);

$assignedSeat = null;
$assignedSeatInfo = null;

foreach ($seats as $seatInfo) {
    $seatNum = $seatInfo['seat'];
    if (!in_array($seatNum, $usedSeats)) {
        $assignedSeat = $seatNum;
        $assignedSeatInfo = $seatInfo;
        break;
    }
}

if ($assignedSeat === null) {
    errorResponse('房间已满');
}

// 写入 Redis
$redis->setPlayer($roomId, $assignedSeat, [
    'name' => $name,
    'alive' => 1
]);
$redis->setNameMap($roomId, $name, $assignedSeat);

// 返回成功
successResponse([
    'seat' => $assignedSeat,
    'role' => $assignedSeatInfo['role'],
    'word' => $assignedSeatInfo['word'],
    'isAdmin' => false,
    'room' => $roomId
]);
