<?php
/**
 * 房间配置读取类
 */

class RoomConfig
{
    private static $config = null;
    private static $configFile = __DIR__ . '/../config/rooms.json';

    /**
     * 加载配置
     */
    private static function load(): array
    {
        if (self::$config === null) {
            $json = file_get_contents(self::$configFile);
            self::$config = json_decode($json, true) ?: [];
        }
        return self::$config;
    }

    /**
     * 检查房间是否存在
     */
    public static function exists(string $roomId): bool
    {
        $config = self::load();
        return isset($config[$roomId]);
    }

    /**
     * 获取房间配置
     */
    public static function get(string $roomId): ?array
    {
        $config = self::load();
        return $config[$roomId] ?? null;
    }

    /**
     * 获取房间座位列表
     */
    public static function getSeats(string $roomId): array
    {
        $room = self::get($roomId);
        return $room['seats'] ?? [];
    }

    /**
     * 根据座位号获取座位信息
     */
    public static function getSeatInfo(string $roomId, int $seat): ?array
    {
        $seats = self::getSeats($roomId);
        foreach ($seats as $seatInfo) {
            if ($seatInfo['seat'] === $seat) {
                return $seatInfo;
            }
        }
        return null;
    }

    /**
     * 获取所有房间ID列表
     */
    public static function getAllRoomIds(): array
    {
        $config = self::load();
        return array_keys($config);
    }
}
