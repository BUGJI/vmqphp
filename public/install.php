<?php
/**
 * V免签 PHP 安装向导
 * --------------------
 * 自包含单文件，不依赖 ThinkPHP 框架，用于协助完成部署配置：
 *   1. 环境检测（PHP 版本 / 扩展 / open_basedir / 目录权限）
 *   2. 数据库配置（MySQL 或 SQLite，自动写入 config/database.php）
 *   3. 自动建表 + 初始化后台账号密码
 *
 * ⚠ 安全提示：安装完成后请立即删除本文件！否则任何访问者都可能重装系统。
 *   删除方式：点击下方"立即删除"按钮，或通过 FTP / 宝塔面板手动删除。
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
date_default_timezone_set('Asia/Shanghai');

define('INSTALL_ROOT', dirname(__DIR__));                  // 项目根目录（public 的上级）
define('CONFIG_FILE', INSTALL_ROOT . '/config/database.php');

/* ================= 工具函数 ================= */

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function env_checks() {
    $checks = [];
    $checks[] = ['PHP 版本 >= 5.6', version_compare(PHP_VERSION, '5.6.0', '>='), '当前 ' . PHP_VERSION];
    $checks[] = ['PDO 扩展', extension_loaded('pdo'), extension_loaded('pdo') ? '已开启' : '未开启'];
    $checks[] = ['pdo_mysql（MySQL 模式需要）', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? '已开启' : '未开启（如用 SQLite 可忽略）'];
    $checks[] = ['pdo_sqlite（SQLite 模式需要）', extension_loaded('pdo_sqlite'), extension_loaded('pdo_sqlite') ? '已开启' : '未开启（如用 MySQL 可忽略）'];
    $checks[] = ['GD 库（二维码识别）', function_exists('gd_info'), function_exists('gd_info') ? '已开启' : '未开启'];
    $checks[] = ['curl 扩展（回调通知）', function_exists('curl_init'), function_exists('curl_init') ? '已开启' : '未开启'];

    $ob = ini_get('open_basedir');
    if ($ob) {
        $ok = (strpos($ob, INSTALL_ROOT) !== false);
        $checks[] = ['open_basedir 未限制在 public 内', $ok, $ok ? '未限制' : '受限：' . $ob . '（请在宝塔 网站-设置-网站目录 关闭"防跨站攻击(open_basedir)"，否则框架无法运行）'];
    } else {
        $checks[] = ['open_basedir 未限制在 public 内', true, '未设置'];
    }

    $checks[] = ['config 目录可写（写入 database.php）', is_writable(INSTALL_ROOT . '/config'), is_writable(INSTALL_ROOT . '/config') ? '可写' : '不可写'];
    $dataDir = INSTALL_ROOT . '/data';
    if (!is_dir($dataDir)) { @mkdir($dataDir, 0755, true); }
    $checks[] = ['data 目录可写（SQLite 数据库文件）', is_dir($dataDir) && is_writable($dataDir), is_dir($dataDir) && is_writable($dataDir) ? '可写' : '不可写'];

    return $checks;
}

/** 读取当前 database.php 配置（可能不存在或解析失败） */
function current_db_config() {
    if (!is_file(CONFIG_FILE)) return null;
    $cfg = @include CONFIG_FILE;
    return is_array($cfg) ? $cfg : null;
}

/** 检测是否已安装：配置存在且能连上库且 setting 表有数据 */
function detect_installed() {
    $cfg = current_db_config();
    if (!$cfg) return false;
    try {
        $dsn = strtolower($cfg['type']) === 'sqlite'
            ? 'sqlite:' . $cfg['database']
            : "mysql:host={$cfg['hostname']};port={$cfg['hostport']};dbname={$cfg['database']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_TIMEOUT => 5]);
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM setting");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['c'] > 0;
    } catch (Throwable $t) {
        return false;
    }
}

