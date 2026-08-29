# CHANGELOG

本仓库为 [szvone/vmqphp](https://github.com/szvone/vmqphp) 的维护分支。原项目已停止维护，此分支在保持原功能不变的前提下，修复新环境下的兼容性问题。

## [1.12.1] - 2026-08-29

### Fixed

- **修复 PHP 7.4+ / PHP 8.x 下登录接口直接 500 的问题**
  - `thinkphp/library/think/db/Query.php:568`：将已废弃的花括号字符串偏移语法 `$value{0}` 改为 `$value[0]`（PHP 7.4 起废弃、PHP 8.0 起移除）。
  - `thinkphp/library/think/Error.php`：`appError()` 忽略 `E_DEPRECATED` / `E_USER_DEPRECATED`，避免 ThinkPHP debug 模式将 deprecation 提示当作异常抛出导致整站 500。

### 部署注意事项

- 使用宝塔面板部署时，**网站运行目录需设置为 `public`**，并关闭「防跨站攻击（open_basedir）」或确保 `open_basedir` 包含网站根目录（ThinkPHP 框架文件位于 `public/` 之外，若被限制在 `public/` 内会导致所有 PHP 请求 Fatal error）。
- 默认后台账号密码均为 `admin`，部署后请第一时间在「系统设置」中修改。
