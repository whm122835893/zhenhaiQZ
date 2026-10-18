<?php
// +----------------------------------------------------------------------
// | 内置服务器路由
// |解决 ThinkPHP debugModeInit() 开启 ob_start() 后，
// |     PHP 内置服务器（SAPI cli-server）不自动 flush 输出缓冲的问题。
// +----------------------------------------------------------------------

// 已存在的静态文件交给内置服务器自己处理
if (is_file($_SERVER["DOCUMENT_ROOT"] . $_SERVER["SCRIPT_NAME"])) {
    return false;
}

$_SERVER["SCRIPT_FILENAME"] = __DIR__ . '/index.php';

require __DIR__ . "/index.php";

// ThinkPHP 初始化时会 ob_start() 申请大缓冲，内置服务器不像 php-fpm 那样
// 在请求结束时自动 flush，导致 $response->send() echo 的内容滞留缓冲区，
// 客户端收到 0 字节。这里手动把缓冲区刷出。
while (ob_get_level() > 0) {
    ob_end_flush();
}