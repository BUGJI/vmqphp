<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

Route::get('think', function () {
    return 'hello,ThinkPHP5!';
});

Route::any('login','index/index/login');
Route::any('getMenu','index/index/getMenu');
Route::any('enQrcode','admin/index/enQrcode');
Route::any('createOrder','index/index/createOrder');


Route::any('getOrder','index/index/getOrder');
Route::any('checkOrder','index/index/checkOrder');
Route::any('getState','index/index/getState');

// 易支付协议接口（接口模式=易支付格式时使用）
Route::any('epaySubmit','index/index/epaySubmit');
Route::any('epayOrder','index/index/epayOrder');

Route::any('appHeart','index/index/appHeart');
Route::any('appPush','index/index/appPush');


Route::any('closeEndOrder','index/index/closeEndOrder');


return [

];