/** 写入 config/database.php（保留 TP5.1 完整配置结构） */
function write_config($type, $host, $port, $name, $user, $pass) {
    $cfg = [
        'type'            => $type,
        'hostname'        => $host,
        'database'        => $name,
        'username'        => $user,
        'password'        => $pass,
        'hostport'        => (int)$port,
        'dsn'             => '',
        'params'          => [],
        'charset'         => 'utf8',
        'prefix'          => '',
        'debug'           => true,
        'deploy'          => 0,
        'rw_separate'     => false,
        'master_num'      => 1,
        'slave_no'        => '',
        'read_master'     => false,
        'fields_strict'   => true,
        'resultset_type'  => 'array',
        'auto_timestamp'  => false,
        'datetime_format' => 'Y-m-d H:i:s',
        'sql_explain'     => false,
        'builder'         => '',
        'query'           => '\\think\\db\\Query',
        'break_reconnect' => false,
        'break_match_str' => [],
    ];
    $php = "<?php\n// +----------------------------------------------------------------------\n// | 数据库配置（由 install.php 生成，修改时间 " . date('Y-m-d H:i:s') . "）\n// +----------------------------------------------------------------------\n\nreturn " . var_export($cfg, true) . ";\n";
    if (is_file(CONFIG_FILE)) {
        @copy(CONFIG_FILE, CONFIG_FILE . '.bak');
    }
    return file_put_contents(CONFIG_FILE, $php) !== false;
}

/** 测试 MySQL 连接；库不存在时尝试自动创建 */
function test_mysql($host, $port, $name, $user, $pass) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
        return [true, $pdo, ''];
    } catch (Throwable $e) {
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARACTER SET utf8");
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8", $user, $pass);
            return [true, $pdo, ''];
        } catch (Throwable $e2) {
            return [false, null, $e2->getMessage()];
        }
    }
}

/** 测试 SQLite 路径可用 */
function test_sqlite($path) {
    if (strpos($path, __DIR__) === 0) {
        return [false, null, '数据库文件不能放在网站根目录（public）内，否则会被直接下载！建议放在项目根/data/ 下，如：' . INSTALL_ROOT . '/data/vmq.db'];
    }
    $dir = dirname($path);
    if (!is_dir($dir)) { if (!@mkdir($dir, 0755, true)) return [false, null, '目录不存在且无法创建：' . $dir]; }
    if (!is_writable($dir)) return [false, null, '目录不可写：' . $dir];
    try {
        $pdo = new PDO('sqlite:' . $path);
        return [true, $pdo, ''];
    } catch (Throwable $e) {
        return [false, null, $e->getMessage()];
    }
}

/* ================= 建表 SQL ================= */

