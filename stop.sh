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

# 检查是否以 root 或 sudo 运行
if [ "$EUID" -ne 0 ]; then 
    echo "❌ 错误：必须以 root 用户或 sudo 运行此脚本"
    echo "请使用："
    echo "  sudo ./stop.sh"
    exit 1
fi

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

# 方法1：使用 Workerman 的 stop 命令
$PHP_BIN ws_server.php stop > /dev/null 2>&1

# 方法2：杀死所有相关的 PHP 进程（备用）
pkill -f "php ws_server.php" 2>/dev/null

# 清理 PID 目录
rm -f /tmp/workerman_pids/*.pid 2>/dev/null

echo "✓ WebSocket 服务器已停止"
echo ""

read -p "是否同时清理 Redis 数据？(y/N): " choice
if [ "$choice" == "y" ] || [ "$choice" == "Y" ]; then
    echo "正在清理 Redis 游戏数据..."
    redis-cli keys "room:*" | xargs -r redis-cli del > /dev/null 2>&1
    echo "✓ Redis 数据已清理"
fi

echo ""
echo "服务已停止！"

