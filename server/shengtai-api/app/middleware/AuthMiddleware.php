<?php
declare(strict_types=1);

namespace app\middleware;

use app\exception\ApiException;
use app\helper\Jwt;

/**
 * 登录鉴权中间件
 *
 * 机制：Authorization: Bearer {token}
 * 无 token / token 无效 → 抛 11111，前端 toast「请先登录」并跳登录页。
 *
 * 校验通过后把 uid 挂到 request 上，控制器用 $this->uid() 取。
 */
class AuthMiddleware
{
    public function handle(\think\Request $request, \Closure $next)
    {
        $token = $this->extractToken($request);

        if ($token === '') {
            throw ApiException::unauthorized();
        }

        $uid = Jwt::uid($token, (string)config('shengtai.jwt.secret'));

        if ($uid === null) {
            throw ApiException::unauthorized();
        }

        // 传递 uid 给后续控制器
        $request->auth_uid = $uid;
        $request->middleware('auth_uid', $uid);

        return $next($request);
    }

    /**
     * 提取 token
     * 源站前端两种写法都出现过，兼容处理：
     *   1. Authorization: Bearer xxx
     *   2. Authorization: xxx
     *   3. token 放在 query/body（部分接口源站这么传）
     */
    private function extractToken(\think\Request $request): string
    {
        $header = $request->header('authorization', '');

        if ($header !== '') {
            if (stripos($header, 'bearer ') === 0) {
                return trim(substr($header, 7));
            }
            return trim($header);
        }

        return trim((string)$request->param('token', ''));
    }
}
