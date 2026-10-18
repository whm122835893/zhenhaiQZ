<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\ImGroup as ImGroupModel;
use app\model\ImGroupMember;
use app\model\ImGroupMessage;
use app\model\ImUserSession;
use app\model\User as UserModel;
use app\model\UserProfile;
use app\service\Transformer;

/**
 * IM 控制器
 *
 * 对应源站 im 分包（社群/群聊）。
 *
 * 源站实测：GET /im/group 是公开端点，未登录也能看群列表。
 */
class Im extends Base
{
    /**
     * GET /im/group —— 群列表（公开）
     */
    public function groupList()
    {
        $pageNo   = $this->pageNo();
        $pageSize = $this->pageSize();

        $query = ImGroupModel::where('status', 1)->where('is_deleted', 0);

        $total = (clone $query)->count();

        $rows = $query
            ->order('user_total', 'desc')
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'gid'            => Transformer::id($row['id']),
                'name'           => (string)$row['name'],
                'avatar'         => (string)$row['avatar'],
                'avatarUrl'      => (string)$row['avatar_url'],
                'userTotal'      => (int)$row['user_total'],
                'blacklistTotal' => (int)$row['blacklist_total'],
                'creatorId'      => Transformer::id($row['creator_uid']),
                'createTime'     => Transformer::datetimeTs($row['created_at']),
            ];
        }

        return $this->pageList($data, $total);
    }

    /**
     * GET /im/group/user/{gid} —— 群成员列表
     */
    public function members()
    {
        $gid = (string)($this->request->param('gid') ?? '');

        if ($gid === '') {
            throw ApiException::missing('gid');
        }

        $rows = ImGroupMember::where('gid', $gid)
            ->where('status', 1)
            ->order('joined_at', 'asc')
            ->select()
            ->toArray();

        $data = [];
        foreach ($rows as $r) {
            $profile = UserProfile::where('uid', $r['uid'])->find();
            $arr = $profile ? $profile->toArray() : [];

            $data[] = [
                'uid'         => Transformer::id($r['uid']),
                'nickname'    => (string)($arr['nickname'] ?? ''),
                'avatar'      => (string)($arr['avatar'] ?? ''),
                'avatarUrl'   => (string)($arr['avatar_url'] ?? ''),
                'isBlacklist' => (bool)$r['is_blacklist'],
                'joinedAt'    => (int)$r['joined_at'] > 0
                    ? Transformer::ts($r['joined_at'])
                    : Transformer::datetimeTs($r['joined_at']),
            ];
        }

        return $data;
    }

    /**
     * GET /im/group/message —— 群消息列表
     */
    public function messages()
    {
        $uid = $this->uid();

        $gid = $this->need('gid');

        $query = ImGroupMessage::where('gid', $gid)->where('is_recalled', 0);

        // 若有 lastMessageId 则只取增量（源站的轮询用法）
        $lastId = $this->int('lastMessageId', 0);
        if ($lastId > 0) {
            $query->where('id', '>', $lastId);
        }

        $rows = $query->order('id', 'asc')->limit(200)->select()->toArray();

        $data = [];
        foreach ($rows as $r) {
            $profile = UserProfile::where('uid', $r['uid'])->find();
            $arr = $profile ? $profile->toArray() : [];

            $data[] = [
                'id'        => Transformer::id($r['id']),
                'gid'       => Transformer::id($r['gid']),
                'uid'       => Transformer::id($r['uid']),
                'nickname'  => (string)($arr['nickname'] ?? ''),
                'avatarUrl' => (string)($arr['avatar_url'] ?? ''),
                'type'      => (int)$r['type'],
                'content'   => (string)$r['content'],
                'extra'     => $r['extra'] ? json_decode((string)$r['extra'], true) : null,
                'isRecalled'=> (bool)$r['is_recalled'],
                'sentAt'    => (int)$r['sent_at'] > 0
                    ? Transformer::ts($r['sent_at'])
                    : Transformer::datetimeTs($r['sent_at']),
            ];
        }

        return $data;
    }

    /**
     * POST /im/group/join/{id} —— 加入群
     */
    public function join()
    {
        $uid = $this->uid();
        $gid = (string)($this->request->param('id') ?? '');

        if ($gid === '') {
            throw ApiException::missing('id');
        }

        $group = ImGroupModel::where('id', $gid)->where('status', 1)->where('is_deleted', 0)->find();
        if (!$group) {
            throw ApiException::notFound();
        }

        $exists = ImGroupMember::where('gid', $gid)->where('uid', $uid)->find();

        if ($exists) {
            // 已加入，幂等返回
            return [
                'gid'       => Transformer::id($gid),
                'joined'    => true,
                'joinedAt'  => Transformer::ts($exists['joined_at']),
            ];
        }

        $profile = UserProfile::where('uid', $uid)->find();

        ImGroupMember::create([
            'gid'          => $gid,
            'uid'          => $uid,
            'nickname'     => (string)($profile['nickname'] ?? ''),
            'is_blacklist' => 0,
            'status'       => 1,
            'joined_at'    => $this->nowMillis(),
        ]);

        ImGroupModel::where('id', $gid)->inc('user_total')->update();

        return [
            'gid'      => Transformer::id($gid),
            'joined'   => true,
            'joinedAt' => Transformer::ts($this->nowMillis()),
        ];
    }

    /**
     * POST /im/group/leave/{id} —— 退出群
     */
    public function leave()
    {
        $uid = $this->uid();
        $gid = (string)($this->request->param('id') ?? '');

        if ($gid === '') {
            throw ApiException::missing('id');
        }

        $member = ImGroupMember::where('gid', $gid)->where('uid', $uid)->find();
        if (!$member) {
            throw ApiException::notFound();
        }

        ImGroupMember::where('id', $member['id'])->update([
            'status'    => 0,
            'joined_at' => 0,
        ]);

        ImGroupModel::where('id', $gid)->where('user_total', '>', 0)->dec('user_total')->update();

        return [
            'gid'    => Transformer::id($gid),
            'joined' => false,
        ];
    }
}