<?php
/**
 * 示例回调接收端（V免签 / 易支付 双格式智能验签）
 * - V免签格式：payId/param/type/price/reallyPrice，sign = md5(payId.param.type.price.reallyPrice.key)
 * - 易支付格式：pid/trade_no/out_trade_no/type/name/money/trade_status，sign = md5(参数ksort拼接+key)
 * - key 自动从 config/database.php 读取（MySQL / SQLite 兼容），无需手动改文件
 */
ini_set("error_reporting","E_ALL & ~E_NOTICE");

/** 获取通讯密钥：优先回调参数，其次数据库 setting 表，最后默认值 */
function vmq_get_key(){
    if (isset($_GET['key']) && $_GET['key'] != '') return $_GET['key'];
    try {
        $cfg = require __DIR__ . '/../../config/database.php';
        if (strtolower($cfg['type']) === 'sqlite') {
            $pdo = new PDO('sqlite:' . $cfg['database']);
        } else {
            $pdo = new PDO("mysql:host={$cfg['hostname']};port={$cfg['hostport']};dbname={$cfg['database']};charset={$cfg['charset']}", $cfg['username'], $cfg['password']);
        }
        $v = $pdo->query("SELECT vvalue FROM setting WHERE vkey='key'")->fetchColumn();
        if ($v) return $v;
    } catch (Exception $e) {}
    return "aa15188ce0f1d97018524d9862ef2a46"; // 兜底
}

$key = vmq_get_key();

// 自动识别回调格式
if (isset($_GET['trade_no']) && isset($_GET['out_trade_no'])) {
    // 易支付格式验签
    $params = $_GET;
    unset($params['sign'], $params['sign_type'], $params['key']);
    ksort($params);
    $signstr = '';
    foreach ($params as $k => $v) {
        if ($v != '') $signstr .= $k . '=' . $v . '&';
    }
    $signstr = substr($signstr, 0, -1) . $key;
    $_sign = md5($signstr);
} else {
    // V免签格式验签
    $_sign = md5($_GET['payId'] . $_GET['param'] . $_GET['type'] . $_GET['price'] . $_GET['reallyPrice'] . $key);
}

if ($_sign != $_GET['sign']) {
    echo "error_sign";//sign校验不通过
    exit();
}

echo "success";

// 继续业务流程
// echo "商户订单号：" . $_GET['payId'] . "<br>自定义参数：" . $_GET['param'] . "<br>支付方式：" . $_GET['type'] . "<br>订单金额：" . $_GET['price'] . "<br>实际支付金额：" . $_GET['reallyPrice'];
