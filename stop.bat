@echo off
chcp 65001 >nul
echo ================================
echo   谁是卧底 - 游戏服务停止脚本
echo ================================
echo.

echo 正在停止 WebSocket 服务器...
php ws_server.php stop

echo.
echo 是否同时清理 Redis 数据？(Y/N)
set /p choice=
if /i "%choice%"=="Y" (
    echo 正在清理 Redis 游戏数据...
    redis-cli keys "room:*" | xargs redis-cli del >nul 2>&1
    echo Redis 数据已清理 ✓
)

echo.
echo 服务已停止！
pause
