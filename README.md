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

### 1. 安装依赖

```bash
composer install
```

### 2. 配置房间

编辑 `config/rooms.json`，设置房间和座位信息：

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

### 3. 启动服务

**Windows (PowerShell):**
```powershell
# 启动 Redis（确保 Redis 已安装并运行）
redis-server

# 启动 WebSocket 服务器（新开一个终端）
php ws_server.php start
```

**Linux/Mac:**
```bash
# 启动 Redis
redis-server &

# 启动 WebSocket 服务器
php ws_server.php start -d
```

### 4. 访问游戏

浏览器打开：`http://localhost/sswd/public/`

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
- 需要确保 2346 端口未被占用

## 🛑 停止服务

```bash
# 停止 WebSocket 服务器
Ctrl+C

# 停止 Redis
redis-cli shutdown
```

---

Made with ❤️ by 绿茶汉化组
