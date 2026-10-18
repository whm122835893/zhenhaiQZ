<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\ItemFollow;
use app\model\Nft as NftModel;
use app\model\NftMarketFollow;
use app\model\User as UserModel;
use app\model\UserInvite;
use app\model\UserProfile;
use app\model\UserWallet;
use app\service\Transformer;

/**
 * 用户控制器
 *
 * 对应源站 user 分包。
 *
 * 源站实测要点：
 *  - GET /user/wallet 源站未上线（10101），钱包信息随 account 接口返回
 *  - 用户资料与钱包是两张表，account 接口会把两者合并返回
 *  - 实名信息（realName / idCard）必须脱敏输出
 */
class User extends Base
{
    /**
     * GET /user/profile —— 用户资料
     */
    public function profile()
    {
        $uid = $this->uid();

        $user = UserModel::where('id', $uid)->where('is_deleted', 0)->find();
        if (!$user) {
            throw ApiException::notFound();
        }

        $profile = UserProfile::where('uid', $uid)->find();
        $wallet  = UserWallet::where('uid', $uid)->find();

        return $this->composeProfile($user->toArray(), $profile ? $profile->toArray() : [], $wallet ? $wallet->toArray() : []);
    }

    /**
     * POST /user/profile/avatar —— 更新头像
     *
     * 源站前端直传对象存储拿到 URL，再调本接口保存。
     */
    public function updateAvatar()
    {
        $uid = $this->uid();

        $avatar = (string)$this->bodyNeed('avatar');

        $profile = UserProfile::where('uid', $uid)->find();
        if (!$profile) {
            throw ApiException::notFound();
        }

        UserProfile::where('uid', $uid)->update([
            'avatar'     => $avatar,
            'avatar_url' => $this->absUrl($avatar),
        ]);

        return [
            'avatar'   => $avatar,
            'avatarUrl' => $this->absUrl($avatar),
        ];
    }

    /**
     * PUT /user/profile/nickname —— 更新昵称
     */
    public function updateNickname()
    {
        $uid = $this->uid();

        $nickname = trim((string)$this->bodyNeed('nickname'));
        if ($nickname === '') {
            throw ApiException::paramMissing();
        }

        // 源站昵称长度限制 1-20
        if (mb_strlen($nickname) > 20) {
            throw ApiException::paramType('nicknameis too long');
        }

        if (!UserProfile::where('uid', $uid)->find()) {
            throw ApiException::notFound();
        }

        UserProfile::where('uid', $uid)->update(['nickname' => $nickname]);

        return ['nickname' => $nickname];
    }

    /**
     * PUT /user/profile/introduction —— 更新个人简介
     */
    public function updateIntroduction()
    {
        $uid = $this->uid();

        $intro = (string)($this->body()['introduction'] ?? '');

        // 源站简介长度限制 0-200
        if (mb_strlen($intro) > 200) {
            throw ApiException::paramType('introductionis too long');
        }

        if (!UserProfile::where('uid', $uid)->find()) {
            throw ApiException::notFound();
        }

        UserProfile::where('uid', $uid)->update(['introduction' => $intro]);

        return ['introduction' => $intro];
    }

