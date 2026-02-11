#!/bin/bash
# ================================
#   谁是卧底 - 服务状态查看脚本
# ================================

cd "$(dirname "$0")"

PHP_BIN="/www/server/php/74/bin/php"

if [ ! -f "$PHP_BIN" ]; then
    if [ -f "/usr/bin/php" ]; then
        PHP_BIN="/usr/bin/php"
    elif [ -f "/usr/local/bin/php" ]; then
        PHP_BIN="/usr/local/bin/php"
    fi
fi

$PHP_BIN ws_server.php status
