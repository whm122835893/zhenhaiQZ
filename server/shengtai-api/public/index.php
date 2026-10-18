<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\App;

// [ 应用入口文件 ]

require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);

// -----------------------------------------------------------------------
// 输出缓冲兜底
//
// ThinkPHP 的 debugModeInit() 在非 CLI 环境会 ob_start() 申请一个大缓冲，
// 目的是收集 SQL 日志便于调试。在 php-fpm / nginx 下 SAPI 会在请求结束时
// 自动 flush；但 PHP 内置服务器（SAPI cli-server）与 cli-server 一族不会，
// 导致 $response->send() echo 的内容滞留缓冲区，客户端收到 0 字节。
//
// 这里在响应发送完毕后逐级 flush，生产环境（SAPI 会自动处理）下无副作用。
// -----------------------------------------------------------------------
while (ob_get_level() > 0) {
    ob_end_flush();
}