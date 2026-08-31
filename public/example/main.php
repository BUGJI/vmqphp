<?php
ini_set("error_reporting","E_ALL & ~E_NOTICE");

$key = isset($_GET['key']) && $_GET['key'] != '' ? $_GET['key'] : "aa15188ce0f1d97018524d9862ef2a46";//通讯密钥（可用页面传入，默认示例密钥）
$host = "../createOrder";

$sign = md5($_GET['payId'].$_GET['param'].$_GET['type'].$_GET['price'].$key);
$p = "payId=".$_GET['payId'].'&param='.$_GET['param'].'&type='.$_GET['type']."&price=".$_GET['price'].'&sign='.$sign.'&isHtml=1';

echo "<script>window.location.href = '".$host."?".$p."'</script>";

