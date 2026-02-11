# 宝塔面板部署指南

这是针对 **Ubuntu + 宝塔面板** 环境的详细部署步骤。

## ✅ 环境检查

在宝塔面板中确认以下组件已安装：
- PHP 7.4 (或更高版本)
- Redis 8.0 (或兼容版本)
- Composer (PHP 包管理器)

### 检查 PHP 版本
```bash
/www/server/php/74/bin/php -v
```

### 检查 Redis 版本
```bash
redis-cli --version
```

### 检查 Composer
```bash
which composer
# 或
/www/server/php/74/bin/php /usr/bin/composer --version
```

## 📦 部署步骤

### 第一步：上传项目

1. 在宝塔面板中创建网站
2. 上传 `sswd` 目录到网站根目录
3. 最终路径应为: `/www/wwwroot/yourdomain.com/sswd/`

### 第二步：安装 Composer 依赖

进入宝塔终端，执行：
```bash
cd /www/wwwroot/yourdomain.com/sswd
composer install
```

**如果报错：Your requirements could not be resolved**
- 这通常是因为 PHP Redis 扩展未启用，但不用担心，系统会自动使用 Predis 库
- Predis 是纯 PHP 实现的 Redis 客户端，不需要 C 扩展

### 第三步：设置脚本权限

```bash
chmod +x /www/wwwroot/yourdomain.com/sswd/start.sh
chmod +x /www/wwwroot/yourdomain.com/sswd/stop.sh
chmod +x /www/wwwroot/yourdomain.com/sswd/status.sh
```

### 第四步：宝塔防火墙配置

1. 打开宝塔面板 → 「安全」
2. 放行端口 **2346**
   - 规则：入站规则
   - 协议：TCP
   - 端口：2346
   - 操作：允许

### 第五步：云服务器安全组（如果使用阿里云/腾讯云）

需要在云厂商的控制台放行 2346 端口入站：
- **阿里云**：ECS 安全组 → 入站规则 → 添加规则
- **腾讯云**：安全组 → 入站规则 → 添加规则
- **其他厂商**：类似操作

## 🚀 启动与停止

### 启动 WebSocket 服务

进入项目目录：
```bash
cd /www/wwwroot/yourdomain.com/sswd
```

**前台启动**（用于测试和调试）：
```bash
./start.sh
```

**后台启动**（推荐用于正式环境）：
```bash
./start.sh -d
```

### 查看服务状态
```bash
./status.sh
```

### 停止服务
```bash
./stop.sh
```

## 🌐 访问游戏

部署完成后，打开浏览器访问：
```
http://yourdomain.com/sswd/public/
```

## 🔧 自定义配置

### 修改房间配置

编辑 `/config/rooms.json`：

```json
{
  "房间号": {
    "seats": [
      {"seat": 1, "role": "平民", "word": "词语1"},
      {"seat": 2, "role": "平民", "word": "词语1"},
      {"seat": 3, "role": "卧底", "word": "词语2"},
      ...
    ]
  }
}
```

### 修改 Redis 连接配置

如果 Redis 不在默认地址 127.0.0.1:6379，可以设置环境变量：

```bash
export REDIS_HOST=127.0.0.1
export REDIS_PORT=6379
export REDIS_PASSWORD=yourpassword  # 如果有密码
```

然后启动服务：
```bash
./start.sh -d
```

## ⚠️ 常见问题排查

### 问题 1：WebSocket 连接失败

**现象**：浏览器控制台显示 WebSocket connection failed

**排查步骤**：
1. 确认 2346 端口已在宝塔放行
   ```bash
   sudo iptables -L | grep 2346
   ```

2. 确认 WebSocket 服务正在运行
   ```bash
   ./status.sh
   ```

3. 尝试从服务器本地连接
   ```bash
   curl http://127.0.0.1:2346
   ```

4. 检查服务器日志（如果有）

### 问题 2：composer install 报错 ext-redis

**现象**：ext-redis * but it is missing

**解决**：这是正常的，系统会使用 Predis 替代。继续安装即可。

### 问题 3：Redis 连接失败

**现象**：Redis 连接失败的错误信息

**排查步骤**：
```bash
# 检查 Redis 是否运行
redis-cli ping
# 应该返回 PONG

# 如果没有运行，启动 Redis
systemctl start redis

# 检查 Redis 配置
redis-cli CONFIG GET port
```

### 问题 4：启动脚本找不到 PHP

**现象**：./start.sh: /www/server/php/74/bin/php: No such file

**解决**：
1. 查找 PHP 实际路径
   ```bash
   which php
   find /www -name "php" -type f 2>/dev/null
   ```

2. 修改 `start.sh` 中的 `PHP_BIN` 为正确路径

## 🔐 安全建议

1. **限制 WebSocket 端口访问**
   - 只在需要的时间段放行 2346 端口
   - 游戏结束后立即关闭

2. **使用 HTTPS/WSS**
   - 为了更安全，考虑使用反向代理（Nginx）
   - 将 ws:// 转发为 wss://

3. **监控资源使用**
   - 定期检查服务器 CPU 和内存使用
   - 每局游戏结束后重启服务

## 📞 技术支持

如遇到问题，请提供以下信息：
1. 出错的具体信息
2. 服务器日志（如有）
3. 浏览器控制台错误
4. PHP 和 Redis 版本

---

Made with ❤️
