#!/bin/bash
# ================================
#   谁是卧底 - 游戏服务启动脚本
#   适用于 Ubuntu + 宝塔面板
# ================================

# 切换到脚本所在目录
cd "$(dirname "$0")"

echo "================================"
echo "  谁是卧底 - 游戏服务启动"
echo "================================"
echo ""

# 检查 Redis 状态
echo "[1/2] 检查 Redis 状态..."
if redis-cli ping > /dev/null 2>&1; then
    echo "Redis 运行正常 ✓"
else
    echo "Redis 未运行，尝试启动..."
    systemctl start redis
    sleep 1
    if redis-cli ping > /dev/null 2>&1; then
        echo "Redis 启动成功 ✓"
    else
        echo "Redis 启动失败，请检查 Redis 服务！"
        exit 1
    fi
fi

echo ""
echo "[2/2] 启动 WebSocket 服务器..."
echo "服务器地址: ws://0.0.0.0:2346"
echo ""

# 使用宝塔的 PHP 路径启动（后台守护进程模式）
# 如果 PHP 路径不同，请修改下面的路径
PHP_BIN="/www/server/php/74/bin/php"

# 检查 PHP 是否存在
if [ ! -f "$PHP_BIN" ]; then
    # 尝试其他常见路径
    if [ -f "/usr/bin/php" ]; then
        PHP_BIN="/usr/bin/php"
    elif [ -f "/usr/local/bin/php" ]; then
        PHP_BIN="/usr/local/bin/php"
    else
        echo "找不到 PHP，请修改脚本中的 PHP_BIN 路径"
        exit 1
    fi
fi

echo "使用 PHP: $PHP_BIN"
echo "================================"
echo ""

# 启动模式选择
if [ "$1" == "-d" ]; then
    # 后台守护进程模式
    $PHP_BIN ws_server.php start -d
    echo "WebSocket 服务器已在后台启动"
    echo "查看状态: ./start.sh status"
    echo "停止服务: ./stop.sh"
else
    # 前台模式（按 Ctrl+C 停止）
    echo "按 Ctrl+C 停止服务"
    echo ""
    $PHP_BIN ws_server.php start
fi
