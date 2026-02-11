# PHP pcntl 函数禁用问题解决方案

## 问题描述

你遇到的错误是:
```
pcntl_signal() has been disabled for security reasons
pcntl_fork() has been disabled for security reasons
```

这是因为服务器的 PHP 配置中禁用了 `pcntl_*` 系列函数,这在共享主机和某些生产环境中很常见,目的是防止潜在的安全风险。

## 解决方案

### 方案 1: 启用 pcntl 函数 (推荐 - 适合生产环境)

1. **找到 php.ini 文件**
   ```bash
   # 查看 PHP 配置文件位置
   /www/server/php/74/bin/php --ini
   ```

2. **编辑 php.ini**
   ```bash
   # 使用宝塔面板
   # 在宝塔面板 -> 软件商店 -> PHP 7.4 -> 设置 -> 配置修改
   
   # 或直接编辑文件
   vim /www/server/php/74/etc/php.ini
   ```

3. **找到 `disable_functions` 这一行,移除以下函数:**
   ```ini
   ; 找到这一行 (可能很长):
   disable_functions = ...,pcntl_alarm,pcntl_fork,pcntl_signal,...
   
   ; 移除这三个函数:
   ; - pcntl_alarm
   ; - pcntl_fork  
   ; - pcntl_signal
   ```

4. **重启 PHP-FPM**
   ```bash
   # 宝塔面板
   # 在宝塔面板 -> 软件商店 -> PHP 7.4 -> 重载配置
   
   # 或命令行
   systemctl restart php-fpm-74
   ```

5. **验证修改**
   ```bash
   /www/server/php/74/bin/php -r "echo function_exists('pcntl_fork') ? 'OK' : 'Failed';"
   ```

### 方案 2: 使用临时启用命令 (快速测试)

使用新的启动脚本 `start_safe.sh`,它会尝试临时启用这些函数:

```bash
chmod +x start_safe.sh
./start_safe.sh
```

这个脚本会使用 `-d disable_functions=""` 参数来尝试覆盖禁用设置。

### 方案 3: 使用 Docker (最佳实践)

创建一个 Docker 容器,完全控制 PHP 环境:

```dockerfile
FROM php:7.4-cli
RUN docker-php-ext-install pcntl
# ... 其他配置
```

### 方案 4: 单进程模式 (开发/小规模)

如果无法修改 PHP 配置,Workerman 可以在单进程模式下运行,但功能会受限:

- 不支持热重载
- 并发性能较低
- 不适合生产环境高负载

代码已修改为自动检测并使用单进程模式。

## 宝塔面板操作步骤

如果你使用的是宝塔面板:

1. 登录宝塔面板
2. 进入 **软件商店**
3. 找到 **PHP 7.4**,点击 **设置**
4. 选择 **禁用函数**
5. 找到并删除以下函数:
   - `pcntl_alarm`
   - `pcntl_fork`
   - `pcntl_signal`
   - `pcntl_wait`
   - `pcntl_waitpid`
6. 点击 **保存**
7. 点击 **重载配置**

## 验证是否成功

运行以下命令检查:

```bash
cd /www/wwwroot/game26.gtea.icu
/www/server/php/74/bin/php -r "
if (function_exists('pcntl_fork') && function_exists('pcntl_signal')) {
    echo '✓ pcntl 函数已启用\n';
} else {
    echo '✗ pcntl 函数仍被禁用\n';
    echo '被禁用的函数: ' . ini_get('disable_functions') . '\n';
}
"
```

## 重新启动服务

完成配置后,使用原来的启动脚本:

```bash
./start.sh
```

## 注意事项

1. **安全性**: 启用 pcntl 函数可能带来安全风险,确保你的代码是可信的
2. **权限**: 你需要有修改 PHP 配置的权限
3. **备份**: 修改 php.ini 前先备份原文件
4. **影响范围**: 修改会影响该 PHP 版本的所有网站

## 相关链接

- [Workerman 官方文档 - 安装扩展](https://www.workerman.net/doc/workerman/install/install.html)
- [PHP pcntl 扩展文档](https://www.php.net/manual/zh/book.pcntl.php)
