<?php
declare(strict_types=1);

namespace app\controller\admin;

/**
 * 钱包流水与对账
 *
 * wallet_transactions.amount 正入负出；balance_after 为该笔后余额。
 * 对账口径：用户钱包余额总和 vs 流水净额（差额通常来自建账前的初始余额）。
 */
class Wallet extends AdminBase
{
    private const TRANS_TYPES = ['recharge', 'withdraw', 'buy', 'sell', 'fee', 'reward'];

    /** GET /admin/wallet/transactions —— 资金流水 */
    public function transactions()
    {
        $q = \think\facade\Db::name('wallet_transactions')
            ->field('id,uid,order_id,trans_type,amount,balance_after,remark,created_at');

        if (($uid = $this->str('uid')) !== '') {
            $q->where('uid', $uid);
        }
        $type = $this->str('type');
        if ($type !== '' && in_array($type, self::TRANS_TYPES, true)) {
            $q->where('trans_type', $type);
        }
        if (($from = $this->str('from')) !== '') {
            $q->where('created_at', '>=', $from . ' 00:00:00');
        }
        if (($to = $this->str('to')) !== '') {
            $q->where('created_at', '<=', $to . ' 23:59:59');
        }
        $q->order('id', 'desc');

        $data = $this->adminPage($q);
        $uids = array_values(array_unique(array_column($data['list'], 'uid')));
        $users = $uids === [] ? [] : \think\facade\Db::name('user_profiles')->whereIn('uid', $uids)->column('nickname', 'uid');
        foreach ($data['list'] as &$r) {
            $r['id']          = (string)$r['id'];
            $r['uid']         = (string)$r['uid'];
            $r['orderId']     = (string)$r['order_id'];
            $r['amount']      = (float)$r['amount'];
            $r['balanceAfter']= (float)$r['balance_after'];
            $r['nickname']    = (string)($users[$r['uid']] ?? ('用户' . substr((string)$r['uid'], -4)));
            unset($r['order_id'], $r['balance_after']);
        }
        unset($r);
        return $data;
    }

    /** GET /admin/wallet/summary —— 资金对账 */
    public function summary()
    {
        $typeSums = \think\facade\Db::name('wallet_transactions')
            ->group('trans_type')
            ->field('trans_type, COUNT(*) AS cnt, SUM(amount) AS total')
            ->select()->toArray();

        $byType = [];
        foreach (self::TRANS_TYPES as $t) {
            $byType[$t] = ['count' => 0, 'amount' => 0.0];
        }
        foreach ($typeSums as $t) {
            $byType[$t['trans_type']] = [
                'count'  => (int)$t['cnt'],
                'amount' => round((float)$t['total'], 2),
            ];
        }

        // 实际余额总和（用户钱包表，两表均无软删列）
        // ⚠️ TP8 的 find() 在无 where 条件时直接返回空（BaseQuery::find 短路），
        //    无条件聚合必须走 select()
        $walletRows = \think\facade\Db::name('user_wallets')
            ->field('COUNT(*) AS users, SUM(balance) AS total_balance')
            ->select()->toArray();
        $wallet = $walletRows[0] ?? [];

        // 流水口径净额
        $ledgerRows = \think\facade\Db::name('wallet_transactions')
            ->field('SUM(amount) AS net')
            ->select()->toArray();
        $ledger = $ledgerRows[0] ?? [];

        $totalBalance = round((float)($wallet['total_balance'] ?? 0), 2);
        $ledgerNet    = round((float)($ledger['net'] ?? 0), 2);

        return [
            'userCount'   => (int)($wallet['users'] ?? 0),
            'totalBalance'=> $totalBalance,
            'ledgerNet'   => $ledgerNet,
            // 差额 = 实际余额 - 流水净额；>0 通常为建账前初始余额或未落流水的调整
            'diff'        => round($totalBalance - $ledgerNet, 2),
            'byType'      => $byType,
        ];
    }
}
