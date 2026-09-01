# CHANGELOG

本仓库为 [szvone/vmqphp](https://github.com/szvone/vmqphp) 的维护分支。原项目已停止维护，此分支在保持原功能不变的前提下，修复新环境下的兼容性问题。

## [1.12.3] - 2026-08-31

### Added

- **后台「接口模式」切换（V免签格式 / 易支付格式）**：系统对外接口协议可一键切换——
  - 后台「系统设置」新增「接口模式」下拉框：`0` = V免签格式（默认，原协议不变），`1` = 易支付格式。
  - 两种模式**互斥**：易支付模式下 `createOrder` / `getOrder` 返回提示改用易支付接口；V免签模式下 `epaySubmit` / `epayOrder` 同样被拦截。
  - 易支付模式对接协议（与彩虹易支付一致）：
    - 下单 `epaySubmit`：`pid` 固定为 `1`，`type` 传 `alipay` / `wxpay`（内部映射支付宝→2、微信→1），`sign` = 参数按 ASCII 升序 `k=v&` 拼接后追加通讯密钥再 md5。
    - 查单 `epayOrder`：`pid + key` 认证，返回 `trade_no / out_trade_no / type / money / status` 标准格式。
    - 回调：支付成功由 `appPush` 以 **GET** 方式通知商户 `notify_url`，参数含 `pid / trade_no / out_trade_no / type / name / money / trade_status(TRADE_SUCCESS) / sign`，商户验签后输出字符串 `success`。
    - `key`、`notify_url`、`return_url` 两种模式共用后台设置；下单未传 `notify_url` / `return_url` 时使用后台默认值。
- **回调失败自动重试**：弥补 V免签无重试设计的缺陷——异步通知失败（未返回 `success`）的订单标记 `state=2` 后，系统间隔约 60 秒自动重发回调，最多重试 3 次；重发成功自动恢复 `state=1`。`pay_order` 表新增 `retry_count` 字段。
- **易支付协议测试入口**：`public/example/main_epay.php`（与 `main.php` 并列），`index.html` 增加「接口协议」下拉框选择 V免签 / 易支付协议。
- **接口文档更新**：`public/api.html` 新增「易支付模式」章节，含下单 / 查单 / 回调验签 PHP 示例。

### Changed

- `index/controller/Index.php`：
  - 抽出建单核心 `_createOrderCore()` 供 `createOrder` / `epaySubmit` 共用（原 V免签下单行为不变）。
  - `appPush` 回调按接口模式分流：易支付模式发送易支付格式回调，V免签模式保持原格式。
  - 新增 `epaySign()`（ksort md5 签名）、`epaySubmit()`、`epayOrder()`、`_retryNotify()`。
- `admin/controller/Index.php`：`getSettings` 返回 `apiMode`，`saveSetting` 写入 `apiMode`（行不存在时用 `REPLACE INTO` 兼容 MySQL / SQLite）。
- `route/route.php`：新增 `epaySubmit` / `epayOrder` 两条路由。
- `public/install.php`：初始化 `setting` 表种子增加 `apiMode`，`pay_order` 建表增加 `retry_count`（MySQL / SQLite 双库）。
- **后台设置保存放宽校验**：保存时不再强制要求同步回调地址（`returnUrl`）、异步回调地址（`notifyUrl`）、微信 / 支付宝收款二维码非空，避免阻挡用户修改密钥、接口模式等其他配置。
- **登录页 CDN 资源本地化**：`public/index.html` 原通过 jsdelivr / baomitu CDN 加载 jQuery、skel、layer，在国内网络环境经常加载失败导致登录按钮无反应；现将 jQuery 1.11.3、skel 3.0.1、layer 3.1.1（含 theme 样式与图标）全部下载到 `assets/js/` 本地引用，彻底摆脱外网依赖。
- **后台框架页 CDN 资源本地化**：`public/aaa.html` 原通过 staticfile / baomitu CDN 加载 html5shiv、respond、jQuery 3.3.1，同样存在 CDN 加载失败导致后台菜单空白的问题；已本地化到 `assets/js/`。
- **测试页通讯密钥可填写**：`public/example/index.html` 新增「通讯密钥」输入框，不再写死密钥；`main.php` / `main_epay.php` / `notify.php` / `return.php` 支持 `key` 参数覆盖（未传时回退示例默认密钥），方便用户按自己后台配置的密钥直接测试。

## [1.12.4] - 2026-08-31

### Fixed

- **易支付模式下支付页误报「订单已过期」**：`getOrder` 此前在易支付模式（apiMode=1）下被拦截返回「请使用 epayOrder 接口」，而支付页 `payPage/pay.html` 依赖 `getOrder` 查单，导致下单跳转后立即显示订单过期。已移除 `getOrder` 的模式拦截——它是支付页内部查单接口，协议互斥只保留在下单接口（`createOrder` / `epaySubmit`）。
- **回调重发阻塞导致站点周期性无响应**：`getCurl` 超时被后一次赋值覆盖为 **60 秒**（先设 15 又被覆盖成 60），且 `_retryNotify` 每次心跳（`appHeart`）串行重发最多 20 个失败订单——若存在 `state=2` 且回调地址不可达的订单，一次重发窗口可阻塞 PHP-FPM 进程长达 20×60 秒，进程池占满后整站「访问不上」。已修复：curl 超时统一为 **5 秒**；`_retryNotify` 每轮只重发 **1 个**订单（配合原有 60 秒间隔天然限速），避免回调风暴。
- **示例回调接收端智能验签**：`example/notify.php` / `example/return.php` 原先只用 V免签格式验签且密钥写死示例默认值，导致易支付模式（apiMode=1）下真实支付回调与后台补单回调全部返回 `error_sign`、订单无法标记成功。现已支持 **V免签 / 易支付双格式自动识别**，且密钥优先从回调参数、其次自动读取 `config/database.php` 的 `setting.key`（MySQL / SQLite 兼容），无需手动改文件。
- **后台补单按接口模式分流**：`admin/index/setBd` 原先固定发送 V免签格式回调，易支付模式下商户收到错误协议；已按 `apiMode` 分流，与 `appPush` 回调格式保持一致（易支付格式 ksort-md5 签名）。

### 升级说明

- 已部署的旧库需手动执行两条 SQL（新装用 `install.php` 自动完成）：

  ```sql
  ALTER TABLE pay_order ADD COLUMN retry_count INT NOT NULL DEFAULT 0;
  INSERT INTO setting (vkey, vvalue) VALUES ('apiMode', '0');
  ```

- 易支付模式下商户端回调接口请参照 `public/api.html`「易支付模式」章节实现验签并返回 `success`。

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

### Fixed

- **appPush 回调偶发失败导致订单停留在待支付/通知失败**：`getCurl` 5 秒超时在通知端首次连库或 PHP-FPM 排队时容易超时（实测首次回调失败、60 秒后自动重发即成功）。已将 curl 超时放宽至 **10 秒**，并在 `appPush` 回调失败后 **立即重试一次**（间隔 1 秒），避免用户支付后订单长时间停在 `state=2`。补单与重发链路不变。


---

## 原版更新历史（2019-2020，来自原版 README，仅为存档）

### v1.12（2020.01.30）

- 增加一些提示信息

### v1.11（2019.10.28）

- 修复上传二维码一直卡在处理中
- 如二维码无法正常识别，请给 `/public/qr-code/test.php` 设置 777 权限

### v1.10.1（2019.09.16）

- 增加版本更新提示

### v1.10（2019.09.15）

- 调整二维码识别方案，提升二维码识别率
- 增加第一次安装时系统自动生成通讯密钥的功能

### v1.9.1（2019.09.15）

- 二维码识别出错增加解决方法：在其他网站（草料二维码识别）识别二维码内容后，将内容重新生成成二维码图片上传

### v1.9（2019.09.11）

- 修复一些已知的 BUG
- 删除时间校验（很多人的服务器时间不准确），不会出现客户端时间错误
- 增加主页服务器基本配置的显示列表

### v1.8.1（2019.05.22）

- 增加详细的手机端设置教程
- 同步最新版监控端 App

### v1.8（2019.05.16）

- 更新监控端 APP 到 1.6 版本，理论支持更多手机
- 尝试修复偶然情况下锁定金额无法被释放的问题

### v1.7.2（2019.05.12）

- 修复当通知地址带有 GET 参数时无法正常通知的问题

### v1.7.1（2019.05.07）

- 修复上个版本更新后订单金额异常的问题

### v1.7（2019.05.06）

- 修复部分情况下无法自动释放被锁定金额的情况（数据库有变动：`tmp_price` 表增加 `oid` 字段 varchar(255)；不会加字段可删除原库重新导入 vmq.sql）

### v1.6.2（2019.04.30）

- 修复部分情况下出现订单已过期但页面还在倒计时的问题

### v1.6.1（2019.04.26）

- 再次优化二维码识别：先用 js 解析，失败则用 PHP 解析

### v1.6（2019.04.25）

- 优化二维码识别，使用 js 解析，解决部分二维码识别返回 false 问题

### v1.5（2019.04.24）

- 同步最新版 APP
- 添加注意事项说明，完善 README.md 文档

### v1.4.1（2019.04.22）

- 修复删除未支付状态的订单时不自动释放锁定金额的问题
- 修复创建订单时返回的二维码与支付方式不符合的问题

### v1.4（2019.04.21）

- 修复订单过期不自动释放锁定金额的问题
- 修复订单超出负荷问题

### v1.3（2019.04.20）

- 删除数据库文件中的默认系统设置，防止误导用户
- 更新监控 App 到 v1.3 版本，趋于稳定

### v1.2（2019.04.19）

- 整理代码，重新优化 APP 兼容性
- 添加店员到账支持，可实现安卓备用机/模拟器挂小号取收款通知，方便 iOS 用户（微信绑定店员：收付款→二维码收款→收款小账本→添加店员接收通知；支付宝绑定店员：我的→商家服务→店员通→立即添加）
- 服务端修复一堆 BUG，建议更新到此版本

### v1.1.1（2019.04.19）

- 修复后台点击补单成功但订单未置为成功状态
- 修复后台首页金额统计保留两位小数
- 修复修改系统设置引发的监控端状态重置问题
- 新增创建订单 API 的 `notifyUrl` / `returnUrl` 参数

### v1.1（2019.04.18）

- 打包 thinkphp 框架上传

### v1.0（2019.04.18）

- PHP 初版发布
