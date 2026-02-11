#!/bin/bash
# ================================
#   谁是卧底 - 游戏服务停止脚本
#   适用于 Ubuntu + 宝塔面板
# ================================

cd "$(dirname "$0")"

echo "================================"
echo "  谁是卧底 - 停止服务"
echo "================================"
echo ""

# 使用宝塔的 PHP 路径
PHP_BIN="/www/server/php/74/bin/php"

if [ ! -f "$PHP_BIN" ]; then
    if [ -f "/usr/bin/php" ]; then
        PHP_BIN="/usr/bin/php"
    elif [ -f "/usr/local/bin/php" ]; then
        PHP_BIN="/usr/local/bin/php"
    fi
fi

echo "正在停止 WebSocket 服务器..."
$PHP_BIN ws_server.php stop

echo ""
read -p "是否同时清理 Redis 数据？(y/N): " choice
if [ "$choice" == "y" ] || [ "$choice" == "Y" ]; then
    echo "正在清理 Redis 游戏数据..."
    redis-cli keys "room:*" | xargs -r redis-cli del > /dev/null 2>&1
    echo "Redis 数据已清理 ✓"
fi

echo ""
echo "服务已停止！"
