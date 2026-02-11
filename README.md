# 谁是卧底 - 局域网实时游戏

基于 PHP + Workerman + Redis 的局域网谁是卧底游戏系统。

## 📁 项目结构

```
sswd/
├── config/
│   └── rooms.json          # 房间配置文件
├── src/
│   ├── RedisClient.php     # Redis 封装类
│   └── RoomConfig.php      # 房间配置读取类
├── api/
│   └── join.php            # 玩家加入接口
├── public/
│   ├── index.html          # 游戏页面
│   ├── css/
│   │   └── style.css       # 样式文件
│   └── js/
│       └── game.js         # 游戏逻辑
├── ws_server.php           # WebSocket 服务器
├── composer.json           # 依赖配置
└── README.md               # 说明文档
```

## 🚀 快速开始

### 1. 上传文件

将整个 `sswd` 目录上传到宝塔网站目录，例如：`/www/wwwroot/your-domain/sswd/`

### 2. 安装依赖

在宝塔面板的「终端」或 SSH 中执行：

```bash
cd /www/wwwroot/your-domain/sswd
composer install
```

如果 composer 命令找不到，使用完整路径：
```bash
/www/server/php/74/bin/php /usr/bin/composer install
```

### 3. 设置脚本权限

```bash
chmod +x start.sh stop.sh status.sh
```

### 4. 防火墙设置

在宝塔面板「安全」中放行端口 **2346**（WebSocket 端口）

同时检查服务器安全组（阿里云/腾讯云等）是否放行 2346

### 5. 启动服务

```bash
# 前台模式（调试用，Ctrl+C 停止）
./start.sh

# 后台守护进程模式（推荐）
./start.sh -d

# 查看状态
./status.sh

# 停止服务
./stop.sh
```

### 6. 访问游戏

浏览器打开：`http://你的域名/sswd/public/`

## 🎮 游戏流程

1. **玩家进入房间**
   - 输入房间号（如 A001）
   - 输入昵称
   - 管理员输入 `admin` 作为昵称

2. **管理员控制**
   - 点击「开始游戏」开始
   - 点击「下一位」推进发言
   - 点击「开启投票」进入投票阶段
   - 根据投票结果「淘汰」玩家
   - 点击「结束本局」结束游戏

3. **玩家操作**
   - 点击身份卡查看自己的词语
   - 在聊天区发言
   - 投票阶段选择怀疑的玩家

## 🔧 技术架构

- **后端**: PHP 7.4+
- **WebSocket**: Workerman 4.x
- **数据存储**: Redis（内存模式，不持久化）
- **前端**: 原生 HTML/CSS/JavaScript

## 📝 Redis 数据结构

| Key | 类型 | 说明 |
|-----|------|------|
| `room:{id}:status` | String | 房间状态 (waiting/started/voting/ended) |
| `room:{id}:players` | Hash | 玩家列表 {seat: {name, alive}} |
| `room:{id}:name_map` | Hash | 姓名映射 {name: seat} |
| `room:{id}:speaker` | String | 当前发言人座位号 |
| `room:{id}:votes` | Hash | 投票统计 {seat: count} |
| `room:{id}:voted` | Set | 已投票玩家名单 |
| `room:{id}:chat` | List | 聊天记录 |

## 📡 WebSocket 协议

### 客户端发送

| action | 参数 | 说明 |
|--------|------|------|
| `chat` | msg | 发送聊天消息 |
| `start` | - | 开始游戏 (管理员) |
| `next` | - | 下一位发言 (管理员) |
| `vote_start` | - | 开启投票 (管理员) |
| `vote` | target | 投票给目标座位 |
| `kill` | seat | 淘汰玩家 (管理员) |
| `end` | - | 结束本局 (管理员) |

### 服务器广播

| type | 数据 | 说明 |
|------|------|------|
| `state` | status, speaker | 房间状态 |
| `players` | list | 玩家列表 |
| `chat` | msg | 聊天消息 |
| `votes` | data | 投票结果 |

## ⚠️ 注意事项

- 这是一次性运行的工具，不是长期服务
- 游戏结束后建议重启服务清理状态
- 最多支持 7 个房间同时运行
- 需要确保 **2346 端口** 在宝塔防火墙和服务器安全组中放行
- 如果使用域名访问，WebSocket 会自动使用相同域名

## 🔧 房间配置

编辑 `config/rooms.json` 设置房间和词语：

```json
{
  "A001": {
    "seats": [
      {"seat": 1, "role": "平民", "word": "苹果"},
      {"seat": 2, "role": "平民", "word": "苹果"},
      {"seat": 3, "role": "卧底", "word": "香蕉"},
      ...
    ]
  }
}
```

## 🛑 停止服务

```bash
./stop.sh
```

## 🐛 常见问题

### 1. composer install 出错

**错误：Your requirements could not be resolved**

解决方案：确保已安装 Predis，重新运行：
```bash
composer install
```

如果仍有问题，检查 PHP 版本：
```bash
php -v
```

### 2. WebSocket 连接失败
- 检查 2346 端口是否在宝塔「安全」中放行
- 检查服务器安全组（阿里云/腾讯云）是否放行 2346
- 检查防火墙: `sudo iptables -L | grep 2346`

### 3. Redis 连接失败
```bash
# 检查 Redis 是否运行
redis-cli ping
# 应该返回 PONG

# 检查 Redis 状态
systemctl status redis

# 启动 Redis
systemctl start redis
```

### 4. PHP 找不到 Predis
```bash
# 确保 composer.json 中包含了 Predis
cat composer.json | grep predis

# 重新安装
composer install --no-cache
```

### 5. Workerman 报错 "Cannot assign requested address"

说明 Workerman 绑定的 IP 有问题，修改 `ws_server.php` 中的绑定地址：
```php
// 改为
$ws_worker = new Worker("websocket://0.0.0.0:2346");
```

---

Made with ❤️ by 绿茶汉化组
