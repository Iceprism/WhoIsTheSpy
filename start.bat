@echo off
chcp 65001 >nul
echo ================================
echo   谁是卧底 - 游戏服务启动脚本
echo ================================
echo.

echo [1/2] 检查 Redis 状态...
redis-cli ping >nul 2>&1
if %errorlevel% neq 0 (
    echo Redis 未运行，请先启动 Redis 服务！
    echo 命令: redis-server
    pause
    exit /b 1
)
echo Redis 运行正常 ✓

echo.
echo [2/2] 启动 WebSocket 服务器...
echo 服务器地址: ws://localhost:2346
echo 按 Ctrl+C 停止服务
echo ================================
echo.

php ws_server.php start
