<?php
/**
 * Redis 客户端封装类
 * 提供房间状态管理的所有 Redis 操作
 */

class RedisClient
{
    private static $instance = null;
    private $redis;

    private function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): RedisClient
    {
        if (self::$instance === null) {
            self::$instance = new RedisClient();
        }
        return self::$instance;
    }

    /**
     * 获取原生 Redis 实例
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    // ==================== 房间状态 ====================

    /**
     * 获取房间状态
     */
    public function getRoomStatus(string $roomId): string
    {
        return $this->redis->get("room:{$roomId}:status") ?: 'waiting';
    }

    /**
     * 设置房间状态
     */
    public function setRoomStatus(string $roomId, string $status): bool
    {
        return $this->redis->set("room:{$roomId}:status", $status);
    }

    // ==================== 玩家管理 ====================

    /**
     * 获取所有玩家
     */
    public function getPlayers(string $roomId): array
    {
        $data = $this->redis->hGetAll("room:{$roomId}:players");
        $players = [];
        foreach ($data as $seat => $json) {
            $players[$seat] = json_decode($json, true);
        }
        return $players;
    }

    /**
     * 获取单个玩家
     */
    public function getPlayer(string $roomId, int $seat): ?array
    {
        $json = $this->redis->hGet("room:{$roomId}:players", $seat);
        return $json ? json_decode($json, true) : null;
    }

    /**
     * 设置玩家
     */
    public function setPlayer(string $roomId, int $seat, array $playerData): bool
    {
        return $this->redis->hSet("room:{$roomId}:players", $seat, json_encode($playerData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 更新玩家存活状态
     */
    public function setPlayerAlive(string $roomId, int $seat, int $alive): bool
    {
        $player = $this->getPlayer($roomId, $seat);
        if ($player) {
            $player['alive'] = $alive;
            return $this->setPlayer($roomId, $seat, $player);
        }
        return false;
    }

    // ==================== 姓名映射 ====================

    /**
     * 根据姓名获取座位号
     */
    public function getSeatByName(string $roomId, string $name): ?int
    {
        $seat = $this->redis->hGet("room:{$roomId}:name_map", $name);
        return $seat !== false ? (int)$seat : null;
    }

    /**
     * 设置姓名映射
     */
    public function setNameMap(string $roomId, string $name, int $seat): bool
    {
        return $this->redis->hSet("room:{$roomId}:name_map", $name, $seat);
    }

    /**
     * 获取已使用的座位列表
     */
    public function getUsedSeats(string $roomId): array
    {
        $nameMap = $this->redis->hGetAll("room:{$roomId}:name_map");
        return array_values(array_map('intval', $nameMap));
    }

    // ==================== 发言人 ====================

    /**
     * 获取当前发言人
     */
    public function getSpeaker(string $roomId): ?int
    {
        $speaker = $this->redis->get("room:{$roomId}:speaker");
        return $speaker !== false ? (int)$speaker : null;
    }

    /**
     * 设置当前发言人
     */
    public function setSpeaker(string $roomId, int $seat): bool
    {
        return $this->redis->set("room:{$roomId}:speaker", $seat);
    }

    // ==================== 投票 ====================

    /**
     * 清空投票
     */
    public function clearVotes(string $roomId): void
    {
        $this->redis->del("room:{$roomId}:votes");
        $this->redis->del("room:{$roomId}:voted");
    }

    /**
     * 投票
     */
    public function vote(string $roomId, string $voterName, int $targetSeat): bool
    {
        // 检查是否已投票
        if ($this->redis->sIsMember("room:{$roomId}:voted", $voterName)) {
            return false;
        }
        // 记录投票
        $this->redis->hIncrBy("room:{$roomId}:votes", $targetSeat, 1);
        $this->redis->sAdd("room:{$roomId}:voted", $voterName);
        return true;
    }

    /**
     * 获取投票结果
     */
    public function getVotes(string $roomId): array
    {
        $votes = $this->redis->hGetAll("room:{$roomId}:votes");
        $result = [];
        foreach ($votes as $seat => $count) {
            $result[(int)$seat] = (int)$count;
        }
        return $result;
    }

    /**
     * 检查是否已投票
     */
    public function hasVoted(string $roomId, string $name): bool
    {
        return $this->redis->sIsMember("room:{$roomId}:voted", $name);
    }

    // ==================== 聊天 ====================

    /**
     * 添加聊天消息
     */
    public function addChat(string $roomId, array $message): void
    {
        $this->redis->lPush("room:{$roomId}:chat", json_encode($message, JSON_UNESCAPED_UNICODE));
        $this->redis->lTrim("room:{$roomId}:chat", 0, 99);
    }

    /**
     * 获取聊天记录
     */
    public function getChats(string $roomId, int $count = 50): array
    {
        $chats = $this->redis->lRange("room:{$roomId}:chat", 0, $count - 1);
        return array_map(function($json) {
            return json_decode($json, true);
        }, $chats);
    }

    // ==================== 房间清理 ====================

    /**
     * 清理房间所有数据
     */
    public function clearRoom(string $roomId): void
    {
        $keys = $this->redis->keys("room:{$roomId}:*");
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }
}
