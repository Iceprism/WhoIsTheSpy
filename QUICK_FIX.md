# 快速修复指南 - pcntl 函数被禁用

## 🚨 你遇到的问题

```
pcntl_signal() has been disabled for security reasons
pcntl_fork() has been disabled for security reasons
PHP Fatal error: Uncaught Exception: forkOneWorker fail
```

## ✅ 一键解决（宝塔面板用户）

### 步骤 1: 登录宝塔面板

访问: `http://你的服务器IP:8888`

### 步骤 2: 修改 PHP 禁用函数

1. 点击左侧 **软件商店**
2. 找到 **PHP 7.4**（或你使用的 PHP 版本）
3. 点击 **设置** 按钮
4. 点击 **禁用函数** 标签
5. 在列表中找到并 **删除** 以下函数：
   ```
   pcntl_alarm
   pcntl_fork
   pcntl_signal
   pcntl_wait
   pcntl_waitpid
   ```
6. 点击 **保存**
7. 点击 **服务** 标签，点击 **重载配置**

### 步骤 3: 验证修复

```bash
cd /www/wwwroot/game26.gtea.icu

# 运行环境检查脚本
chmod +x check_env.sh
./check_env.sh
```

应该看到:
```
✓ pcntl_fork() 可用
✓ pcntl_signal() 可用
✓ 环境检查通过，可以正常运行 Workerman
```

### 步骤 4: 重新启动服务

```bash
./start.sh
```

## 🎯 如果还不行

### 检查 php.ini 位置

```bash
/www/server/php/74/bin/php --ini
```

会显示类似:
```
Configuration File (php.ini) Path: /www/server/php/74/etc
Loaded Configuration File:         /www/server/php/74/etc/php.ini
```

### 手动编辑 php.ini

```bash
# 备份配置文件
cp /www/server/php/74/etc/php.ini /www/server/php/74/etc/php.ini.backup

# 编辑配置文件
vim /www/server/php/74/etc/php.ini

# 或使用 nano
nano /www/server/php/74/etc/php.ini
```

找到 `disable_functions` 这一行（通常在 300 行左右）:

**修改前:**
```ini
disable_functions = passthru,exec,system,chroot,chgrp,chown,shell_exec,proc_open,proc_get_status,popen,ini_alter,ini_restore,dl,openlog,syslog,readlink,symlink,popepassthru,stream_socket_server,fsocket,pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_wifexited,pcntl_wifstopped,pcntl_wifsignaled,pcntl_wexitstatus,pcntl_wtermsig,pcntl_wstopsig,pcntl_signal,pcntl_signal_dispatch,pcntl_get_last_error,pcntl_strerror,pcntl_sigprocmask,pcntl_sigwaitinfo,pcntl_sigtimedwait,pcntl_exec,pcntl_getpriority,pcntl_setpriority
```

**修改后（删除 pcntl_* 函数）:**
```ini
disable_functions = passthru,exec,system,chroot,chgrp,chown,shell_exec,proc_open,proc_get_status,popen,ini_alter,ini_restore,dl,openlog,syslog,readlink,symlink,popepassthru,stream_socket_server,fsocket
```

保存并退出（vim: `:wq` / nano: `Ctrl+X` 然后 `Y`）

### 重启 PHP-FPM

```bash
# 宝塔安装的 PHP
systemctl restart php-fpm-74

# 或
/etc/init.d/php-fpm-74 restart
```

## 📋 完整命令序列（复制粘贴）

```bash
# 1. 进入项目目录
cd /www/wwwroot/game26.gtea.icu

# 2. 运行环境检查
chmod +x check_env.sh
./check_env.sh

# 3. 如果显示 pcntl 不可用，编辑 PHP 配置
# 在宝塔面板操作，或：
# vim /www/server/php/74/etc/php.ini
# 删除 disable_functions 中的 pcntl_* 函数

# 4. 重启 PHP-FPM
systemctl restart php-fpm-74

# 5. 再次检查环境
./check_env.sh

# 6. 启动服务
./start.sh
```

## ⚠️ 安全提示

启用 `pcntl` 函数会略微降低安全性，但对于专用游戏服务器来说是安全的。

**建议:**
- 只在游戏服务器上启用
- 不要在共享的网站服务器上启用
- 确保服务器有防火墙保护
- 定期更新 PHP 和系统

## 🆘 仍然有问题？

### 选项 1: 使用临时启用脚本

```bash
./start_safe.sh
```

### 选项 2: 使用 Screen 保持后台运行

```bash
# 安装 screen（如果没有）
yum install screen -y   # CentOS
apt install screen -y   # Ubuntu

# 创建新会话
screen -S game

# 启动服务
./start.sh

# 按 Ctrl+A 然后按 D 离开会话
# 重新连接: screen -r game
```

### 选项 3: 联系我

如果以上方法都不行，请提供:
1. `./check_env.sh` 的完整输出
2. `cat /www/server/php/74/etc/php.ini | grep disable_functions` 的输出
3. 你的服务器环境（宝塔版本、PHP 版本、操作系统）

## ✅ 成功标志

当看到以下输出时，表示启动成功：

```
[1/2] 检查 Redis 状态...
Redis 运行正常 ✓

[2/2] 启动 WebSocket 服务器...
服务器地址: ws://0.0.0.0:2346

使用 PHP: /www/server/php/74/bin/php
================================

按 Ctrl+C 停止服务

Workerman[ws_server.php] start in DEBUG mode
-------------------------------------------- WORKERMAN --------------------------------------------
Workerman version:4.1.17          PHP version:7.4.28           Event-Loop:\Workerman\Events\Select
--------------------------------------------- WORKERS ---------------------------------------------
proto   user            worker          listen                      processes    status           
tcp     root            none            websocket://0.0.0.0:2346    1             [OK]            
---------------------------------------------------------------------------------------------------
Press Ctrl+C to stop. Start success.
```

**没有 pcntl 错误，显示 "Start success" = 成功！** 🎉
