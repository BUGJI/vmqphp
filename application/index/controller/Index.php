<?php
namespace app\index\controller;

use think\Db;
use think\facade\Session;

class Index
{
    public function index()
    {

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>存在搭建问题</title>
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
</head>
<body class="body">
<div style="padding: 15px;color: red;">
    <h1 style="text-align: center">检测到默认文档未设定成index.html</h1><br><br>
    <h1 style="text-align: center">请在宝塔面板-网站-设置-默认文档->将index.html放到第一行并保存！</h1><br><br>
</div>
</body>
</html>
';
    }


    public function getReturn($code = 1, $msg = "成功", $data = null)
    {
        return array("code" => $code, "msg" => $msg, "data" => $data);
    }

    //后台用户登录
    public function login()
    {
        $user = input("user");
        $pass = input("pass");

        $_user = Db::name("setting")->where("vkey", "user")->find();
        if ($user != $_user["vvalue"]) {
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        $_pass = Db::name("setting")->where("vkey", "pass")->find();
        if ($pass != $_pass["vvalue"]) {
            return json($this->getReturn(-1, "账号或密码错误"));
        }

        Session::set("admin", 1);

        return json($this->getReturn());
    }


    //后台菜单
    public function getMenu()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }


        $menu = array(
            array(
                "name" => "系统设置",
                "type" => "url",
                "url" => "admin/setting.html?t=" . time(),
            ),
            array(
                "name" => "监控端设置",
                "type" => "url",
                "url" => "admin/jk.html?t=" . time(),
            ),
            array(
                "name" => "微信二维码",
                "type" => "menu",
                "node" => array(
                    array(
                        "name" => "添加",
                        "type" => "url",
                        "url" => "admin/addwxqrcode.html?t=" . time(),
                    ),
                    array(
                        "name" => "管理",
                        "type" => "url",
                        "url" => "admin/wxqrcodelist.html?t=" . time(),
                    )
                ),
            ), array(
                "name" => "支付宝二维码",
                "type" => "menu",
                "node" => array(
                    array(
                        "name" => "添加",
                        "type" => "url",
                        "url" => "admin/addzfbqrcode.html?t=" . time(),
                    ),
                    array(
                        "name" => "管理",
                        "type" => "url",
                        "url" => "admin/zfbqrcodelist.html?t=" . time(),
                    )
                ),
            ), array(
                "name" => "订单列表",
                "type" => "url",
                "url" => "admin/orderlist.html?t=" . time(),
            ), array(
                "name" => "Api说明",
                "type" => "url",
                "url" => "api.html?t=" . time(),
            )
        );

