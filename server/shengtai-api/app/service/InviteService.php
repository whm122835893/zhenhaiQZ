<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 邀请奖励结算（仅藏品通道，不发余额）
 *
 * 配置（system_configs invite.*）：
 *   invite.enabled        1/0 总开关
 *   invite.reward_iid     奖励藏品ID（items.id）
 *   invite.reward_qty     奖励藏品数量
 *   invite.settle_timing  register=注册即发 / first_order=被邀请人首单支付后
 *
 * 幂等防线：
 *   - user_invites 每个被邀请人仅一条，结算后 reward_status=granted 不再重复
 *   - 复用 grant_record.uk_biz_key（source=invite_reward），重复触发不超发
 */
final class InviteService
{
    public const TIMING_REGISTER    = 'register';
    public const TIMING_FIRST_ORDER = 'first_order';

    /** 注册事件（Passport 注册成功后调用；时机不匹配时静默跳过） */
    public static function onRegistered(string $inviteeUid): void
    {
        if (self::timing() === self::TIMING_REGISTER) {
            self::settle($inviteeUid);
        }
    }

    /** 支付成功事件（订单完成回调/补单后调用；时机不匹配时静默跳过） */
    public static function onOrderPaid(string $buyerUid): void
    {
        if (self::timing() === self::TIMING_FIRST_ORDER) {
            self::settle($buyerUid);
        }
    }

    /** 当前结算时机 */
    public static function timing(): string
    {
        $t = SystemConfigService::get('invite.settle_timing', self::TIMING_REGISTER);
        return in_array($t, [self::TIMING_REGISTER, self::TIMING_FIRST_ORDER], true)
            ? $t : self::TIMING_REGISTER;
    }

    /**
     * 结算某被邀请人对应的邀请奖励（幂等，可重复调用）。
     * 发放对象是邀请人（user_invites.uid）。
     *
     * @return bool 是否发生了实际发放
     */
    public static function settle(string $inviteeUid): bool
    {
        if (SystemConfigService::int('invite.enabled', 0) !== 1) {
            return false;
        }

        $invite = Db::name('user_invites')->where('invitee_uid', (int)$inviteeUid)->find();
        if (!$invite || $invite['reward_status'] !== 'pending') {
            return false; // 无邀请关系 / 已发放 / 已失效
        }

        // 未配置奖励藏品则保持 pending，待运营补配后下次触发自动结算
        $iid = SystemConfigService::int('invite.reward_iid', 0);
        if ($iid <= 0) {
            return false;
        }
        $qty = max(1, SystemConfigService::int('invite.reward_qty', 1));

        GrantService::grantDirect((string)$invite['uid'], $iid, $qty, 'invite_reward', 'inv:' . $invite['id']);
        Db::name('user_invites')->where('id', $invite['id'])->update([
            'reward_status' => 'granted',
            'rewarded_at'   => (int)round(microtime(true) * 1000),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        return true;
    }
}
