#!/bin/bash
# ================================
#   谁是卧底 - 快速启动脚本
#   （使用 sudo 运行）
# ================================

echo "================================"
echo "  谁是卧底 - 快速启动"
echo "================================"
echo ""

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# 检查是否以 root 或 sudo 运行
if [ "$EUID" -ne 0 ]; then 
    echo "❌ 错误：必须以 root 用户或 sudo 运行此脚本"
    echo ""
    echo "请在项目目录运行以下命令："
    echo "  cd $SCRIPT_DIR"
    echo "  sudo bash run.sh"
    exit 1
fi

echo "项目路径: $SCRIPT_DIR"
echo ""

# 创建 PID 目录
mkdir -p /tmp/workerman_pids
chmod 777 /tmp/workerman_pids

# 检查 Redis
echo "[1/3] 检查 Redis..."
if redis-cli ping > /dev/null 2>&1; then
    echo "✓ Redis 正在运行"
else
    echo "⚠ Redis 未运行，尝试启动..."
    systemctl start redis 2>/dev/null || service redis-server start 2>/dev/null
    sleep 1
    if redis-cli ping > /dev/null 2>&1; then
        echo "✓ Redis 启动成功"
    else
        echo "❌ Redis 启动失败"
        exit 1
    fi
fi

# 查找 PHP
echo "[2/3] 查找 PHP..."
PHP_BIN=""

if [ -f "/www/server/php/74/bin/php" ]; then
    PHP_BIN="/www/server/php/74/bin/php"
elif [ -f "/www/server/php/73/bin/php" ]; then
    PHP_BIN="/www/server/php/73/bin/php"
elif [ -f "/usr/bin/php" ]; then
    PHP_BIN="/usr/bin/php"
else
    echo "❌ 找不到 PHP"
    exit 1
fi

echo "✓ PHP: $PHP_BIN"
PHP_VERSION=$($PHP_BIN -v | head -n 1)
echo "  版本: $PHP_VERSION"

# 启动 WebSocket
echo "[3/3] 启动 WebSocket 服务器..."

cd "$SCRIPT_DIR"

# 后台启动
nohup $PHP_BIN ws_server.php start > /tmp/workerman_output.log 2>&1 &
PID=$!

sleep 1

# 检查是否成功启动
if ps -p $PID > /dev/null 2>&1; then
    echo "✓ WebSocket 服务器启动成功 (PID: $PID)"
    echo ""
    echo "================================"
    echo "✓ 游戏已准备就绪！"
    echo "================================"
    echo ""
    echo "访问地址: http://game26.gtea.icu/sswd/public/"
    echo "WebSocket: ws://game26.gtea.icu:2346"
    echo ""
    echo "实时日志: tail -f /tmp/workerman_output.log"
    echo "停止服务: sudo bash stop.sh"
    echo ""
else
    echo "❌ 启动失败，查看错误日志："
    cat /tmp/workerman_output.log
    exit 1
fi
