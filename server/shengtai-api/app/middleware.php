<?php
// 全局中间件
//
// ⚠️ 顺序即语义，改动前必读
//
// 本项目响应链路是「异常 → 包络 → CORS 头」三层，正确顺序（外 → 内）：
//
//   1. CorsMiddleware      最外层。$next() 返回时拿到的已是最终响应
//                          （含正常包络，也含 ExceptionHandle 转好的
//                          11111 / 10203 错误包络），因此**任何**响应都带 CORS 头。
//                          若把它放在更内层，异常路径上可能漏头，浏览器只报
//                          "No 'Access-Control-Allow-Origin' header"，
//                          前端读不到 body 里的 message，toast 就出不来。
//
//   2. ResponseMiddleware  把控制器返回值（think\response\Html，data 是裸数组）
//                          或已包装的源站包络，统一输出为
//                          { status, code, message, data }。
//
// 为什么不写在 Service::register() 里用 unshift：
//   think\trace\Service 也在 register() 阶段往 global 队列塞 TraceDebug，
//   两者执行顺序不可控。TraceDebug 在 $next() 后调用 $response->getContent()，
//   此时拿到的是尚未序列化的裸数组，抛 "variable type error： array"，
//   该异常被 Response::send() 内部的 try-catch 吞掉，
//   客户端最终收到 HTTP 200 + 0 字节空响应体。
//   所以在 vendor/services.php 里**移除了** think\trace\Service，
//   把中间件顺序完全交给本文件显式声明 —— 不依赖注册时机，不玩反射。

return [
    \app\middleware\CorsMiddleware::class,
    \app\middleware\ResponseMiddleware::class,
];