function mysql_tables() {
    return [
        "CREATE TABLE IF NOT EXISTS `pay_order` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `close_date` bigint(20) NOT NULL,
          `create_date` bigint(20) NOT NULL,
          `is_auto` int(11) NOT NULL,
          `notify_url` varchar(255) DEFAULT NULL,
          `order_id` varchar(255) DEFAULT NULL,
          `param` varchar(255) DEFAULT NULL,
          `pay_date` bigint(20) NOT NULL,
          `pay_id` varchar(255) DEFAULT NULL,
          `pay_url` varchar(255) DEFAULT NULL,
          `price` double NOT NULL,
          `really_price` double NOT NULL,
          `return_url` varchar(255) DEFAULT NULL,
          `state` int(11) NOT NULL,
          `type` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `pay_qrcode` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `pay_url` varchar(255) DEFAULT NULL,
          `price` double NOT NULL,
          `type` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `setting` (
          `vkey` varchar(255) NOT NULL,
          `vvalue` varchar(255) DEFAULT NULL,
          PRIMARY KEY (`vkey`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
        "CREATE TABLE IF NOT EXISTS `tmp_price` (
          `price` varchar(255) NOT NULL,
          `oid` varchar(255) NOT NULL,
          PRIMARY KEY (`price`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8",
    ];
}

function sqlite_tables() {
    return [
        "CREATE TABLE IF NOT EXISTS pay_order (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          close_date INTEGER NOT NULL,
          create_date INTEGER NOT NULL,
          is_auto INTEGER NOT NULL,
          notify_url TEXT,
          order_id TEXT,
          param TEXT,
          pay_date INTEGER NOT NULL,
          pay_id TEXT,
          pay_url TEXT,
          price REAL NOT NULL,
          really_price REAL NOT NULL,
          return_url TEXT,
          state INTEGER NOT NULL,
          type INTEGER NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS pay_qrcode (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          pay_url TEXT,
          price REAL NOT NULL,
          type INTEGER NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS setting (
          vkey TEXT PRIMARY KEY,
          vvalue TEXT
        )",
        "CREATE TABLE IF NOT EXISTS tmp_price (
          price TEXT PRIMARY KEY,
          oid TEXT NOT NULL
        )",
    ];
}

function seed_setting($pdo, $type, $adminUser, $adminPass) {
    $items = [
        'user'       => $adminUser,
        'pass'       => $adminPass,
        'notifyUrl'  => '',
        'returnUrl'  => '',
        'key'        => md5(uniqid(mt_rand(), true)),
        'lastheart'  => '0',
        'lastpay'    => '0',
        'jkstate'    => '-1',
        'close'      => '5',
        'payQf'      => '1',
        'wxpay'      => '',
        'zfbpay'     => '',
    ];
    foreach ($items as $k => $v) {
        if (strtolower($type) === 'sqlite') {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO setting (vkey, vvalue) VALUES (?, ?)");
        } else {
            $stmt = $pdo->prepare("REPLACE INTO `setting` (`vkey`, `vvalue`) VALUES (?, ?)");
        }
        $stmt->execute([$k, $v]);
    }
}

/* ================= 页面流程 ================= */

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$errors = [];
$success = false;
$installed = detect_installed();

// 删除 install.php 自身
if ($action === 'delete') {
    $deleted = @unlink(__FILE__);
    $deleteMsg = $deleted ? 'install.php 已成功删除！' : '删除失败（文件权限不足），请通过 FTP / 宝塔面板手动删除 public/install.php。';
    $installed = detect_installed();
}

// 处理安装提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'install') {
    $dbType = $_POST['db_type'] === 'sqlite' ? 'sqlite' : 'mysql';
    $adminUser = trim($_POST['admin_user'] !== '' ? $_POST['admin_user'] : 'admin');
    $adminPass = $_POST['admin_pass'] !== '' ? $_POST['admin_pass'] : 'admin';

    if ($dbType === 'mysql') {
        $dbHost = trim($_POST['db_host'] !== '' ? $_POST['db_host'] : '127.0.0.1');
        $dbPort = trim($_POST['db_port'] !== '' ? $_POST['db_port'] : '3306');
        $dbName = trim($_POST['db_name']);
        $dbUser = trim($_POST['db_user']);
        $dbPass = (string)$_POST['db_pass'];
        if ($dbName === '') { $errors[] = '请填写数据库名'; }
        if ($dbUser === '') { $errors[] = '请填写数据库用户名'; }
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $dbName)) { $errors[] = '数据库名只能包含字母、数字、下划线、中划线'; }
        if (!$errors) {
            list($ok, $pdo, $err) = test_mysql($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
            if (!$ok) { $errors[] = '数据库连接失败：' . $err . '（如数据库不存在，请确认账号有创建库权限，或先在宝塔中创建）'; }
            else {
                foreach (mysql_tables() as $sql) { $pdo->exec($sql); }
                seed_setting($pdo, 'mysql', $adminUser, $adminPass);
                if (write_config('mysql', $dbHost, $dbPort, $dbName, $dbUser, $dbPass)) { $success = true; }
                else { $errors[] = 'config/database.php 写入失败，请检查 config 目录权限'; }
            }
        }
    } else {
        $dbPath = trim($_POST['sqlite_path']);
        if ($dbPath === '') { $dbPath = INSTALL_ROOT . '/data/vmq.db'; }
        if (!$errors) {
            list($ok, $pdo, $err) = test_sqlite($dbPath);
            if (!$ok) { $errors[] = 'SQLite 初始化失败：' . $err; }
            else {
                foreach (sqlite_tables() as $sql) { $pdo->exec($sql); }
                seed_setting($pdo, 'sqlite', $adminUser, $adminPass);
                if (write_config('sqlite', '', '', $dbPath, '', '')) { $success = true; }
                else { $errors[] = 'config/database.php 写入失败，请检查 config 目录权限'; }
            }
        }
    }
    if ($success) { $installed = true; }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V免签 安装向导</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font: 14px/1.7 "Microsoft YaHei", Arial, sans-serif; background: #f0f2f5; color: #333; padding: 30px 12px; }
.wrap { max-width: 760px; margin: 0 auto; }
.card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.08); padding: 24px 28px; margin-bottom: 18px; }
h1 { font-size: 22px; margin-bottom: 4px; }
.sub { color: #888; font-size: 13px; margin-bottom: 16px; }
.alert { padding: 10px 14px; border-radius: 6px; margin: 12px 0; font-size: 13px; }
.alert-danger { background: #fdecea; color: #b71c1c; border: 1px solid #f5c6cb; }
.alert-warning { background: #fff8e1; color: #8a6d00; border: 1px solid #ffe08a; }
.alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
table.checks { width: 100%; border-collapse: collapse; margin: 8px 0; }
table.checks td { padding: 6px 8px; border-bottom: 1px solid #f0f0f0; }
table.checks td:first-child { width: 45%; }
.ok { color: #2e7d32; font-weight: bold; }
.bad { color: #c62828; font-weight: bold; }
label { display: block; margin: 14px 0 4px; font-weight: bold; font-size: 13px; }
input[type=text], input[type=password], select { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
.row { display: flex; gap: 10px; }
.row > div { flex: 1; }
.btn { display: inline-block; padding: 10px 22px; border: 0; border-radius: 4px; font-size: 14px; cursor: pointer; text-decoration: none; }
.btn-primary { background: #1a73e8; color: #fff; }
.btn-danger { background: #d32f2f; color: #fff; }
.btn-green { background: #2e7d32; color: #fff; }
.btn-gray { background: #9e9e9e; color: #fff; }
.btn:hover { opacity: .9; }
.hint { font-size: 12px; color: #999; margin-top: 4px; }
.footer { text-align: center; color: #aaa; font-size: 12px; margin-top: 14px; }
.step { display: inline-block; background: #1a73e8; color: #fff; border-radius: 50%; width: 22px; height: 22px; line-height: 22px; text-align: center; font-size: 12px; margin-right: 6px; }
</style>
</head>
<body>
<div class="wrap">

<?php if (isset($deleteMsg)): ?>
<div class="card">
    <h1>安装向导</h1>
    <div class="alert alert-<?php echo $deleted ? 'success' : 'danger'; ?>"><?php echo e($deleteMsg); ?></div>
    <p><a class="btn btn-primary" href="index.html">进入后台登录页</a></p>
</div>
<?php elseif ($success): ?>
<div class="card">
    <h1>✅ 安装成功</h1>
    <div class="alert alert-danger"><b>⚠ 重要：</b>请<b>立即删除 public/install.php</b>！本文件是重装入口，保留期间任何人都可以访问并重置你的系统数据。</div>
    <div class="alert alert-success">
        数据库配置已写入 <code>config/database.php</code>，数据表已创建完毕。<br>
        后台账号：<b><?php echo e($adminUser); ?></b>，密码：<b><?php echo e($adminPass); ?></b><br>
        建议登录后立即在「系统设置」中修改密码。
    </div>
    <p>
        <a class="btn btn-danger" href="install.php?action=delete" onclick="return confirm('确认删除 install.php？');">立即删除 install.php</a>
        <a class="btn btn-primary" href="index.html">进入后台登录</a>
    </p>
    <p class="hint">如果"立即删除"失败，请通过 FTP 或宝塔面板手动删除 <code>public/install.php</code>。</p>
</div>
<?php elseif ($installed): ?>
<div class="card">
    <h1>检测到已安装</h1>
    <div class="alert alert-success">系统已检测到数据库配置与数据表均正常，无需重复安装。</div>
    <div class="alert alert-danger"><b>⚠ 安全提醒：</b>当前仍存在 <code>public/install.php</code>（重装入口），请立即删除以防他人重置系统。</div>
    <p>
        <a class="btn btn-danger" href="install.php?action=delete" onclick="return confirm('确认删除 install.php？');">立即删除 install.php</a>
        <a class="btn btn-primary" href="index.html">进入后台登录</a>
    </p>
</div>
<?php else: ?>

<div class="card">
    <h1>V免签 安装向导</h1>
    <p class="sub">协助完成数据库配置与初始化，全程无需手动编辑代码。</p>
    <div class="alert alert-danger"><b>⚠ 防呆提醒：</b>安装完成后<b>请立即删除 public/install.php</b>！本文件是重装入口，保留期间任何访问者都能重装并重置你的系统数据。</div>

    <h2 style="font-size:16px;margin:14px 0 6px;"><span class="step">1</span>环境检测</h2>
    <table class="checks">
        <?php foreach (env_checks() as $c): ?>
        <tr><td><?php echo e($c[0]); ?></td><td class="<?php echo $c[1] ? 'ok' : 'bad'; ?>"><?php echo $c[1] ? '✔' : '✘'; ?> <?php echo e($c[2]); ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2 style="font-size:16px;margin-bottom:10px;"><span class="step">2</span>数据库配置</h2>
    <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $er) echo '<div>✘ ' . e($er) . '</div>'; ?></div>
    <?php endif; ?>

    <form method="post" action="install.php">
        <input type="hidden" name="action" value="install">

        <label>数据库类型</label>
        <select name="db_type" id="db_type" onchange="toggleDb()">
            <option value="mysql">MySQL（宝塔等服务器环境，默认推荐）</option>
            <option value="sqlite">SQLite（零配置，无需安装数据库，适合轻量部署）</option>
        </select>

        <div id="mysql_fields">
            <div class="row">
                <div>
                    <label>MySQL 主机</label>
                    <input type="text" name="db_host" value="127.0.0.1">
                </div>
                <div>
                    <label>端口</label>
                    <input type="text" name="db_port" value="3306">
                </div>
            </div>
            <label>数据库名（不存在时尝试自动创建）</label>
            <input type="text" name="db_name" placeholder="例如 vmq">
            <div class="row">
                <div>
                    <label>用户名</label>
                    <input type="text" name="db_user" placeholder="数据库账号">
                </div>
                <div>
                    <label>密码</label>
                    <input type="password" name="db_pass" placeholder="数据库密码">
                </div>
            </div>
        </div>

        <div id="sqlite_fields" style="display:none;">
            <label>SQLite 数据库文件路径</label>
            <input type="text" name="sqlite_path" value="<?php echo e(INSTALL_ROOT . '/data/vmq.db'); ?>">
            <p class="hint">默认存放在网站根目录之外的 data/ 目录，请勿放在 public 内（会被直接下载）。</p>
        </div>

        <h2 style="font-size:16px;margin:18px 0 6px;"><span class="step">3</span>后台管理员</h2>
        <div class="row">
            <div>
                <label>登录账号</label>
                <input type="text" name="admin_user" value="admin">
            </div>
            <div>
                <label>登录密码</label>
                <input type="password" name="admin_pass" placeholder="默认 admin">
            </div>
        </div>
        <p class="hint">密码留空则使用默认 admin；安装后可在后台「系统设置」修改。</p>

        <p style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">开始安装</button>
            <a class="btn btn-gray" href="index.html" style="margin-left:8px;">暂不安装，返回首页</a>
        </p>
    </form>
</div>

<script>
function toggleDb() {
    var t = document.getElementById('db_type').value;
    document.getElementById('mysql_fields').style.display = (t === 'mysql') ? '' : 'none';
    document.getElementById('sqlite_fields').style.display = (t === 'sqlite') ? '' : 'none';
}
</script>

<?php endif; ?>

<div class="footer">V免签 PHP 安装向导 · ThinkPHP <?php echo e(defined('THINK_VERSION') ? THINK_VERSION : '5.1'); ?></div>
</div>
</body>
</html>
