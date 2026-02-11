#!/bin/bash
# ================================
#   PHP 环境检查脚本
# ================================

echo "================================"
echo "  PHP 环境检查"
echo "================================"
echo ""

# 查找 PHP
PHP_BIN="/www/server/php/74/bin/php"
if [ ! -f "$PHP_BIN" ]; then
    PHP_BIN=$(which php)
    if [ -z "$PHP_BIN" ]; then
        echo "错误: 找不到 PHP"
        exit 1
    fi
fi

echo "PHP 路径: $PHP_BIN"
echo ""

# PHP 版本
echo "1. PHP 版本:"
$PHP_BIN -v | head -n 1
echo ""

# 检查 pcntl 扩展
echo "2. pcntl 扩展状态:"
PCNTL_LOADED=$($PHP_BIN -r "echo extension_loaded('pcntl') ? 'yes' : 'no';")
if [ "$PCNTL_LOADED" == "yes" ]; then
    echo "   ✓ pcntl 扩展已加载"
else
    echo "   ✗ pcntl 扩展未加载"
fi

# 检查 pcntl 函数
PCNTL_FORK=$($PHP_BIN -r "echo function_exists('pcntl_fork') ? 'yes' : 'no';")
PCNTL_SIGNAL=$($PHP_BIN -r "echo function_exists('pcntl_signal') ? 'yes' : 'no';")

if [ "$PCNTL_FORK" == "yes" ] && [ "$PCNTL_SIGNAL" == "yes" ]; then
    echo "   ✓ pcntl_fork() 可用"
    echo "   ✓ pcntl_signal() 可用"
    echo ""
    echo "✓ 环境检查通过，可以正常运行 Workerman"
else
    echo "   ✗ pcntl_fork() 不可用"
    echo "   ✗ pcntl_signal() 不可用"
    echo ""
    echo "✗ pcntl 函数被禁用"
    echo ""
    echo "被禁用的函数列表:"
    $PHP_BIN -r "echo ini_get('disable_functions');" | tr ',' '\n' | grep pcntl
    echo ""
    echo "解决方案:"
    echo "  1. 修改 PHP 配置启用 pcntl 函数（推荐）"
    echo "  2. 使用 start_safe.sh 脚本启动"
    echo "  3. 查看 FIX_PCNTL.md 了解详细步骤"
fi

echo ""

# 检查 Redis 扩展
echo "3. Redis 扩展状态:"
REDIS_LOADED=$($PHP_BIN -r "echo extension_loaded('redis') ? 'yes' : 'no';")
if [ "$REDIS_LOADED" == "yes" ]; then
    echo "   ✓ Redis 扩展已加载"
else
    echo "   ⚠ Redis 扩展未加载（使用 Socket 连接）"
fi

echo ""

# 检查 Composer 依赖
echo "4. Composer 依赖:"
if [ -d "vendor" ]; then
    echo "   ✓ vendor 目录存在"
    if [ -f "vendor/autoload.php" ]; then
        echo "   ✓ autoload.php 存在"
    else
        echo "   ✗ autoload.php 不存在，请运行: composer install"
    fi
else
    echo "   ✗ vendor 目录不存在，请运行: composer install"
fi

echo ""

# 检查 Redis 服务
echo "5. Redis 服务状态:"
if redis-cli ping > /dev/null 2>&1; then
    echo "   ✓ Redis 服务运行正常"
else
    echo "   ✗ Redis 服务未运行"
fi

echo ""
echo "================================"
echo "检查完成"
echo "================================"
