#!/bin/bash
# ================================
#   谁是卧底 - 安全启动脚本
#   适用于禁用了 pcntl 函数的环境
# ================================

# 切换到脚本所在目录
cd "$(dirname "$0")"

echo "================================"
echo "  谁是卧底 - 游戏服务启动"
echo "  (安全模式 - 单进程)"
echo "================================"
echo ""

# 检查 Redis 状态
echo "[1/3] 检查 Redis 状态..."
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
echo "[2/3] 检查 PHP 配置..."

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
        echo "找不到 PHP，请修改脚本中的 PHP_BIN 路径"
        exit 1
    fi
fi

echo "使用 PHP: $PHP_BIN"

# 检查 pcntl 是否被禁用
PCNTL_CHECK=$($PHP_BIN -r "echo function_exists('pcntl_fork') ? 'yes' : 'no';")
if [ "$PCNTL_CHECK" == "no" ]; then
    echo "检测到 pcntl 函数被禁用"
    echo "将使用单进程模式运行（适合小规模部署）"
    echo ""
    echo "提示: 如需完整功能,请联系服务器管理员在 php.ini 中"
    echo "      从 disable_functions 移除以下函数:"
    echo "      pcntl_fork, pcntl_signal, pcntl_alarm"
else
    echo "pcntl 扩展可用 ✓"
fi

echo ""
echo "[3/3] 启动 WebSocket 服务器..."
echo "服务器地址: ws://0.0.0.0:2346"
echo "================================"
echo ""

# 使用 -d 参数可以后台运行，但在 pcntl 禁用时不建议
if [ "$1" == "-d" ]; then
    echo "警告: 检测到 -d 参数，但由于环境限制，建议使用前台模式"
    echo "      如需后台运行，请使用 screen 或 nohup 命令"
    echo ""
    read -p "继续吗? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "按 Ctrl+C 停止服务"
echo ""

# 使用 -c 参数指定临时配置,允许 pcntl 函数
# 注意: 这需要 PHP 有权限修改配置
$PHP_BIN -d disable_functions="" ws_server.php start

