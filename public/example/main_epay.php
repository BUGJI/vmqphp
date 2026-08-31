<?php
/**
 * 易支付协议测试入口（对应后台「接口模式=易支付格式」）
 * 对接参数与彩虹易支付 submit.php 一致：
 *   pid(固定1) & type(wxpay/alipay) & notify_url & return_url & out_trade_no & name & money & sign
 *   sign = md5(参数按字母排序 "k=v&" 拼接 + 通讯密钥)
 */
ini_set("error_reporting","E_ALL & ~E_NOTICE");

$key = "aa15188ce0f1d97018524d9862ef2a46";//通讯密钥（与 main.php 一致）
$pid = "1";//商户ID（易支付模式固定为1）
$host = "../epaySubmit";

$type = ($_GET['type']=='1' ? 'wxpay' : 'alipay');
$out_trade_no = $_GET['payId'];
$name = $_GET['param'];
$money = $_GET['price'];
$notify_url = "http://vmq.bugcode.cc/example/notify.php";   // 示例异步回调接收端
$return_url = "http://vmq.bugcode.cc/example/return.php";   // 示例同步跳转接收端

// 易支付标准签名
$params = array(
    "pid" => $pid,
    "type" => $type,
    "notify_url" => $notify_url,
    "return_url" => $return_url,
    "out_trade_no" => $out_trade_no,
    "name" => $name,
    "money" => $money
);
ksort($params);
$signstr = "";
foreach ($params as $k => $v) {
    $signstr .= $k . "=" . $v . "&";
}
$signstr = substr($signstr, 0, -1) . $key;
$sign = md5($signstr);

$params['sign'] = $sign;
$p = http_build_query($params);
echo "<script>window.location.href = '" . $host . "?" . $p . "'</script>";
