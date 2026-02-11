<?php
/**
 * WebSocket 服务器
 * 使用 Workerman 实现实时通信
 * 
 * 启动命令: php ws_server.php start
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/RedisClient.php';
require_once __DIR__ . '/src/RoomConfig.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

// 检查 pcntl 扩展是否可用
$pcntl_disabled = !function_exists('pcntl_fork') || !function_exists('pcntl_signal');

if ($pcntl_disabled) {
    echo "警告: pcntl 函数被禁用，使用单进程模式运行\n";
    echo "如需启用多进程，请在 php.ini 中移除 disable_functions 中的 pcntl_* 函数\n";
    echo "当前模式适合开发和小规模部署\n\n";
    
    // 设置为单进程模式
    Worker::$daemonize = false;
}

// 创建 WebSocket 服务器
$ws_worker = new Worker("websocket://0.0.0.0:2346");

// 设置进程数（单进程即可，因为是小规模应用）
$ws_worker->count = 1;

// 房间连接管理: room_id => [connection_id => connection]
$roomConnections = [];

// 连接信息: connection_id => ['room' => room_id, 'name' => name, 'seat' => seat]
$connectionInfo = [];

/**
 * 广播消息到房间所有连接
 */
function broadcastToRoom(string $roomId, array $data): void
{
    global $roomConnections;
    
    $message = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    if (isset($roomConnections[$roomId])) {
        foreach ($roomConnections[$roomId] as $conn) {
            $conn->send($message);
        }
    }
}

/**
 * 获取下一个存活的座位
 */
function getNextAliveSeat(string $roomId, int $currentSeat): ?int
{
    $redis = RedisClient::getInstance();
    $players = $redis->getPlayers($roomId);
    $seats = RoomConfig::getSeats($roomId);
    
    // 获取所有座位号并排序
    $seatNumbers = array_column($seats, 'seat');
    sort($seatNumbers);
    
    // 找到当前座位在数组中的位置
    $currentIndex = array_search($currentSeat, $seatNumbers);
    if ($currentIndex === false) {
        $currentIndex = -1;
    }
    
    // 从下一个位置开始循环查找
    $total = count($seatNumbers);
    for ($i = 1; $i <= $total; $i++) {
        $nextIndex = ($currentIndex + $i) % $total;
        $nextSeat = $seatNumbers[$nextIndex];
        
        if (isset($players[$nextSeat]) && $players[$nextSeat]['alive'] == 1) {
            return $nextSeat;
        }
    }
    
    return null;
}

/**
 * 获取第一个存活的座位
 */
function getFirstAliveSeat(string $roomId): ?int
{
    $redis = RedisClient::getInstance();
    $players = $redis->getPlayers($roomId);
    $seats = RoomConfig::getSeats($roomId);
    
    // 获取所有座位号并排序
    $seatNumbers = array_column($seats, 'seat');
    sort($seatNumbers);
    
    foreach ($seatNumbers as $seat) {
        if (isset($players[$seat]) && $players[$seat]['alive'] == 1) {
            return $seat;
        }
    }
    
    return null;
}

/**
 * 广播房间状态
 */
function broadcastState(string $roomId): void
{
    $redis = RedisClient::getInstance();
    
    broadcastToRoom($roomId, [
        'type' => 'state',
        'status' => $redis->getRoomStatus($roomId),
        'speaker' => $redis->getSpeaker($roomId)
    ]);
}

/**
 * 广播玩家列表
 */
function broadcastPlayers(string $roomId): void
{
    $redis = RedisClient::getInstance();
    $players = $redis->getPlayers($roomId);
    
    $list = [];
    foreach ($players as $seat => $player) {
        $list[] = [
            'seat' => (int)$seat,
            'name' => $player['name'],
            'alive' => $player['alive']
        ];
    }
    
    // 按座位号排序
    usort($list, function($a, $b) {
        return $a['seat'] - $b['seat'];
    });
    
    broadcastToRoom($roomId, [
        'type' => 'players',
        'list' => $list
    ]);
}

/**
 * 广播投票结果
 */
function broadcastVotes(string $roomId): void
{
    $redis = RedisClient::getInstance();
    $votes = $redis->getVotes($roomId);
    
    broadcastToRoom($roomId, [
        'type' => 'votes',
        'data' => $votes
    ]);
}

/**
 * 广播聊天消息
 */
function broadcastChat(string $roomId, array $msg): void
{
    broadcastToRoom($roomId, [
        'type' => 'chat',
        'msg' => $msg
    ]);
}

// ==================== 事件处理 ====================

$ws_worker->onConnect = function(TcpConnection $connection) {
    echo "新连接: {$connection->id}\n";
};