    /**
     * GET /user/invite —— 我的邀请信息
     *
     * ⚠️ 返回结构必须是 `{list, total}` —— 源站 invite 页原文：
     *   p.listInvite(this.query).then(i => {
     *     if (i.status) { const e = i.data.list;
     *                     this.inviteQuantity = i.data.total;
     *                     this.list = s ? [...this.list, ...e] : e;
     *                     0 == e.length && (this.finished = !0) }
     *     else 11111 == i.code && (this.finished = !0)
     *   })
     *
     * 之前这里返回的是自定义结构（invitees / inviteCount / totalReward），
     * 前端读 `res.data.list` 得到 undefined → `rows.length` 抛
     * "Cannot read properties of undefined (reading 'length')"。
     * 同时页面要支持触底分页，故按 pageNo 分页。
     */
    public function invite()
    {
        $uid = $this->uid();

        $profile = UserProfile::where('uid', $uid)->find();
        if (!$profile) {
            throw ApiException::notFound();
        }

        $pageNo = max(1, (int)($this->request->get('pageNo', 1)));
        $pageSize = 10;

        $query = UserInvite::where('uid', $uid);
        $total = (clone $query)->count();
        $rows = $query->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'inviteeId'    => Transformer::id($r['invitee_uid']),
                'inviteCode'   => (string)$r['invite_code'],
                'rewardAmount' => (int)$r['reward_amount'],
                'rewardStatus' => (int)$r['reward_status'],
                'rewardedAt'   => (int)$r['rewarded_at'] > 0 ? Transformer::ts($r['rewarded_at']) : null,
                'createTime'   => Transformer::datetimeTs($r['created_at']),
            ];
        }

        return $this->pageList($list, $total, true);
    }

    /**
     * GET /user/resource —— 我的资源汇总
     *
     * 首页「我的」页顶部聚合卡片用。
     */
    public function resource()
    {
        $uid = $this->uid();

        $wallet = UserWallet::where('uid', $uid)->find();

        $nftCount = NftModel::where('uid', $uid)->where('is_deleted', 0)->count();

        $followCount = ItemFollow::where('uid', $uid)->count()
            + NftMarketFollow::where('uid', $uid)->count();

        $orderCount = \app\model\Order::where('uid', $uid)->where('is_deleted', 0)->count();

        return [
            'balance'      => $wallet ? (int)$wallet['balance'] : 0,
            'frozenBalance'=> $wallet ? (int)$wallet['frozen_balance'] : 0,
            'totalIncome'  => $wallet ? (int)$wallet['total_income'] : 0,
            'totalExpense' => $wallet ? (int)$wallet['total_expense'] : 0,
            'nftCount'     => $nftCount,
            'followCount'  => $followCount,
            'orderCount'   => $orderCount,
        ];
    }

    /* ====================================================================
     * 内部工具
     * ================================================================== */

    /**
     * 合并账号 + 资料 + 钱包成一个响应体
     *
     * 复用 User 模型里的脱敏逻辑，保证 account 与 profile 两个端点
     * 返回的敏感字段处理方式完全一致。
     */
    private function composeProfile(array $user, array $profile, array $wallet): array
    {
        $account = (new UserModel())->toAccountArray($user);

        return [
            // 账号维度（已脱敏）
            'userId'            => $account['userId'],
            'phone'             => $account['phone'],
            'email'             => $account['email'],
            'account'           => $account['account'],
            'hasPassword'       => $account['hasPassword'],
            'hasSecondPassword' => $account['hasSecondPassword'],
            'hasRealName'       => $account['hasRealName'],
            'realName'          => $account['realName'],
            'idCard'            => $account['idCard'],
            'status'            => $account['status'],
            'lastLoginAt'       => $account['lastLoginAt'],
            'createTime'        => $account['createTime'],

            // 资料维度
            'nickname'      => (string)($profile['nickname'] ?? ''),
            'avatar'        => (string)($profile['avatar'] ?? ''),
            'avatarUrl'     => (string)($profile['avatar_url'] ?? ''),
            'sex'           => (int)($profile['sex'] ?? 0),
            'birthday'      => $profile['birthday'] ? Transformer::datetimeTs($profile['birthday']) : null,
            'country'       => (string)($profile['country'] ?? ''),
            'regionCode'    => (string)($profile['region_code'] ?? ''),
            'introduction'  => (string)($profile['introduction'] ?? ''),
            'chainAddress'  => (string)($profile['chain_address'] ?? ''),
            'inviteCode'    => (string)($profile['invite_code'] ?? ''),
            'inviter'       => (string)($profile['inviter'] ?? ''),
            'token'         => (string)($profile['token'] ?? ''),

            // 钱包维度（单位：分）
            'balance'       => $wallet ? (int)$wallet['balance'] : 0,
            'frozenBalance' => $wallet ? (int)$wallet['frozen_balance'] : 0,
            'totalIncome'   => $wallet ? (int)$wallet['total_income'] : 0,
            'totalExpense'  => $wallet ? (int)$wallet['total_expense'] : 0,
            'currency'      => (string)($wallet['currency'] ?? 'CNY'),
        ];
    }

    /**
     * 相对路径转绝对地址
     */
    private function absUrl(string $path): string
    {
        if ($path === '' || str_starts_with($path, 'http')) {
            return $path;
        }
        return Transformer::mediaBase() . ltrim($path, '/');
    }
}