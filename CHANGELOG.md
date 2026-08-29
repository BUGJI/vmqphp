# CHANGELOG

本仓库为 [szvone/vmqphp](https://github.com/szvone/vmqphp) 的维护分支。原项目已停止维护，此分支在保持原功能不变的前提下，修复新环境下的兼容性问题。

## [1.12.2] - 2026-08-29

### Added

- **新增 `public/install.php` 安装向导**：全程网页化完成部署配置，无需手动编辑代码——
  - 环境检测（PHP 版本、PDO/GD/curl 扩展、open_basedir、目录权限）
  - 数据库配置（**MySQL 或 SQLite 二选一**，自动写入 `config/database.php`，MySQL 库不存在时尝试自动创建）
  - 自动建表 + 初始化后台管理员账号密码
  - 安装开始页与完成页均**防呆提醒删除 install.php**，并提供一键删除按钮（安全：本文件是重装入口，保留期间任何访问者都能重置系统）
  - 检测到已安装时不再提供重装，提示删除文件后重新上传
- **支持 SQLite 数据库**：零 MySQL 依赖，适合 NAS、虚拟主机等轻量环境。只需 PHP 开启 `pdo_sqlite` 扩展。

### Changed

- `admin/controller/Index.php`（getMain）：数据库版本显示兼容 SQLite（MySQL 用 `VERSION()`，SQLite 用 `sqlite_version()`）。
- `index/controller/Index.php`（createOrder）：金额锁占坑语法兼容 SQLite（MySQL 用 `INSERT IGNORE`，SQLite 用 `INSERT OR IGNORE`）。

### 部署说明（新增）

- 上传后访问 `http://你的域名/install.php` 即可开始安装；安装完成后请立即删除该文件。
- SQLite 模式数据库文件默认放在网站根目录之外的 `data/vmq.db`（**请勿放在 public 内，否则会被直接下载**）。

## [1.12.1] - 2026-08-29

### Fixed

- **修复 PHP 7.4+ / PHP 8.x 下登录接口直接 500 的问题**
  - `thinkphp/library/think/db/Query.php:568`：将已废弃的花括号字符串偏移语法 `$value{0}` 改为 `$value[0]`（PHP 7.4 起废弃、PHP 8.0 起移除）。
  - `thinkphp/library/think/Error.php`：`appError()` 忽略 `E_DEPRECATED` / `E_USER_DEPRECATED`，避免 ThinkPHP debug 模式将 deprecation 提示当作异常抛出导致整站 500。

### 部署注意事项

- 使用宝塔面板部署时，**网站运行目录需设置为 `public`**，并关闭「防跨站攻击（open_basedir）」或确保 `open_basedir` 包含网站根目录（ThinkPHP 框架文件位于 `public/` 之外，若被限制在 `public/` 内会导致所有 PHP 请求 Fatal error）。
- 默认后台账号密码均为 `admin`，部署后请第一时间在「系统设置」中修改。
