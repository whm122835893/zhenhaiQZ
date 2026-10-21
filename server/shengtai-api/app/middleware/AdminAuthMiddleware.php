<?php
declare(strict_types=1);

namespace app\middleware;

use app\exception\ApiException;
use app\helper\Jwt;

/**
 * 管理员鉴权中间件（参考《管理后台开发文档》2.2 AdminAuth）
 *
 * 与用户 JWT 的区别（文档 2.1 要求"密钥不同、claim 不同、互不通用"）：
 *   1. 独立密钥：ADMIN_JWT_SECRET 环境变量，缺省用用户密钥拼后缀派生
 *   2. claim 带 typ=admin / aid / role，用户 token 没有这些字段，解析必失败
 *
 * 校验通过后挂载：auth_aid / auth_role / auth_username
 */
class AdminAuthMiddleware
{
    public function handle(\think\Request $request, \Closure $next)
    {
        $header = $request->header('authorization', '');
        $token  = stripos($header, 'bearer ') === 0 ? trim(substr($header, 7)) : trim($header);

        if ($token === '') {
            throw new ApiException('请先登录管理后台', ApiException::CODE_UNAUTHORIZED);
        }

        $secret = self::secret();
        $data   = Jwt::decode($token, $secret);

        if ($data === null || ($data['typ'] ?? '') !== 'admin' || empty($data['aid'])) {
            throw new ApiException('登录状态无效，请重新登录', ApiException::CODE_UNAUTHORIZED);
        }

        // 管理员状态实时校验（禁用/删除立即踢下线）
        $admin = \think\facade\Db::name('admin_users')
            ->where('id', (int)$data['aid'])
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->find();

        if (!$admin) {
            throw new ApiException('账号已被禁用', ApiException::CODE_UNAUTHORIZED);
        }

        $request->auth_aid      = (string)$admin['id'];
        $request->auth_role     = (int)$admin['role'];
        $request->auth_username = (string)$admin['username'];

        return $next($request);
    }

    public static function secret(): string
    {
        $env = trim((string)\think\facade\Env::get('ADMIN_JWT_SECRET', ''));
        return $env !== '' ? $env : (string)config('shengtai.jwt.secret') . '|zhenhai-admin';
    }
}
