<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\helper\Jwt;
use app\helper\Rsa;
use app\helper\Snowflake;
use app\model\User;
use app\model\UserProfile;
use app\model\UserWallet;
use app\model\UserOnlineSession;
use app\model\UserLoginLog;
use think\facade\Db;

/**
 * 账号与鉴权控制器 —— 对应源站 passport 分包
 *
 * 源站实测：
 *  - GET /passport/account 返回 10 字段，realName/idCard 已是脱敏值
 *  - GET /passport/online 返回 8 字段，含 isSelf（是否当前会话）
 *  - password 字段是 RSA(PKCS1) 加密的 Base64
 */
class Passport extends Base
{
    /**
     * POST /passport/signin —— 账号密码登录
     *
     * 请求：{ account, password(RSA加密), inviter? }
     */
    public function signin()
    {
        $body = $this->body();

        $account = trim((string)($body['account'] ?? ''));
        if ($account === '') {
            throw ApiException::missing('account');
        }

        $encrypted = (string)($body['password'] ?? '');
        if ($encrypted === '') {
            throw ApiException::missing('password');
        }

        $plain = $this->decryptPassword($encrypted);

        // (account = X OR phone = X) AND is_deleted = 0
        // 闭包保证 OR 不与外层 is_deleted 条件发生优先级错配
        $user = User::where('is_deleted', 0)
            ->where(function ($query) use ($account) {
                $query->where('account', $account)->whereOr('phone', $account);
            })
            ->find();

        if ($user === null || !$user->has_password) {
            // 源站在账号不存在/密码错误时返回同一提示，不暴露账号是否存在
            throw new ApiException('账号或密码错误', ApiException::CODE_SYSTEM_ERROR);
        }

        if (!password_verify($plain, (string)$user->password_hash)) {
            throw new ApiException('账号或密码错误', ApiException::CODE_SYSTEM_ERROR);
        }

        return $this->issueLogin($user, 1 /* 1密码 2短信 3第三方 */);
    }

