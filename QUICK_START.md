# 快速启动指南

## 🚀 一行命令启动

```bash
cd /www/wwwroot/game26.gtea.icu/sswd && sudo bash run.sh
```

## 📋 完整命令

### 启动服务

```bash
# 进入项目目录
cd /www/wwwroot/game26.gtea.icu/sswd

# 前台启动（用于测试，显示日志）
sudo bash start.sh

# 后台启动（推荐）
sudo bash start.sh -d

# 或者用快速启动脚本
sudo bash run.sh
```

### 停止服务

```bash
cd /www/wwwroot/game26.gtea.icu/sswd
sudo bash stop.sh
```

### 查看日志

```bash
# 实时查看日志
tail -f /tmp/workerman_output.log

# 查看最后100行
tail -100 /tmp/workerman_output.log

# 清空日志
> /tmp/workerman_output.log
```

### 查看进程

```bash
# 查看 Workerman 进程
ps aux | grep ws_server

# 查看 PID
cat /tmp/workerman_pids/*.pid

# 手动杀死进程
pkill -f "php ws_server"
```

### 查看 Redis 状态

```bash
# 检查 Redis 是否运行
redis-cli ping

# 查看所有房间数据
redis-cli keys "room:*"

# 清空所有游戏数据
redis-cli FLUSHDB
```

## ⚠️ 常见问题

### 问题 1：Permission denied

**错误信息**：failed to open stream: Permission denied

**解决**：必须使用 sudo 运行
```bash
sudo bash run.sh
```

### 问题 2：PID 文件错误

**解决**：脚本会自动在 `/tmp/workerman_pids` 中创建 PID 文件

```bash
# 检查 PID 文件
ls -la /tmp/workerman_pids/

# 清理 PID 文件
rm -f /tmp/workerman_pids/*.pid
```

### 问题 3：端口被占用

**错误**：Address already in use

**解决**：杀死之前的进程
```bash
sudo pkill -f "php ws_server"
sleep 1
sudo bash run.sh
```

### 问题 4：Redis 连接失败

**检查 Redis**：
```bash
redis-cli ping
# 应该返回 PONG
```

**启动 Redis**：
```bash
systemctl start redis
# 或
service redis-server start
```

## 🔍 调试技巧

### 前台启动查看实时错误

```bash
cd /www/wwwroot/game26.gtea.icu/sswd
sudo /www/server/php/74/bin/php ws_server.php start
```

### 查看 WebSocket 连接

```bash
# 在客户端打开浏览器开发工具
# 按 F12 → 控制台 → 查看 WebSocket 连接

# 或者从服务器查看连接数
ss -tunlp | grep 2346
```

### 重新启动快速命令

```bash
cd /www/wwwroot/game26.gtea.icu/sswd && sudo bash stop.sh && sleep 1 && sudo bash run.sh
```

## 📱 游戏访问

- **管理员**: 输入昵称 `admin`
- **玩家**: 输入房间号和昵称
- **网址**: http://game26.gtea.icu/sswd/public/

---

有问题可查看完整的 DEPLOY.md 文档
