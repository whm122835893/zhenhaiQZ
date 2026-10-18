<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\UserWallet;
use app\model\WalletTransaction;
use app\service\Transformer;

/**
 * 钱包控制器
 *
 * 对应源站 wallet 分包。
 *
 * ⚠️ 源站实测的关键行为：
 *  - GET /wallet/hfpay 的 data 是**纯字符串 URL**（收银台地址），不是对象
 *  - GET /wallet/yeepay **恒报 code:20000 系统错误**（易宝通道未上线）
 *  - GET /wallet/transaction、GET /wallet/log 源站未上线
 *
 * 这三点必须复刻，否则前端会写出源站没有的分支。
 */
class Wallet extends Base
{
    /**
     * GET /wallet/hfpay —— 汇付收银台地址
     *
     * ⚠️ 返回值是纯字符串，不是 {url: "..."} 这种对象。
     */
    public function hfpay()
    {
        $uid = $this->uid();

        $orderId = $this->str('orderId');
        $amount  = $this->int('amount');

        // 充值金额必须大于 0（源站报参数类型错误）
        if ($amount <= 0 && $orderId === '') {
            throw ApiException::paramType('amountmust be number');
        }

        if ($orderId !== '') {
            $order = \app\model\Order::where('id', $orderId)
                ->where('uid', $uid)
                ->where('is_deleted', 0)
                ->find();

            if (!$order) {
                throw ApiException::notFound();
            }

            $amount = (int)$order['amount'];
        }

        $base = rtrim((string)config('shengtai.shengtai.media_base'), '/');

        // 源站返回的就是这个 URL 字符串本身，前端直接 window.location 跳转
        return $base . '/cashier?amount=' . $amount . '&uid=' . $uid;
    }

    /**
     * GET /wallet/yeepay —— 易宝收银台
     *
     * ⚠️ 源站恒报 20000，保持同样行为，不臆造可用通道。
     */
    public function yeepay()
    {
        throw ApiException::system('系统错误');
    }

    /**
     * GET /wallet/balance —— 余额查询
     *
     * 注：源站没有这个端点（钱包信息随 account 返回），
     * 本地补充供前端内部使用，不影响源站前端行为。
     */
    public function balance()
    {
        $uid = $this->uid();

        $wallet = UserWallet::where('uid', $uid)->find();
        if (!$wallet) {
            throw ApiException::notFound();
        }

        return [
            'balance'       => (int)$wallet['balance'],
            'frozenBalance' => (int)$wallet['frozen_balance'],
            'totalIncome'   => (int)$wallet['total_income'],
            'totalExpense'  => (int)$wallet['total_expense'],
            'currency'      => (string)$wallet['currency'],
        ];
    }

    /**
     * GET /wallet/transactions —— 流水
     *
     * 注：源站的 GET /wallet/transaction 未上线，这里是本地补充实现。
     */
    public function transactions()
    {
        $uid = $this->uid();

        $pageNo   = $this->pageNo();
        $pageSize = $this->pageSize();

        $query = WalletTransaction::where('uid', $uid);

        $transType = $this->str('transType');
        if ($transType !== '') {
            $query->where('trans_type', $transType);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id'           => Transformer::id($r['id']),
                'orderId'      => Transformer::id($r['order_id']),
                'transType'    => (int)$r['trans_type'],
                'amount'       => (int)$r['amount'],
                'balanceAfter' => (int)$r['balance_after'],
                'remark'       => (string)$r['remark'],
                'createTime'   => Transformer::datetimeTs($r['created_at']),
            ];
        }

        return $this->pageList($data, $total);
    }
}