    /**
     * POST /passport/signup —— 注册
     */
    public function signup()
    {
        $body = $this->body();

        $phone = trim((string)($body['phone'] ?? ''));
        if ($phone === '') {
            throw ApiException::missing('phone');
        }
        $code = trim((string)($body['code'] ?? ''));
        if ($code === '') {
            throw ApiException::missing('code');
        }

        $encrypted = (string)($body['password'] ?? '');
        if ($encrypted === '') {
            throw ApiException::missing('password');
        }
        $plain = $this->decryptPassword($encrypted);

        // 验证码校验（本地无短信服务，固定放行测试码，正式环境接短信网关）
        if (!\app\service\SmsService::verify($phone, $code)) {
            throw new ApiException('验证码错误或已过期', ApiException::CODE_SYSTEM_ERROR);
        }

        if (User::where('phone', $phone)->count() > 0) {
            throw new ApiException('该手机号已注册', ApiException::CODE_SYSTEM_ERROR);
        }

        $now = date('Y-m-d H:i:s');

        $user = new User();
        $user->save([
            'id'            => Snowflake::nextIdStr(),
            'phone'         => $phone,
            'email'         => (string)($body['email'] ?? ''),
            'account'       => $phone,
            'password_hash' => password_hash($plain, PASSWORD_BCRYPT),
            'has_password'  => 1,
            'status'        => 'normal',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // 建空资料 + 空钱包
        UserProfile::create([
            'id'         => Snowflake::nextIdStr(),
            'uid'        => $user->id,
            'nickname'   => '用户' . substr($phone, -4),
            'invite_code'=> strtoupper(substr(md5((string)$user->id), 0, 6)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        UserWallet::create([
            'id'         => Snowflake::nextIdStr(),
            'uid'        => $user->id,
            'balance'    => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 邀请闭环：携带 inviter（邀请码）则落 user_invites，失败不阻断注册
        $this->bindInviter($user->id, trim((string)($body['inviter'] ?? '')), $now);

        // 新人定向发放（事件驱动）：失败不阻断注册主流程
        try {
            \app\service\GrantService::onUserRegistered((string)$user->id);
        } catch (\Throwable) {
        }

        // 邀请奖励结算（时机=注册即发 时在此发放；失败不阻断注册）
        try {
            \app\service\InviteService::onRegistered((string)$user->id);
        } catch (\Throwable) {
        }

        return $this->issueLogin($user, 2);
    }

    /**
     * 绑定邀请关系：按邀请码找邀请人，写 user_invites（reward_status=pending）。
     * 奖励为藏品（invite.reward_iid/qty），由 InviteService 按结算时机发放。
     */
    private function bindInviter(string $inviteeUid, string $code, string $now): void
    {
        try {
            if ($code === '') {
                return;
            }
            $inviter = UserProfile::where('invite_code', strtoupper($code))->find();
            if (!$inviter || (string)$inviter->uid === $inviteeUid) {
                return;
            }
            // 同一被邀请人只允许一条记录（幂等）
            if (Db::name('user_invites')->where('invitee_uid', $inviteeUid)->count() > 0) {
                return;
            }

            Db::name('user_invites')->insert([
                'id'            => Snowflake::nextIdStr(),
                'uid'           => (string)$inviter->uid,
                'invitee_uid'   => $inviteeUid,
                'invite_code'   => strtoupper($code),
                'reward_amount' => 0,
                'reward_status' => 'pending',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } catch (\Throwable) {
            // 邀请绑定失败不影响注册主流程
        }
    }

    /**
     * POST /passport/signin/quick —— 短信验证码快捷登录
     */
    public function signinQuick()
    {
        $body = $this->body();
        $phone = trim((string)($body['phone'] ?? ''));
        $code  = trim((string)($body['code'] ?? ''));
        if ($phone === '') { throw ApiException::missing('phone'); }
        if ($code === '')  { throw ApiException::missing('code'); }

        if (!\app\service\SmsService::verify($phone, $code)) {
            throw new ApiException('验证码错误或已过期', ApiException::CODE_SYSTEM_ERROR);
        }

        $user = User::where('phone', $phone)->where('is_deleted', 0)->find();
        if ($user === null) {
            // 快捷登录不存在则自动注册
            $now = date('Y-m-d H:i:s');
            $user = new User();
            $user->save([
                'id'         => Snowflake::nextIdStr(),
                'phone'      => $phone,
                'account'    => $phone,
                'status'     => 'normal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            UserProfile::create([
                'id' => Snowflake::nextIdStr(), 'uid' => $user->id,
                'nickname' => '用户' . substr($phone, -4),
                'invite_code' => strtoupper(substr(md5((string)$user->id), 0, 6)),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            UserWallet::create([
                'id' => Snowflake::nextIdStr(), 'uid' => $user->id,
                'balance' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);

            // 新人定向发放（快捷登录自动注册路径，失败不阻断）
            try {
                \app\service\GrantService::onUserRegistered((string)$user->id);
            } catch (\Throwable) {
            }
        }

        return $this->issueLogin($user, 2);
    }

    /**
     * POST /passport/signin/oneClick —— 一键登录（第三方授权）
     */
    public function signinOneClick()
    {
        throw new ApiException('暂未支持', ApiException::CODE_SYSTEM_ERROR);
    }

    /**
     * GET /passport/captcha/image —— 图形验证码
     */
    public function captchaImage()
    {
        $key = \app\service\CaptchaService::create();
        return [
            'key'  => $key,
            // 本地不接短信/图形服务，前端拿不到图片时会走兜底 UI
            'image'=> '',
            // RSA 公钥随验证码下发（源站同一响应携带）
            'publicKey' => (string)config('rsa.public_key', ''),
        ];
    }

    /**
     * POST /passport/captcha/sms —— 短信验证码
     */
    public function captchaSms()
    {
        $body = $this->body();
        $phone = trim((string)($body['phone'] ?? ''));
        if ($phone === '') { throw ApiException::missing('phone'); }

        $code = \app\service\SmsService::send($phone);
        return [
            'phone'  => $phone,
            // 开发环境直接把验证码返回方便测试，生产环境必须去掉
            'code'   => $this->app->isDebug() ? $code : '',
            'expire' => 300,
        ];
    }

    /**
     * GET /passport/account —— 账号信息（需登录）
     */
    public function account()
    {
        $user = User::find($this->uid());
        if ($user === null) {
            throw ApiException::notFound();
        }
        return $user->toAccountArray();
    }

    /**
     * POST /passport/signout —— 退出
     */
    public function signout()
    {
        $uid = $this->uid();
        UserOnlineSession::where('uid', $uid)
            ->where('token', (string)$this->request->header('authorization', ''))
            ->delete();
        return true;
    }

    /**
     * GET /passport/online —— 在线会话列表
     *
     * 源站实测 8 字段：token/createTime/deviceId/deviceModel/platform/signinWay/ip/isSelf
     */
    public function online()
    {
        $uid   = $this->uid();
        $token = trim((string)$this->request->header('authorization', ''));
        if (stripos($token, 'bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        $rows = UserOnlineSession::where('uid', $uid)
            ->order('login_at', 'desc')
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $item = \app\service\Transformer::onlineSession($r);
            // isSelf：是否为当前 token 对应的会话
            $item['isSelf'] = $r['token'] === $token;
            $out[] = $item;
        }
        return $out;
    }

    /**
     * PUT /passport/password —— 修改登录密码
     */
    public function updatePassword()
    {
        $uid = $this->uid();
        $body = $this->body();

        $oldEnc = (string)($body['oldPassword'] ?? '');
        $newEnc = (string)($body['newPassword'] ?? '');
        if ($newEnc === '') { throw ApiException::missing('newPassword'); }

        $user = User::find($uid);
        if ($user === null) { throw ApiException::notFound(); }

        // 已有密码则需校验旧密码
        if ($user->has_password) {
            if ($oldEnc === '') { throw ApiException::missing('oldPassword'); }
            if (!password_verify($this->decryptPassword($oldEnc), (string)$user->password_hash)) {
                throw new ApiException('原密码错误', ApiException::CODE_SYSTEM_ERROR);
            }
        }

        $user->save([
            'password_hash' => password_hash($this->decryptPassword($newEnc), PASSWORD_BCRYPT),
            'has_password'  => 1,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * PUT /passport/secondPassword —— 设置/修改操作密码
     */
    public function updateSecondPassword()
    {
        $uid = $this->uid();
        $body = $this->body();

        $password = (string)($body['password'] ?? '');
        if ($password === '') { throw ApiException::missing('password'); }

        $plain = $this->decryptPassword($password);
        if (!preg_match('/^\d{6}$/', $plain)) {
            throw new ApiException('操作密码为6位数字', ApiException::CODE_SYSTEM_ERROR);
        }

        $user = User::find($uid);
        $user->save([
            'second_password_hash' => password_hash($plain, PASSWORD_BCRYPT),
            'has_second_password'  => 1,
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * PUT /passport/phone —— 修改手机号
     */
    public function updatePhone()
    {
        $uid  = $this->uid();
        $body = $this->body();

        $phone = trim((string)($body['phone'] ?? ''));
        $code  = trim((string)($body['code'] ?? ''));
        if ($phone === '') { throw ApiException::missing('phone'); }
        if ($code === '')  { throw ApiException::missing('code'); }

        if (!\app\service\SmsService::verify($phone, $code)) {
            throw new ApiException('验证码错误或已过期', ApiException::CODE_SYSTEM_ERROR);
        }
        if (User::where('phone', $phone)->where('id', '<>', $uid)->count() > 0) {
            throw new ApiException('该手机号已被使用', ApiException::CODE_SYSTEM_ERROR);
        }

        User::find($uid)->save([
            'phone'      => $phone,
            'account'    => $phone,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * POST /passport/realName —— 实名认证
     */
    public function realName()
    {
        $uid  = $this->uid();
        $body = $this->body();

        $name = trim((string)($body['name'] ?? ''));
        $card = trim((string)($body['idCard'] ?? ''));
        if ($name === '') { throw ApiException::missing('name'); }
        if ($card === '') { throw ApiException::missing('idCard'); }

        User::find($uid)->save([
            // 存脱敏值（源站返回的就是脱敏值）
            'real_name'     => $this->maskName($name),
            'id_card'       => $this->maskIdCard($card),
            'has_real_name' => 1,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * POST /passport/delete —— 注销账号（软删除）
     */
    public function delete()
    {
        $uid  = $this->uid();
        $body = $this->body();

        $password = (string)($body['password'] ?? '');
        if ($password === '') { throw ApiException::missing('password'); }

        $user = User::find($uid);
        if ($user !== null && $user->has_password) {
            if (!password_verify($this->decryptPassword($password), (string)$user->password_hash)) {
                throw new ApiException('密码错误', ApiException::CODE_SYSTEM_ERROR);
            }
        }

        $now = date('Y-m-d H:i:s');
        User::find($uid)->save(['is_deleted' => 1, 'status' => 'deleted', 'updated_at' => $now]);
        UserProfile::where('uid', $uid)->update(['is_deleted' => 1, 'updated_at' => $now]);
        UserWallet::where('uid', $uid)->update(['is_deleted' => 1, 'updated_at' => $now]);

        return true;
    }

    /* ---------- 内部方法 ---------- */

    /**
     * 签发 token + 记录会话 + 写登录日志
     */
    private function issueLogin(User $user, int $signinWay): array
    {
        $uid  = (string)$user->id;
        $now  = $this->nowMillis();
        $time = date('Y-m-d H:i:s');

        $token = Jwt::encode(['uid' => $uid], (string)config('shengtai.jwt.secret'), (int)config('shengtai.jwt.ttl'));

        // 记录在线会话（供 GET /passport/online 返回）
        UserOnlineSession::create([
            'id'             => Snowflake::nextIdStr(),
            'uid'            => $uid,
            'token'          => $token,
            'signin_way'     => $signinWay,
            'device_id'      => $this->str('deviceId'),
            'device_model'   => $this->str('deviceModel'),
            'platform'       => $this->str('platform', 'web'),
            'ip'             => $this->request->ip(),
            'login_at'       => $now,
            'last_active_at' => $now,
            'is_online'      => 1,
            'is_self'        => 1,
            'created_at'     => $time,
            'updated_at'     => $time,
        ]);

        // 更新最后登录时间
        $user->save(['last_login_at' => $now, 'updated_at' => $time]);

        // 登录日志
        UserLoginLog::create([
            'id'         => Snowflake::nextIdStr(),
            'uid'        => $uid,
            'device_id'  => $this->str('deviceId'),
            'platform'   => $this->str('platform', 'web'),
            'ip'         => $this->request->ip(),
            'signin_way' => $signinWay,
            'is_success' => 1,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        return [
            'token' => $token,
            'uid'   => $uid,
            'user'  => $user->toAccountArray(),
        ];
    }

    /** RSA 解密密码 */
    private function decryptPassword(string $encrypted): string
    {
        $plain = Rsa::decrypt($encrypted, (string)config('rsa.private_key'));
        if ($plain === null) {
            throw new ApiException('密码解密失败', ApiException::CODE_PARAM_TYPE);
        }
        return $plain;
    }

    /** 姓名脱敏：王*绵 */
    private function maskName(string $name): string
    {
        $len = mb_strlen($name);
        if ($len <= 1) {
            return $name;
        }
        return mb_substr($name, 0, 1) . str_repeat('*', $len - 1);
    }

    /** 身份证脱敏：5202****9838 */
    private function maskIdCard(string $card): string
    {
        $len = strlen($card);
        if ($len < 8) {
            return $card;
        }
        return substr($card, 0, 4) . str_repeat('*', $len - 8) . substr($card, -4);
    }
}