        return json($menu);

    }

    //创建订单
    public function createOrder()
    {
        $this->closeEndOrder();

        // 接口模式检查：易支付工作模式下请使用 epaySubmit
        $apiMode = Db::name("setting")->where("vkey","apiMode")->find();
        if ($apiMode && $apiMode['vvalue']=="1"){
            return json($this->getReturn(-1,"当前为易支付工作模式，请使用 epaySubmit 接口"));
        }

        $payId = input("payId");
        if (!$payId || $payId == "") {
            return json($this->getReturn(-1, "请传入商户订单号"));
        }
        $type = input("type");
        if (!$type || $type == "") {
            return json($this->getReturn(-1, "请传入支付方式=>1|微信 2|支付宝"));
        }
        if ($type != 1 && $type != 2) {
            return json($this->getReturn(-1, "支付方式错误=>1|微信 2|支付宝"));
        }

        $price = input("price");
        if (!$price || $price == "") {
            return json($this->getReturn(-1, "请传入订单金额"));
        }
        if ($price <= 0) {
            return json($this->getReturn(-1, "订单金额必须大于0"));
        }

        $sign = input("sign");
        if (!$sign || $sign == "") {
            return json($this->getReturn(-1, "请传入签名"));
        }

        $isHtml = input("isHtml");
        if (!$isHtml || $isHtml == "") {
            $isHtml = 0;
        }
        $param = input("param");
        if (!$param) {
            $param = "";
        }

        $res = Db::name("setting")->where("vkey", "key")->find();
        $key = $res['vvalue'];

        if (input("notifyUrl")) {
            $notify_url = input("notifyUrl");
        } else {
            $res = Db::name("setting")->where("vkey", "notifyUrl")->find();
            $notify_url = $res['vvalue'];
        }

        if (input("returnUrl")) {
            $return_url = input("returnUrl");
        } else {
            $res = Db::name("setting")->where("vkey", "returnUrl")->find();
            $return_url = $res['vvalue'];
        }


        $_sign = md5($payId . $param . $type . $price . $key);
        if ($sign != $_sign) {
            return json($this->getReturn(-1, "签名错误"));
        }

        $result = $this->_createOrderCore($payId, $type, $price, $param, $notify_url, $return_url);
        if (!$result['ok']) {
            return json($this->getReturn(-1, $result['msg']));
        }

        if ($isHtml == 1) {

            echo "<script>window.location.href = 'payPage/pay.html?orderId=" . $result['data']['orderId'] . "'</script>";

        } else {
            return json($this->getReturn(1, "成功", $result['data']));
        }
    }

    /**
     * 建单核心（createOrder / epaySubmit 共用）
     * @param string $payId 商户订单号
     * @param int    $type 支付方式 1微信 2支付宝
     * @param string $price 订单金额
     * @param string $param 自定义参数
     * @param string $notify_url 异步回调地址
     * @param string $return_url 同步回调地址
     * @return array ['ok'=>true,'data'=>array] 或 ['ok'=>false,'msg'=>string]
     */
    private function _createOrderCore($payId, $type, $price, $param, $notify_url, $return_url)
    {
        $jkstate = Db::name("setting")->where("vkey", "jkstate")->find();
        $jkstate = $jkstate['vvalue'];
        if ($jkstate!="1"){
            return array("ok"=>false,"msg"=>"监控端状态异常，请检查");

        }



        $reallyPrice = bcmul($price ,100);

        $payQf = Db::name("setting")->where("vkey", "payQf")->find();
        $payQf = $payQf['vvalue'];


        $orderId = date("YmdHms") . rand(1, 9) . rand(1, 9) . rand(1, 9) . rand(1, 9);

        $ok = false;
        for ($i = 0; $i < 10; $i++) {
            $tmpPrice = $reallyPrice . "-" . $type;

            if (strtolower(config('database.type')) == 'sqlite') {
                $row = Db::execute("INSERT OR IGNORE INTO tmp_price (price,oid) VALUES ('" . $tmpPrice . "','".$orderId."')");
            } else {
                $row = Db::execute("INSERT IGNORE INTO tmp_price (price,oid) VALUES ('" . $tmpPrice . "','".$orderId."')");
            }
            if ($row) {
                $ok = true;
                break;
            }
            if ($payQf == 1) {
                $reallyPrice++;
            } else if ($payQf == 2) {
                $reallyPrice--;
            }
        }

        if (!$ok) {
            return array("ok"=>false,"msg"=>"订单超出负荷，请稍后重试");
        }
        //echo $reallyPrice;

        $reallyPrice = bcdiv($reallyPrice, 100,2);

        if ($type == 1) {
            $payUrl = Db::name("setting")->where("vkey", "wxpay")->find();
            $payUrl = $payUrl['vvalue'];

        } else if ($type == 2) {
            $payUrl = Db::name("setting")->where("vkey", "zfbpay")->find();
            $payUrl = $payUrl['vvalue'];
        }

        if ($payUrl == "") {
            return array("ok"=>false,"msg"=>"请您先进入后台配置程序");
        }
        $isAuto = 1;
        $_payUrl = Db::name("pay_qrcode")
            ->where("price", $reallyPrice)
            ->where("type", $type)
            ->find();
        if ($_payUrl) {
            $payUrl = $_payUrl['pay_url'];
            $isAuto = 0;
        }


        $res = Db::name("pay_order")->where("pay_id", $payId)->find();
        if ($res) {
            return array("ok"=>false,"msg"=>"商户订单号已存在");
        }




        $createDate = time();
        $data = array(
            "close_date" => 0,
            "create_date" => $createDate,
            "is_auto" => $isAuto,
            "notify_url" => $notify_url,
            "order_id" => $orderId,
            "param" => $param,
            "pay_date" => 0,
            "pay_id" => $payId,
            "pay_url" => $payUrl,
            "price" => $price,
            "really_price" => $reallyPrice,
            "return_url" => $return_url,
            "state" => 0,
            "type" => $type

        );


        Db::name("pay_order")->insert($data);

        $time = Db::name("setting")->where("vkey", "close")->find();
        return array("ok"=>true,"data"=>array(
            "payId" => $payId,
            "orderId" => $orderId,
            "payType" => $type,
            "price" => $price,
            "reallyPrice" => $reallyPrice,
            "payUrl" => $payUrl,
            "isAuto" => $isAuto,
            "state" => 0,
            "timeOut" => $time['vvalue'],
            "date" => $createDate
        ));
    }
    //获取订单信息
    public function getOrder()
    {
        // 支付页 pay.html 内部查单接口（不随接口模式拦截；对外协议互斥只作用于 createOrder/epaySubmit）
        $res = Db::name("pay_order")->where("order_id", input("orderId"))->find();
        if ($res){
            $time = Db::name("setting")->where("vkey", "close")->find();

            $data = array(
                "payId" => $res['pay_id'],
                "orderId" => $res['order_id'],
                "payType" => $res['type'],
                "price" => $res['price'],
                "reallyPrice" => $res['really_price'],
                "payUrl" => $res['pay_url'],
                "isAuto" => $res['is_auto'],
                "state" => $res['state'],
                "timeOut" => $time['vvalue'],
                "date" => $res['create_date']
            );
            return json($this->getReturn(1, "成功", $data));
        }else{
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }
    }
    //查询订单状态
    public function checkOrder()
    {
        $res = Db::name("pay_order")->where("order_id", input("orderId"))->find();
        if ($res){
            if ($res['state']==0){
                return json($this->getReturn(-1, "订单未支付"));
            }
            if ($res['state']==-1){
                return json($this->getReturn(-1, "订单已过期"));
            }

            $res2 = Db::name("setting")->where("vkey","key")->find();
            $key = $res2['vvalue'];

            $res['price'] = number_format($res['price'],2,".","");
            $res['really_price'] = number_format($res['really_price'],2,".","");


            $p = "payId=".$res['pay_id']."&param=".$res['param']."&type=".$res['type']."&price=".$res['price']."&reallyPrice=".$res['really_price'];

            $sign = $res['pay_id'].$res['param'].$res['type'].$res['price'].$res['really_price'].$key;
            $p = $p . "&sign=".md5($sign);

            $url = $res['return_url'];



            if (strpos($url,"?")===false){
                $url = $url."?".$p;
            }else{
                $url = $url."&".$p;
            }

            return json($this->getReturn(1, "成功", $url));
        }else{
            return json($this->getReturn(-1, "云端订单编号不存在"));
        }

    }

    //================= 易支付协议（接口模式=易支付格式时使用） =================

    /**
     * 易支付格式签名：参数按字母排序(ksort)，排除 sign/sign_type 及空值，k=v& 拼接后追加 key，md5
     */
    private function epaySign($param, $key){
        ksort($param);
        $signstr = '';
        foreach($param as $k => $v){
            if ($k != "sign" && $k != "sign_type" && $v != ''){
                $signstr .= $k.'='.$v.'&';
            }
        }
        $signstr = substr($signstr,0,-1);
        $signstr .= $key;
        return md5($signstr);
    }

    /**
     * 易支付协议下单（submit）
     * pid 固定为 1，key 使用后台通讯密钥
     */
    public function epaySubmit(){
        $this->closeEndOrder();

        // 接口模式检查
        $apiMode = Db::name("setting")->where("vkey","apiMode")->find();
        if (!$apiMode || $apiMode['vvalue']!="1"){
            return json($this->getReturn(-1,"当前为V免签工作模式，请使用 createOrder 接口"));
        }

        $pid = input("pid");
        $type = input("type");
        $notify_url = input("notify_url");
        $return_url = input("return_url");
        $out_trade_no = input("out_trade_no");
        $name = input("name");
        $money = input("money");
        $sign = input("sign");
        $isJson = input("isJson");

        if ($pid != "1"){
            return json($this->getReturn(-1,"商户ID不存在"));
        }
        if (!$out_trade_no || $out_trade_no == ""){
            return json($this->getReturn(-1,"请传入商户订单号"));
        }
        if (!$money || $money == "" || !is_numeric($money) || $money <= 0){
            return json($this->getReturn(-1,"请传入正确的订单金额"));
        }
        if (!$type || $type == ""){
            return json($this->getReturn(-1,"请传入支付方式"));
        }
        if (!$sign || $sign == ""){
            return json($this->getReturn(-1,"请传入签名"));
        }
        if ($type != "alipay" && $type != "wxpay"){
            return json($this->getReturn(-1,"不支持的支付方式"));
        }

        // 验签
        $res2 = Db::name("setting")->where("vkey","key")->find();
        $key = $res2['vvalue'];
        $paramArr = array(
            "pid" => $pid,
            "type" => $type,
            "notify_url" => $notify_url,
            "return_url" => $return_url,
            "out_trade_no" => $out_trade_no,
            "name" => $name,
            "money" => $money
        );
        if ($this->epaySign($paramArr, $key) != $sign){
            return json($this->getReturn(-1,"签名错误"));
        }

        // type 映射：alipay->2, wxpay->1
        $t = ($type == "alipay") ? 2 : 1;

        // notify_url / return_url：优先下单参数，空则用后台默认
        if (!$notify_url){
            $res = Db::name("setting")->where("vkey","notifyUrl")->find();
            $notify_url = $res['vvalue'];
        }
        if (!$return_url){
            $res = Db::name("setting")->where("vkey","returnUrl")->find();
            $return_url = $res['vvalue'];
        }

        $result = $this->_createOrderCore($out_trade_no, $t, $money, $name, $notify_url, $return_url);
        if (!$result['ok']){
            return json($this->getReturn(-1, $result['msg']));
        }

        if ($isJson == "1"){
            return json($this->getReturn(1,"成功",$result['data']));
        }else{
            echo "<script>window.location.href = 'payPage/pay.html?orderId=" . $result['data']['orderId'] . "'</script>";
        }
    }

    /**
     * 易支付协议查单（api.php act=order 等价）
     * GET epayOrder?pid=1&key=通讯密钥&out_trade_no=xxx （或 &trade_no=xxx）
     */
    public function epayOrder(){
        $apiMode = Db::name("setting")->where("vkey","apiMode")->find();
        if (!$apiMode || $apiMode['vvalue']!="1"){
            return json($this->getReturn(-1,"当前为V免签工作模式，请使用 getOrder 接口"));
        }

        $pid = input("pid");
        $key = input("key");
        $out_trade_no = input("out_trade_no");
        $trade_no = input("trade_no");

        if ($pid != "1"){
            return json(array("code"=>-3,"msg"=>"商户ID不存在"));
        }
        $res2 = Db::name("setting")->where("vkey","key")->find();
        if ($key != $res2['vvalue']){
            return json(array("code"=>-3,"msg"=>"商户密钥错误"));
        }
        if (!$out_trade_no && !$trade_no){
            return json(array("code"=>-4,"msg"=>"订单号不能为空"));
        }

        if ($trade_no){
            $row = Db::name("pay_order")->where("order_id",$trade_no)->find();
        }else{
            $row = Db::name("pay_order")->where("pay_id",$out_trade_no)->find();
        }
        if (!$row){
            return json(array("code"=>-1,"msg"=>"订单号不存在"));
        }

        return json(array(
            "code" => 1,
            "msg" => "succ",
            "trade_no" => $row['order_id'],
            "out_trade_no" => $row['pay_id'],
            "type" => ($row['type']==1 ? "wxpay" : "alipay"),
            "money" => $row['really_price'],
            "param" => $row['param'],
            "status" => $row['state'],
            "addtime" => $row['create_date']
        ));
    }
    //关闭订单
    public function closeOrder(){
        $res2 = Db::name("setting")->where("vkey","key")->find();
        $key = $res2['vvalue'];
        $orderId = input("orderId");

        $_sign = $orderId.$key;

        if (md5($_sign)!=input("sign")){
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $res = Db::name("pay_order")->where("order_id",$orderId)->find();

        if ($res){
            if ($res['state']!=0){
                return json($this->getReturn(-1, "订单状态不允许关闭"));
            }
            Db::name("pay_order")->where("order_id",$orderId)->update(array("state"=>-1,"close_date"=>time()));
            Db::name("tmp_price")
                ->where("oid",$res['order_id'])
                ->delete();
            return json($this->getReturn(1, "成功"));
        }else{
            return json($this->getReturn(-1, "云端订单编号不存在"));

        }

    }
    //获取监控端状态
    public function getState(){
        $res2 = Db::name("setting")->where("vkey","key")->find();
        $key = $res2['vvalue'];
        $t = input("t");

        $_sign = $t.$key;

        if (md5($_sign)!=input("sign")){
            return json($this->getReturn(-1, "签名校验不通过"));
        }

        $res = Db::name("setting")->where("vkey","lastheart")->find();
        $lastheart = $res['vvalue'];
        $res = Db::name("setting")->where("vkey","lastpay")->find();
        $lastpay = $res['vvalue'];
        $res = Db::name("setting")->where("vkey","jkstate")->find();
        $jkstate = $res['vvalue'];

        return json($this->getReturn(1, "成功",array("lastheart"=>$lastheart,"lastpay"=>$lastpay,"jkstate"=>$jkstate)));

    }

    //App心跳接口
    public function appHeart(){
        $this->closeEndOrder();

        $res2 = Db::name("setting")->where("vkey","key")->find();
        $key = $res2['vvalue'];
        $t = input("t");

        $_sign = $t.$key;

        if (md5($_sign)!=input("sign")){
            return json($this->getReturn(-1, "签名校验不通过"));
        }

//        $jg = time()*1000 - $t;
//        if ($jg>50000 || $jg<-50000){
//            return json($this->getReturn(-1, "客户端时间错误"));
//        }

        Db::name("setting")->where("vkey","lastheart")->update(array("vvalue"=>time()));
        Db::name("setting")->where("vkey","jkstate")->update(array("vvalue"=>1));
        return json($this->getReturn());
    }
    //App推送付款数据接口
    public function appPush(){
        $this->closeEndOrder();

        $res2 = Db::name("setting")->where("vkey","key")->find();
        $key = $res2['vvalue'];
        $t = input("t");
        $type = input("type");
        $price = input("price");

        $_sign = $type.$price.$t.$key;

        if (md5($_sign)!=input("sign")){
            return json($this->getReturn(-1, "签名校验不通过"));
        }

//        $jg = time()*1000 - $t;
//        if ($jg>50000 || $jg<-50000){
//            return json($this->getReturn(-1, "客户端时间错误"));
//        }

        Db::name("setting")
            ->where("vkey","lastpay")
            ->update(
                array(
                    "vvalue"=>time()
                )
            );

        $res = Db::name("pay_order")
            ->where("really_price",$price)
            ->where("state",0)
            ->where("type",$type)
            ->find();



        if ($res){

            Db::name("tmp_price")
                ->where("oid",$res['order_id'])
                ->delete();

            Db::name("pay_order")->where("id",$res['id'])->update(array("state"=>1,"pay_date"=>time(),"close_date"=>time()));

            $url = $res['notify_url'];

            $res2 = Db::name("setting")->where("vkey","key")->find();
            $key = $res2['vvalue'];

            // 接口模式分流：易支付格式回调
            $apiMode = Db::name("setting")->where("vkey","apiMode")->find();
            if ($apiMode && $apiMode['vvalue']=="1"){
                $p = array(
                    "pid" => "1",
                    "trade_no" => $res['order_id'],
                    "out_trade_no" => $res['pay_id'],
                    "type" => ($res['type']==1 ? "wxpay" : "alipay"),
                    "name" => $res['param'],
                    "money" => $res['really_price'],
                    "trade_status" => "TRADE_SUCCESS"
                );
                $p['sign'] = $this->epaySign($p, $key);
                if (strpos($url,"?")===false){
                    $url = $url."?".http_build_query($p);
                }else{
                    $url = $url."&".http_build_query($p);
                }
            }else{
                $p = "payId=".$res['pay_id']."&param=".$res['param']."&type=".$res['type']."&price=".$res['price']."&reallyPrice=".$res['really_price'];

                $sign = $res['pay_id'].$res['param'].$res['type'].$res['price'].$res['really_price'].$key;
                $p = $p . "&sign=".md5($sign);

                if (strpos($url,"?")===false){
                    $url = $url."?".$p;
                }else{
                    $url = $url."&".$p;
                }
            }


            $re = $this->getCurl($url);
            if ($re=="success"){
                return json($this->getReturn());
            }else{
                Db::name("pay_order")->where("id",$res['id'])->update(array("state"=>2));

                return json($this->getReturn(-1,"异步通知失败"));
            }


        }else{
            $data = array(
                "close_date" => 0,
                "create_date" => time(),
                "is_auto" => 0,
                "notify_url" => "",
                "order_id" => "无订单转账",
                "param" => "无订单转账",
                "pay_date" => 0,
                "pay_id" => "无订单转账",
                "pay_url" => "",
                "price" => $price,
                "really_price" => $price,
                "return_url" => "",
                "state" => 1,
                "type" => $type

            );

            Db::name("pay_order")->insert($data);
            return json($this->getReturn());

        }


    }


    //关闭过期订单接口(请用定时器至少1分钟调用一次)
    public function closeEndOrder(){
        // 通知失败订单轻量重发（易支付协议依赖重试）
        $this->_retryNotify();

        $res = Db::name("setting")->where("vkey","lastheart")->find();
        $lastheart = $res['vvalue'];
        if ((time()-$lastheart)>60){
            Db::name("setting")->where("vkey","jkstate")->update(array("vvalue"=>0));
        }



        $time = Db::name("setting")->where("vkey", "close")->find();

        $closeTime = time()-60*$time['vvalue'];
        $close_date = time();

        $res = Db::name("pay_order")
            ->where("create_date <=".$closeTime)
            ->where("state",0)
            ->update(array("state"=>-1,"close_date"=>$close_date));

        if ($res){
            $rows = Db::name("pay_order")->where("close_date",$close_date)->select();
            foreach ($rows as $row){
                Db::name("tmp_price")
                    ->where("oid",$row['order_id'])
                    ->delete();
            }

            $rows = Db::name("tmp_price")->select();
            foreach ($rows as $row){
                $re = Db::name("pay_order")->where("order_id",$row['oid'])->find();
                if ($re){

                }else{
                    Db::name("tmp_price")
                        ->where("oid",$row['oid'])
                        ->delete();
                }
            }


            return json($this->getReturn(1,"成功清理".$res."条订单"));
        }else{
            return json($this->getReturn(1,"没有等待清理的订单"));
        }



    }
    /**
     * 重发通知失败(state=2)的订单回调，最多重试3次，间隔>=60秒
     */
    private function _retryNotify(){
        $rows = Db::name("pay_order")
            ->where("state",2)
            ->where("retry_count", "<", 3)
            ->limit(1)
            ->select();
        foreach ($rows as $row){
            if (time() - $row['close_date'] < 60) continue;
            $url = $row['notify_url'];
            if (empty($url)) continue;

            $res2 = Db::name("setting")->where("vkey","key")->find();
            $key = $res2['vvalue'];

            $apiMode = Db::name("setting")->where("vkey","apiMode")->find();
            if ($apiMode && $apiMode['vvalue']=="1"){
                $p = array(
                    "pid" => "1",
                    "trade_no" => $row['order_id'],
                    "out_trade_no" => $row['pay_id'],
                    "type" => ($row['type']==1 ? "wxpay" : "alipay"),
                    "name" => $row['param'],
                    "money" => $row['really_price'],
                    "trade_status" => "TRADE_SUCCESS"
                );
                $p['sign'] = $this->epaySign($p, $key);
                if (strpos($url,"?")===false){
                    $url = $url."?".http_build_query($p);
                }else{
                    $url = $url."&".http_build_query($p);
                }
            }else{
                $p = "payId=".$row['pay_id']."&param=".$row['param']."&type=".$row['type']."&price=".$row['price']."&reallyPrice=".$row['really_price'];
                $sign = $row['pay_id'].$row['param'].$row['type'].$row['price'].$row['really_price'].$key;
                $p = $p . "&sign=".md5($sign);
                if (strpos($url,"?")===false){
                    $url = $url."?".$p;
                }else{
                    $url = $url."&".$p;
                }
            }

            $re = $this->getCurl($url);
            if ($re=="success"){
                Db::name("pay_order")->where("id",$row['id'])->update(array("state"=>1,"close_date"=>time()));
            }else{
                Db::name("pay_order")->where("id",$row['id'])->update(array("retry_count"=>$row['retry_count']+1,"close_date"=>time()));
            }
        }
    }



    //发送Http请求
    private function getCurl($url, $post = 0, $cookie = 0, $header = 0, $nobaody = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $klsf[] = 'Accept:*/*';
        $klsf[] = 'Accept-Language:zh-cn';
        //$klsf[] = 'Content-Type:application/json';
        $klsf[] = 'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 11_2_1 like Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C153 MicroMessenger/6.6.1 NetType/WIFI Language/zh_CN';
        $klsf[] = 'Referer:'.$url;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $klsf);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

}