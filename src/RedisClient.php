<?php
/**
 * Redis 客户端封装类
 * 支持多种连接方式：Redis 扩展、Predis 库
 * 提供房间状态管理的所有 Redis 操作
 */

class RedisClient
{
    private static $instance = null;
    private $client = null;
    private $connectionType = null;
    private $host = '127.0.0.1';
    private $port = 6379;
    private $password = '';

    private function __construct()
    {
        // 尝试从环境变量读取 Redis 配置
        $this->host = getenv('REDIS_HOST') ?: $this->host;
        $this->port = (int)(getenv('REDIS_PORT') ?: $this->port);
        $this->password = getenv('REDIS_PASSWORD') ?: '';

        $this->connect();
    }

    /**
     * 连接到 Redis（自动选择最合适的方式）
     */
    private function connect()
    {
        // 优先使用 Redis 扩展
        if (extension_loaded('redis')) {
            $this->connectUsingExtension();
            return;
        }

        // 其次尝试 Predis
        if (class_exists('Predis\Client')) {
            $this->connectUsingPredis();
            return;
        }

        // 都没有则错误
        $this->connectionType = 'none';
        die('ERROR: No Redis client available. Install either PHP Redis extension or run: composer require predis/predis');
    }

    /**
     * 使用 Redis 扩展连接
     */
    private function connectUsingExtension()
    {
        try {
            $redis = new \Redis();
            $redis->connect($this->host, $this->port, 0);
            if ($this->password) {
                $redis->auth($this->password);
            }
            $redis->ping();
            $this->client = $redis;
            $this->connectionType = 'extension';
        } catch (\Exception $e) {
            die("ERROR: Redis Extension connection failed: " . $e->getMessage());
        }
    }

    /**
     * 使用 Predis 库连接
     */
    private function connectUsingPredis()
    {
        try {
            $options = [];
            if ($this->password) {
                $options['password'] = $this->password;
            }
            
            $predis = new \Predis\Client(
                "tcp://{$this->host}:{$this->port}",
                $options
            );
            $predis->connect();
            $predis->ping();
            $this->client = $predis;
            $this->connectionType = 'predis';
        } catch (\Exception $e) {
            die("ERROR: Predis connection failed: " . $e->getMessage());
        }
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
     * 获取原生 Redis 客户端
     */
    public function getRedis()
    {
        return $this->client;
    }

    // ==================== 房间状态 ====================

    public function getRoomStatus(string $roomId): string
    {
        $status = $this->client->get("room:{$roomId}:status");
        return $status ?: 'waiting';
    }

    public function setRoomStatus(string $roomId, string $status): bool
    {
        return (bool)$this->client->set("room:{$roomId}:status", $status);
    }

    // ==================== 玩家管理 ====================

    public function getPlayers(string $roomId): array
    {
        $data = $this->client->hGetAll("room:{$roomId}:players");
        $players = [];
        if (is_array($data)) {
            foreach ($data as $seat => $json) {
                $players[$seat] = json_decode($json, true);
            }
        }
        return $players;
    }

    public function getPlayer(string $roomId, int $seat): ?array
    {
        $json = $this->client->hGet("room:{$roomId}:players", $seat);
        return $json ? json_decode($json, true) : null;
    }

    public function setPlayer(string $roomId, int $seat, array $playerData): bool
    {
        return (bool)$this->client->hSet(
            "room:{$roomId}:players",
            $seat,
            json_encode($playerData, JSON_UNESCAPED_UNICODE)
        );
    }

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

    public function getSeatByName(string $roomId, string $name): ?int
    {
        $seat = $this->client->hGet("room:{$roomId}:name_map", $name);
        return $seat !== false ? (int)$seat : null;
    }

    public function setNameMap(string $roomId, string $name, int $seat): bool
    {
        return (bool)$this->client->hSet("room:{$roomId}:name_map", $name, $seat);
    }

    public function getUsedSeats(string $roomId): array
    {
        $nameMap = $this->client->hGetAll("room:{$roomId}:name_map");
        $seats = [];
        if (is_array($nameMap)) {
            foreach ($nameMap as $name => $seat) {
                $seats[] = (int)$seat;
            }
        }
        return $seats;
    }

    // ==================== 发言人 ====================

    public function getSpeaker(string $roomId): ?int
    {
        $speaker = $this->client->get("room:{$roomId}:speaker");
        return $speaker !== false ? (int)$speaker : null;
    }

    public function setSpeaker(string $roomId, int $seat): bool
    {
        return (bool)$this->client->set("room:{$roomId}:speaker", $seat);
    }

    // ==================== 投票 ====================

    public function clearVotes(string $roomId): void
    {
        $this->client->del("room:{$roomId}:votes");
        $this->client->del("room:{$roomId}:voted");
    }

    public function vote(string $roomId, string $voterName, int $targetSeat): bool
    {
        // 检查是否已投票
        if ($this->client->sIsMember("room:{$roomId}:voted", $voterName)) {
            return false;
        }
        // 记录投票
        $this->client->hIncrBy("room:{$roomId}:votes", $targetSeat, 1);
        $this->client->sAdd("room:{$roomId}:voted", $voterName);
        return true;
    }

    public function getVotes(string $roomId): array
    {
        $votes = $this->client->hGetAll("room:{$roomId}:votes");
        $result = [];
        if (is_array($votes)) {
            foreach ($votes as $seat => $count) {
                $result[(int)$seat] = (int)$count;
            }
        }
        return $result;
    }

    public function hasVoted(string $roomId, string $name): bool
    {
        return (bool)$this->client->sIsMember("room:{$roomId}:voted", $name);
    }

    // ==================== 聊天 ====================

    public function addChat(string $roomId, array $message): void
    {
        $this->client->lPush("room:{$roomId}:chat", json_encode($message, JSON_UNESCAPED_UNICODE));
        $this->client->lTrim("room:{$roomId}:chat", 0, 99);
    }

    public function getChats(string $roomId, int $count = 50): array
    {
        $chats = $this->client->lRange("room:{$roomId}:chat", 0, $count - 1);
        $result = [];
        if (is_array($chats)) {
            foreach ($chats as $json) {
                $decoded = json_decode($json, true);
                if ($decoded) {
                    $result[] = $decoded;
                }
            }
        }
        return $result;
    }

    // ==================== 房间清理 ====================

    public function clearRoom(string $roomId): void
    {
        $keys = $this->client->keys("room:{$roomId}:*");
        if (!empty($keys)) {
            $this->client->del($keys);
        }
    }
}

