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

# 检查是否以 root 或 sudo 运行
if [ "$EUID" -ne 0 ]; then 
    echo "❌ 错误：必须以 root 用户或 sudo 运行此脚本"
    echo "请使用："
    echo "  sudo ./start.sh"
    echo "  或"
    echo "  sudo ./start.sh -d"
    exit 1
fi

# 检查 Redis 状态
echo "[1/3] 检查 Redis 状态..."
if redis-cli ping > /dev/null 2>&1; then
    echo "✓ Redis 运行正常"
else
    echo "⚠ Redis 未运行，尝试启动..."
    systemctl start redis
    sleep 1
    if redis-cli ping > /dev/null 2>&1; then
        echo "✓ Redis 启动成功"
    else
        echo "❌ Redis 启动失败，请检查 Redis 服务！"
        exit 1
    fi
fi

echo ""
echo "[2/3] 创建 PID 目录..."
# 创建 /tmp/workerman_pids 目录用于存储 PID 文件
mkdir -p /tmp/workerman_pids
chmod 777 /tmp/workerman_pids
echo "✓ PID 目录准备完成"

echo ""
echo "[3/3] 启动 WebSocket 服务器..."
echo "服务器地址: ws://0.0.0.0:2346"
echo ""

# 使用宝塔的 PHP 路径启动
PHP_BIN="/www/server/php/74/bin/php"

# 检查 PHP 是否存在
if [ ! -f "$PHP_BIN" ]; then
    # 尝试其他常见路径
    if [ -f "/usr/bin/php" ]; then
        PHP_BIN="/usr/bin/php"
    elif [ -f "/usr/local/bin/php" ]; then
        PHP_BIN="/usr/local/bin/php"
    else
        echo "❌ 找不到 PHP，请修改脚本中的 PHP_BIN 路径"
        exit 1
    fi
fi

echo "使用 PHP: $PHP_BIN"
echo "================================"
echo ""

# 启动模式选择
if [ "$1" == "-d" ]; then
    # 后台守护进程模式
    nohup $PHP_BIN ws_server.php start > /tmp/workerman_output.log 2>&1 &
    PID=$!
    echo "✓ WebSocket 服务器已在后台启动 (PID: $PID)"
    echo "日志文件: /tmp/workerman_output.log"
    echo ""
    echo "查看日志: tail -f /tmp/workerman_output.log"
    echo "停止服务: sudo ./stop.sh"
else
    # 前台模式（按 Ctrl+C 停止）
    echo "按 Ctrl+C 停止服务"
    echo ""
    $PHP_BIN ws_server.php start
fi

