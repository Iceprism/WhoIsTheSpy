# 问题诊断报告

## 📋 问题摘要

**错误类型:** PHP pcntl 函数被禁用  
**影响:** Workerman 无法启动多进程模式  
**严重程度:** 🔴 高（服务无法启动）  
**解决难度:** ⭐⭐☆☆☆（简单）

---

## 🔍 错误详情

### 错误信息
```
pcntl_signal() has been disabled for security reasons
pcntl_fork() has been disabled for security reasons
PHP Fatal error: Uncaught Exception: forkOneWorker fail
```

### 错误原因
服务器的 PHP 配置中 `disable_functions` 包含了 `pcntl_fork` 和 `pcntl_signal` 函数，导致 Workerman 无法创建子进程。

### 为什么会被禁用？
- **安全考虑**: 共享主机为防止恶意代码执行进程控制
- **宝塔默认**: 宝塔面板默认禁用这些函数
- **生产环境**: 很多托管环境默认禁用

---

## ✅ 解决方案（按推荐程度排序）

### 🥇 方案 1: 修改 PHP 配置（推荐）

**优点:** 永久解决，性能最好  
**缺点:** 需要服务器权限  
**适用:** 有宝塔面板或 root 权限

**操作步骤:**
1. 宝塔面板 → 软件商店 → PHP 7.4 → 设置 → 禁用函数
2. 删除 `pcntl_fork`, `pcntl_signal`, `pcntl_alarm` 等函数
3. 保存并重载配置

**详细指南:** [QUICK_FIX.md](QUICK_FIX.md)

---

### 🥈 方案 2: 使用安全启动脚本

**优点:** 不需要修改配置  
**缺点:** 每次启动都需要特殊命令  
**适用:** 临时测试或权限受限环境

**操作步骤:**
```bash
chmod +x start_safe.sh
./start_safe.sh
```

该脚本会尝试使用 `-d disable_functions=""` 参数临时启用函数。

---

### 🥉 方案 3: Docker 容器（最佳实践）

**优点:** 完全控制环境，隔离安全  
**缺点:** 需要学习 Docker  
**适用:** 生产环境，长期运行

这是最佳实践，但需要额外的学习成本。

---

### 🏅 方案 4: 单进程模式（备选）

**优点:** 无需任何配置  
**缺点:** 性能受限，不支持热重载  
**适用:** 小规模测试

代码已自动支持，但功能受限。

---

## 📁 新增文件说明

| 文件 | 用途 | 使用方法 |
|------|------|----------|
| `QUICK_FIX.md` | 快速修复指南 | 查看一键解决步骤 |
| `FIX_PCNTL.md` | 详细技术文档 | 了解问题原理和多种解决方案 |
| `check_env.sh` | 环境检查脚本 | `./check_env.sh` 检查 PHP 环境 |
| `start_safe.sh` | 安全启动脚本 | `./start_safe.sh` 尝试临时启用 pcntl |

---

## 🎯 推荐操作流程

### 步骤 1: 环境检查
```bash
cd /www/wwwroot/game26.gtea.icu
chmod +x check_env.sh
./check_env.sh
```

### 步骤 2: 根据检查结果选择方案

**如果显示 "pcntl 函数被禁用":**
- **有宝塔面板** → 使用方案 1（修改 PHP 配置）
- **无面板权限** → 使用方案 2（安全启动脚本）

### 步骤 3: 实施修复

**方案 1 用户:**
1. 查看 [QUICK_FIX.md](QUICK_FIX.md)
2. 按步骤修改宝塔面板设置
3. 重启 PHP-FPM
4. 运行 `./check_env.sh` 验证
5. 运行 `./start.sh` 启动服务

**方案 2 用户:**
1. 运行 `chmod +x start_safe.sh`
2. 运行 `./start_safe.sh`
3. 如果失败，联系管理员使用方案 1

### 步骤 4: 验证成功

看到以下输出表示成功：
```
Workerman[ws_server.php] start in DEBUG mode
-------------------------------------------- WORKERMAN --------------------------------------------
proto   user            worker          listen                      processes    status           
tcp     root            none            websocket://0.0.0.0:2346    1             [OK]            
---------------------------------------------------------------------------------------------------
Press Ctrl+C to stop. Start success.
```

**关键标志:** 
- ✅ 没有 `pcntl_signal() has been disabled` 错误
- ✅ 显示 `Start success`
- ✅ 状态显示 `[OK]`

---

## 📖 相关文档

- [QUICK_FIX.md](QUICK_FIX.md) - 宝塔面板用户一键修复指南
- [FIX_PCNTL.md](FIX_PCNTL.md) - 详细的技术说明和多种解决方案
- [README.md](README.md) - 项目整体说明文档

---

## 🆘 仍有问题？

### 提供以下信息以便诊断：

```bash
# 1. 环境检查结果
./check_env.sh > env_report.txt

# 2. PHP 配置
/www/server/php/74/bin/php -i > php_info.txt

# 3. 禁用函数列表
/www/server/php/74/bin/php -r "echo ini_get('disable_functions');" > disabled_functions.txt

# 4. 启动错误日志
./start.sh 2>&1 | tee start_error.txt
```

将这些文件发送给技术支持。

---

## ✨ 修改总结

### 修改的文件
1. `ws_server.php` - 添加 pcntl 检测和单进程模式支持

### 新增的文件
1. `QUICK_FIX.md` - 快速修复指南
2. `FIX_PCNTL.md` - 详细技术文档
3. `check_env.sh` - 环境检查脚本
4. `start_safe.sh` - 安全启动脚本
5. `DIAGNOSIS.md` - 本诊断报告

### 更新的文件
1. `README.md` - 添加常见问题解答

---

**祝你好运！游戏服务器马上就能运行了！** 🎮✨
