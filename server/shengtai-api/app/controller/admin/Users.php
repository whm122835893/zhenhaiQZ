<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;

/**
 * 用户管理 —— GET /admin/users | GET :id | POST :id/status
 */
class Users extends AdminBase
{
    public function index()
    {
        $q = \think\facade\Db::name('users')->alias('u')
            ->leftJoin('user_profiles p', 'p.uid = u.id')
            ->where('u.is_deleted', 0)
            ->field('u.id,u.phone,u.email,u.account,u.status,u.has_real_name,u.last_login_at,u.created_at,p.nickname,p.avatar_url,p.invite_code');

        $kw = $this->str('keyword');
        if ($kw !== '') {
            $q->where(function ($w) use ($kw) {
                $w->whereLike('u.account', "%{$kw}%")
                  ->whereOr('u.phone', 'like', "%{$kw}%")
                  ->whereOr('p.nickname', 'like', "%{$kw}%");
            });
        }
        $status = $this->str('status');
        if ($status !== '') {
            $q->where('u.status', $status);
        }
        $q->order('u.id', 'desc');

        $data = $this->adminPage($q);
        foreach ($data['list'] as &$r) {
            $r['id']       = (string)$r['id'];
            $r['hasReal']  = (int)$r['has_real_name'] === 1;
            $r['nickname'] = (string)($r['nickname'] ?: ('用户' . substr((string)$r['id'], -4)));
            unset($r['has_real_name'], $r['avatar_url']);
        }
        unset($r);
        return $data;
    }

    public function detail()
    {
        $id = $this->needInt('id');
        $u  = \think\facade\Db::name('users')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$u) {
            throw ApiException::notFound('用户不存在');
        }
        $p = \think\facade\Db::name('user_profiles')->where('uid', $id)->find();
        $w = \think\facade\Db::name('user_wallets')->where('uid', $id)->find();

        return [
            'id'          => (string)$u['id'],
            'account'     => (string)$u['account'],
            'phone'       => (string)$u['phone'],
            'email'       => (string)$u['email'],
            'status'      => (string)$u['status'],
            'realName'    => (string)$u['real_name'],
            'lastLoginAt' => (string)$u['last_login_at'],
            'createdAt'   => (string)$u['created_at'],
            'nickname'    => (string)($p['nickname'] ?? ''),
            'inviteCode'  => (string)($p['invite_code'] ?? ''),
            'introduction'=> (string)($p['introduction'] ?? ''),
            'wallet'      => [
                'balance'   => (float)($w['balance'] ?? 0),
                'frozen'    => (float)($w['frozen_balance'] ?? 0),
                'income'    => (float)($w['total_income'] ?? 0),
                'expense'   => (float)($w['total_expense'] ?? 0),
            ],
            'nftCount'    => \think\facade\Db::name('nfts')->where('uid', $id)->where('is_deleted', 0)->count(),
            'orderCount'  => \think\facade\Db::name('orders')->where('uid', $id)->where('is_deleted', 0)->count(),
        ];
    }

    /** 启用/禁用（写操作） */
    public function status()
    {
        $this->needWriter();
        $id     = $this->needInt('id');
        $status = (string)$this->bodyNeed('status');
        if (!in_array($status, ['normal', 'disabled'], true)) {
            throw ApiException::paramType('statusmust be normal|disabled');
        }

        $u = \think\facade\Db::name('users')->where('id', $id)->find();
        if (!$u) {
            throw ApiException::notFound('用户不存在');
        }

        \think\facade\Db::name('users')->where('id', $id)->update(['status' => $status]);
        $this->opLog('users', 'set_status', ['status' => $u['status']], ['status' => $status], 'user:' . $id);

        return ['id' => (string)$id, 'status' => $status];
    }
}
