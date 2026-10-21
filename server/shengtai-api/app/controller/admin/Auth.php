<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\helper\Jwt;

/**
 * 管理员认证 —— POST /admin/auth/login | GET me | POST logout | POST password
 */
class Auth extends AdminBase
{
    /** 登录有效期 12h */
    private const TTL = 43200;

    public function login()
    {
        $body     = $this->body();
        $username = trim((string)($body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($username === '' || $password === '') {
            throw ApiException::missing('username/password');
        }

        $admin = \think\facade\Db::name('admin_users')
            ->where('username', $username)
            ->where('is_deleted', 0)
            ->find();

        if (!$admin || (int)$admin['status'] !== 1 || !password_verify($password, (string)$admin['password'])) {
            // 登录失败也落审计（风控可查爆破）
            $this->audit($username, 0, '用户名或密码错误');
            throw new ApiException('用户名或密码错误', ApiException::CODE_SYSTEM_ERROR);
        }

        $now = date('Y-m-d H:i:s');
        \think\facade\Db::name('admin_users')
            ->where('id', (int)$admin['id'])
            ->update([
                'last_login_at' => $now,
                'last_login_ip' => (string)$this->request->ip(),
                'login_count'   => \think\facade\Db::raw('login_count + 1'),
            ]);

        $this->audit($username, 1, '登录成功');

        $token = Jwt::encode([
            'typ'      => 'admin',
            'aid'      => (int)$admin['id'],
            'role'     => (int)$admin['role'],
            'username' => (string)$admin['username'],
        ], \app\middleware\AdminAuthMiddleware::secret(), self::TTL);

        return [
            'token'    => $token,
            'expireIn' => self::TTL,
            'profile'  => $this->profileOf($admin),
        ];
    }

    public function me()
    {
        $admin = \think\facade\Db::name('admin_users')->where('id', (int)$this->aid())->find();
        if (!$admin) {
            throw ApiException::unauthorized('账号不存在');
        }
        return ['profile' => $this->profileOf($admin)];
    }

    /** 登出：无状态 JWT 服务端无需处理，审计留痕即可 */
    public function logout()
    {
        $this->audit($this->username(), 1, '退出登录');
        return ['ok' => true];
    }

    /** 修改自己的密码（旧密码校验） */
    public function password()
    {
        $this->needWriter();
        $body = $this->body();
        $old  = (string)($body['oldPassword'] ?? '');
        $new  = (string)($body['newPassword'] ?? '');

        if (strlen($new) < 8) {
            throw new ApiException('新密码至少 8 位', ApiException::CODE_PARAM_TYPE);
        }

        $admin = \think\facade\Db::name('admin_users')->where('id', (int)$this->aid())->find();
        if (!$admin || !password_verify($old, (string)$admin['password'])) {
            throw new ApiException('旧密码错误', ApiException::CODE_SYSTEM_ERROR);
        }

        \think\facade\Db::name('admin_users')
            ->where('id', (int)$admin['id'])
            ->update(['password' => password_hash($new, PASSWORD_BCRYPT)]);

        $this->opLog('auth', 'change_password', null, ['username' => $this->username()]);
        return ['ok' => true];
    }

    /* ---------- 内部 ---------- */

    private function profileOf(array $admin): array
    {
        return [
            'id'          => (string)$admin['id'],
            'username'    => (string)$admin['username'],
            'realName'    => (string)$admin['real_name'],
            'role'        => (int)$admin['role'],
            'roleName'    => self::ROLE[(int)$admin['role']] ?? '未知',
            'lastLoginAt' => (string)$admin['last_login_at'],
            'loginCount'  => (int)$admin['login_count'],
        ];
    }

    private const ROLE = [1 => '超级管理员', 2 => '运营', 3 => '财务', 4 => '客服'];

    private function audit(string $username, int $success, string $detail): void
    {
        try {
            \think\facade\Db::name('audit_logs')->insert([
                'actor_type' => 2,
                'actor_id'   => 0,
                'module'     => 'auth',
                'action'     => $success ? 'login_success' : 'login_failed',
                'target'     => $username,
                'detail'     => mb_substr($detail, 0, 200),
                'ip'         => (string)$this->request->ip(),
            ]);
        } catch (\Throwable) {
        }
    }
}