$ws_worker->onWebSocketConnect = function(TcpConnection $connection, $http_header) {
    global $roomConnections, $connectionInfo;
    
    // 解析 URL 参数
    parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $params);
    
    $roomId = $params['room'] ?? '';
    $name = $params['name'] ?? '';
    
    if (empty($roomId) || empty($name)) {
        $connection->close();
        return;
    }
    
    // 验证房间是否存在
    if (!RoomConfig::exists($roomId)) {
        $connection->close();
        return;
    }
    
    $redis = RedisClient::getInstance();
    
    // 判断是否管理员
    $isAdmin = (strtolower($name) === 'admin');
    $seat = 0;
    
    if (!$isAdmin) {
        // 获取玩家座位
        $seat = $redis->getSeatByName($roomId, $name);
        if ($seat === null) {
            $connection->close();
            return;
        }
    }
    
    // 保存连接信息
    $connectionInfo[$connection->id] = [
        'room' => $roomId,
        'name' => $name,
        'seat' => $seat,
        'isAdmin' => $isAdmin
    ];
    
    // 添加到房间连接列表
    if (!isset($roomConnections[$roomId])) {
        $roomConnections[$roomId] = [];
    }
    $roomConnections[$roomId][$connection->id] = $connection;
    
    echo "玩家加入: $name (房间: $roomId, 座位: $seat)\n";
    
    // 发送当前状态给新连接
    $connection->send(json_encode([
        'type' => 'state',
        'status' => $redis->getRoomStatus($roomId),
        'speaker' => $redis->getSpeaker($roomId)
    ], JSON_UNESCAPED_UNICODE));
    
    // 发送玩家列表
    $players = $redis->getPlayers($roomId);
    $list = [];
    foreach ($players as $s => $player) {
        $list[] = [
            'seat' => (int)$s,
            'name' => $player['name'],
            'alive' => $player['alive']
        ];
    }
    usort($list, function($a, $b) {
        return $a['seat'] - $b['seat'];
    });
    $connection->send(json_encode([
        'type' => 'players',
        'list' => $list
    ], JSON_UNESCAPED_UNICODE));
    
    // 发送聊天记录
    $chats = $redis->getChats($roomId, 50);
    foreach (array_reverse($chats) as $chat) {
        $connection->send(json_encode([
            'type' => 'chat',
            'msg' => $chat
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 广播玩家列表更新给其他人
    broadcastPlayers($roomId);
};

$ws_worker->onMessage = function(TcpConnection $connection, $data) {
    global $connectionInfo;
    
    $info = $connectionInfo[$connection->id] ?? null;
    if (!$info) {
        return;
    }
    
    $roomId = $info['room'];
    $name = $info['name'];
    $seat = $info['seat'];
    $isAdmin = $info['isAdmin'];
    
    $msg = json_decode($data, true);
    if (!$msg || !isset($msg['action'])) {
        return;
    }
    
    $redis = RedisClient::getInstance();
    $action = $msg['action'];
    
    switch ($action) {
        // 1️⃣ 聊天
        case 'chat':
            $text = trim($msg['msg'] ?? '');
            if (empty($text) || mb_strlen($text) > 500) {
                break;
            }
            
            $chatMsg = [
                'seat' => $seat,
                'name' => $name,
                'msg' => $text,
                'time' => date('H:i:s')
            ];
            
            $redis->addChat($roomId, $chatMsg);
            broadcastChat($roomId, $chatMsg);
            break;
            
        // 2️⃣ 管理员开始游戏
        case 'start':
            if (!$isAdmin) break;
            
            $status = $redis->getRoomStatus($roomId);
            if ($status !== 'waiting') break;
            
            // 设置状态为已开始
            $redis->setRoomStatus($roomId, 'started');
            
            // 设置第一个发言人
            $firstSeat = getFirstAliveSeat($roomId);
            if ($firstSeat !== null) {
                $redis->setSpeaker($roomId, $firstSeat);
            }
            
            broadcastState($roomId);
            
            // 系统消息
            $sysMsg = [
                'seat' => 0,
                'name' => '系统',
                'msg' => '游戏开始！请各位玩家依次发言。',
                'time' => date('H:i:s')
            ];
            $redis->addChat($roomId, $sysMsg);
            broadcastChat($roomId, $sysMsg);
            break;
            
        // 3️⃣ 下一位发言
        case 'next':
            if (!$isAdmin) break;
            
            $currentSpeaker = $redis->getSpeaker($roomId);
            if ($currentSpeaker === null) break;
            
            $nextSeat = getNextAliveSeat($roomId, $currentSpeaker);
            if ($nextSeat !== null) {
                $redis->setSpeaker($roomId, $nextSeat);
                broadcastState($roomId);
                
                // 获取下一位玩家名字
                $player = $redis->getPlayer($roomId, $nextSeat);
                $playerName = $player ? $player['name'] : "座位$nextSeat";
                
                $sysMsg = [
                    'seat' => 0,
                    'name' => '系统',
                    'msg' => "请 {$nextSeat}号 {$playerName} 发言",
                    'time' => date('H:i:s')
                ];
                $redis->addChat($roomId, $sysMsg);
                broadcastChat($roomId, $sysMsg);
            }
            break;
            
        // 4️⃣ 开启投票
        case 'vote_start':
            if (!$isAdmin) break;
            
            $redis->setRoomStatus($roomId, 'voting');
            $redis->clearVotes($roomId);
            
            broadcastState($roomId);
            
            $sysMsg = [
                'seat' => 0,
                'name' => '系统',
                'msg' => '投票开始！请选择你认为的卧底。',
                'time' => date('H:i:s')
            ];
            $redis->addChat($roomId, $sysMsg);
            broadcastChat($roomId, $sysMsg);
            break;
            
        // 5️⃣ 玩家投票
        case 'vote':
            $target = intval($msg['target'] ?? 0);
            if ($target <= 0) break;
            
            // 管理员不能投票
            if ($isAdmin) break;
            
            // 检查房间状态
            $status = $redis->getRoomStatus($roomId);
            if ($status !== 'voting') break;
            
            // 检查投票目标是否存活
            $targetPlayer = $redis->getPlayer($roomId, $target);
            if (!$targetPlayer || $targetPlayer['alive'] != 1) break;
            
            // 检查自己是否存活
            $myPlayer = $redis->getPlayer($roomId, $seat);
            if (!$myPlayer || $myPlayer['alive'] != 1) break;
            
            // 执行投票
            if ($redis->vote($roomId, $name, $target)) {
                broadcastVotes($roomId);
                
                $sysMsg = [
                    'seat' => 0,
                    'name' => '系统',
                    'msg' => "{$seat}号 {$name} 已投票",
                    'time' => date('H:i:s')
                ];
                $redis->addChat($roomId, $sysMsg);
                broadcastChat($roomId, $sysMsg);
            }
            break;
            
        // 6️⃣ 管理员淘汰玩家
        case 'kill':
            if (!$isAdmin) break;
            
            $targetSeat = intval($msg['seat'] ?? 0);
            if ($targetSeat <= 0) break;
            
            $targetPlayer = $redis->getPlayer($roomId, $targetSeat);
            if (!$targetPlayer) break;
            
            // 设置为淘汰
            $redis->setPlayerAlive($roomId, $targetSeat, 0);
            
            // 广播玩家列表更新
            broadcastPlayers($roomId);
            
            // 恢复游戏状态
            $redis->setRoomStatus($roomId, 'started');
            broadcastState($roomId);
            
            $sysMsg = [
                'seat' => 0,
                'name' => '系统',
                'msg' => "{$targetSeat}号 {$targetPlayer['name']} 被淘汰！",
                'time' => date('H:i:s')
            ];
            $redis->addChat($roomId, $sysMsg);
            broadcastChat($roomId, $sysMsg);
            break;
            
        // 7️⃣ 管理员结束本局
        case 'end':
            if (!$isAdmin) break;
            
            $redis->setRoomStatus($roomId, 'ended');
            broadcastState($roomId);
            
            $sysMsg = [
                'seat' => 0,
                'name' => '系统',
                'msg' => '本局游戏结束！',
                'time' => date('H:i:s')
            ];
            $redis->addChat($roomId, $sysMsg);
            broadcastChat($roomId, $sysMsg);
            break;
    }
};

$ws_worker->onClose = function(TcpConnection $connection) {
    global $roomConnections, $connectionInfo;
    
    $info = $connectionInfo[$connection->id] ?? null;
    if ($info) {
        $roomId = $info['room'];
        
        // 从房间连接列表移除
        if (isset($roomConnections[$roomId][$connection->id])) {
            unset($roomConnections[$roomId][$connection->id]);
        }
        
        // 清理空房间
        if (empty($roomConnections[$roomId])) {
            unset($roomConnections[$roomId]);
        }
        
        echo "玩家离开: {$info['name']} (房间: $roomId)\n";
    }
    
    // 清理连接信息
    if (isset($connectionInfo[$connection->id])) {
        unset($connectionInfo[$connection->id]);
    }
};

$ws_worker->onError = function(TcpConnection $connection, $code, $msg) {
    echo "错误: $code - $msg\n";
};

// 启动 Worker
Worker::runAll();
