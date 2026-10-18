<?php
declare(strict_types=1);

namespace app\middleware;

use think\Response;

/**
 * 跨域中间件
 *
 * 源站 api.shengtai.cyou 的 CORS 行为（实测）：
 *   Access-Control-Allow-Origin:      回显请求的 Origin（不是 *）
 *   Access-Control-Allow-Credentials: true
 *   Vary: Access-Control-Request-Method, Access-Control-Request-Headers
 *
 * 为什么必须回显 Origin 而不能用 *：
 *   源站前端带 Cookie（credentials: include），浏览器在
 *   Allow-Origin: * 时会直接拒绝响应，前端拿不到数据。
 *
 * 放在中间件队列最内层（ResponseMiddleware 之后执行），
 * 这样即使控制器抛异常转成 401/10203 包络，响应上也带 CORS 头，
 * 前端才能读到 body 里的 message 去弹 toast —— 否则浏览器只会报
 * "CORS policy: No 'Access-Control-Allow-Origin' header"，看不到真实错误。
 */
class CorsMiddleware
{
    /** 允许的来源。null = 回显任意 Origin（与源站一致的后端策略）。 */
    private ?array $allowOrigins;

    /** 允许的请求头 */
    private array $allowHeaders = [
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'appid',
        'version',
        'deviceId',
        'deviceModel',
        'platform',
    ];

    /** 允许的方法 */
    private array $allowMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];

    public function __construct()
    {
        $conf = (array)\think\facade\Config::get('shengtai.cors', []);
        $this->allowOrigins = $conf['allow_origins'] ?? null;
    }

    public function handle(\think\Request $request, \Closure $next)
    {
        // 预检请求直接 204，不进控制器
        if ($request->method(true) === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        return $this->decorate($request, $response);
    }

    private function decorate(\think\Request $request, $response): Response
    {
        $origin = (string)$request->header('origin', '');

        if ($origin !== '' && $this->isAllowed($origin)) {
            $header = $response->getHeader();

            $header['Access-Control-Allow-Origin']      = $origin;
            $header['Access-Control-Allow-Credentials'] = 'true';
            $header['Access-Control-Allow-Methods']     = implode(', ', $this->allowMethods);
            $header['Access-Control-Allow-Headers']     = implode(', ', $this->allowHeaders);
            $header['Access-Control-Max-Age']           = '86400';
            $header['Access-Control-Expose-Headers']    = 'Content-Disposition';

            // Origin 不同则响应内容也会不同，必须声明 Vary 避免缓存串味。
            //
            // ⚠️ 值必须是**字符串**，不能是数组。
            // think\Response::send() 里是 `header($name . ':' . $val)`，
            // 拼接数组会抛 "Array to string conversion"，
            // 该异常被 send() 内部的 catch 吞掉，sendData() 从未执行，
            // 客户端收到 Content-Type 正确但 **0 字节 body**。
            // 本项目踩过这个坑：带 Origin 头时响应恒为空。
            $vary = $header['Vary'] ?? [];
            if (!is_array($vary)) {
                $vary = array_filter(array_map('trim', explode(',', (string)$vary)));
            }
            foreach (['Access-Control-Request-Method', 'Access-Control-Request-Headers', 'Origin'] as $v) {
                if (!in_array($v, $vary, true)) {
                    $vary[] = $v;
                }
            }
            $header['Vary'] = implode(', ', $vary);

            $response->header($header);
        }

        return $response;
    }

    private function isAllowed(string $origin): bool
    {
        // null = 不限制（与源站一致：回显任意 Origin）
        if ($this->allowOrigins === null) {
            return true;
        }

        if (in_array('*', $this->allowOrigins, true)) {
            return true;
        }

        foreach ($this->allowOrigins as $pattern) {
            if ($pattern === $origin) {
                return true;
            }
            // 支持 fnmatch 通配：https://*.example.com
            if (fnmatch($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }
}