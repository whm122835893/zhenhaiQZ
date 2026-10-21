<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\helper\Snowflake;
use app\service\GrantService;

/**
 * 定向发放任务 —— 新人发放 / 活动空投 / 运营补偿
 *
 * 状态机：草稿0 → 待审1 → 已通过2 → 进行中3 → 已完成4；待审可驳回6；非终态可取消5
 * 权限：写操作 needWriter（超管/运营）；审核额外强校验 审核人 ≠ 创建人。
 * 补偿红线：type=3 必填 ticket_no（关联工单）。
 */
class Grant extends AdminBase
{
    private const TYPES = [1, 2, 3];
    private const TARGET_TYPES = [1, 2, 3];

    /** GET /admin/grant/tasks —— 任务列表 */
    public function index()
    {
        $q = \think\facade\Db::name('grant_task');

        $type = $this->int('type', 0);
        if (in_array($type, self::TYPES, true)) {
            $q->where('type', $type);
        }
        $status = $this->int('status', -1);
        if ($status >= 0) {
            $q->where('status', $status);
        }
        $q->order('id', 'desc');

        $data = $this->adminPage($q);
        foreach ($data['list'] as &$r) {
            $r = $this->shapeTask($r);
        }
        unset($r);
        return $data;
    }

    /** GET /admin/grant/tasks/:id —— 详情（含进度统计） */
    public function detail()
    {
        $task = \think\facade\Db::name('grant_task')->where('id', (int)$this->routeId())->find();
        if (!$task) {
            throw ApiException::notFound();
        }
        $task['progress'] = $this->progressOf($task);
        return $this->shapeTask($task);
    }

    /** POST /admin/grant/task —— 创建草稿 */
    public function create()
    {
        $this->needWriter();
        $in = $this->taskInput();

        $id = Snowflake::nextIdStr();
        \think\facade\Db::name('grant_task')->insert($in + [
            'id'         => $id,
            'status'     => GrantService::STATUS_DRAFT,
            'creator_id' => (int)$this->aid(),
        ]);
        $this->opLog('grant', 'create', null, ['name' => $in['name'], 'type' => $in['type']], 'grant_task:' . $id);
        return ['id' => $id];
    }

    /** PUT /admin/grant/tasks/:id —— 编辑草稿（仅草稿/驳回可改） */
    public function update()
    {
        $this->needWriter();
        $task = $this->mustFind($this->routeId());
        if (!in_array((int)$task['status'], [GrantService::STATUS_DRAFT, GrantService::STATUS_REJECTED], true)) {
            throw new ApiException('仅草稿或已驳回任务可编辑', ApiException::CODE_SYSTEM_ERROR);
        }
        \think\facade\Db::name('grant_task')->where('id', $task['id'])->update($this->taskInput());
        $this->opLog('grant', 'update', ['status' => $task['status']], null, 'grant_task:' . $task['id']);
        return ['ok' => true];
    }

    /** POST /admin/grant/tasks/:id/submit —— 提交审核（草稿/驳回 → 待审） */
    public function submit()
    {
        $this->needWriter();
        $task = $this->mustFind($this->routeId());
        if (!in_array((int)$task['status'], [GrantService::STATUS_DRAFT, GrantService::STATUS_REJECTED], true)) {
            throw new ApiException('当前状态不可提交审核', ApiException::CODE_SYSTEM_ERROR);
        }
        \think\facade\Db::name('grant_task')->where('id', $task['id'])
            ->update(['status' => GrantService::STATUS_PENDING]);
        $this->opLog('grant', 'submit', ['status' => $task['status']], ['status' => 1], 'grant_task:' . $task['id']);
        return ['ok' => true];
    }

    /** POST /admin/grant/tasks/:id/audit —— 审核（通过/驳回），审核人 ≠ 创建人 */
    public function audit()
    {
        $this->needWriter();
        $task = $this->mustFind($this->routeId());
        if ((int)$task['status'] !== GrantService::STATUS_PENDING) {
            throw new ApiException('任务不在待审状态', ApiException::CODE_SYSTEM_ERROR);
        }
        if ((int)$task['creator_id'] === (int)$this->aid()) {
            throw new ApiException('不能审核自己创建的任务', ApiException::CODE_SYSTEM_ERROR);
        }

        $body   = $this->body();
        $pass   = filter_var($body['pass'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $remark = trim((string)($body['remark'] ?? ''));
        if (!$pass && $remark === '') {
            throw new ApiException('驳回必须填写审核意见', ApiException::CODE_SYSTEM_ERROR);
        }

        \think\facade\Db::name('grant_task')->where('id', $task['id'])->update([
            'status'       => $pass ? GrantService::STATUS_APPROVED : GrantService::STATUS_REJECTED,
            'auditor_id'   => (int)$this->aid(),
            'audit_remark' => $remark,
        ]);
        $this->opLog('grant', $pass ? 'audit_pass' : 'audit_reject', ['status' => 1], ['status' => $pass ? 2 : 6], 'grant_task:' . $task['id']);
        return ['ok' => true];
    }

    /** POST /admin/grant/tasks/:id/cancel —— 取消（非终态），清理未发的待发记录 */
    public function cancel()
    {
        $this->needWriter();
        $task = $this->mustFind($this->routeId());
        if (in_array((int)$task['status'], [GrantService::STATUS_DONE, GrantService::STATUS_CANCELED], true)) {
            throw new ApiException('任务已是终态', ApiException::CODE_SYSTEM_ERROR);
        }
        \think\facade\Db::transaction(function () use ($task) {
            // 未开始的待发记录直接作废；进行中已写入的待发也一并清理
            \think\facade\Db::name('grant_record')
                ->where('task_id', $task['id'])->whereIn('status', [0, 1])
                ->update(['status' => 4, 'fail_reason' => '任务已取消']);
            \think\facade\Db::name('grant_task')->where('id', $task['id'])
                ->update(['status' => GrantService::STATUS_CANCELED]);
        });
        $this->opLog('grant', 'cancel', ['status' => $task['status']], ['status' => 5], 'grant_task:' . $task['id']);
        return ['ok' => true];
    }

    /** POST /admin/grant/tasks/:id/execute —— 执行（解析人群 → 分批发放） */
    public function execute()
    {
        $this->needWriter();
        $task = $this->mustFind($this->routeId());
        if (!in_array((int)$task['status'], [GrantService::STATUS_APPROVED, GrantService::STATUS_RUNNING], true)) {
            throw new ApiException('仅「已通过/进行中」任务可执行', ApiException::CODE_SYSTEM_ERROR);
        }
        $stat = GrantService::execute($task);
        $this->opLog('grant', 'execute', null, $stat, 'grant_task:' . $task['id']);
        return $stat;
    }

    /** POST /admin/grant/tasks/:id/retry —— 失败记录重发 */
    public function retry()
    {
        $this->needWriter();
        $task = $this->mustFind($this->routeId());
        if (!in_array((int)$task['status'], [GrantService::STATUS_RUNNING, GrantService::STATUS_DONE], true)) {
            throw new ApiException('仅「进行中/已完成」任务可重发失败记录', ApiException::CODE_SYSTEM_ERROR);
        }
        $stat = GrantService::retryFailed($task);
        $this->opLog('grant', 'retry', null, $stat, 'grant_task:' . $task['id']);
        return $stat;
    }

    /** GET /admin/grant/tasks/:id/preview —— 预览将发放人数/份数 */
    public function preview()
    {
        $task = $this->mustFind($this->routeId());
        return GrantService::preview($task);
    }

    /** GET /admin/grant/tasks/:id/records —— 发放记录分页 */
    public function records()
    {
        $task = $this->mustFind($this->routeId());
        $q = \think\facade\Db::name('grant_record')->where('task_id', $task['id']);

        $rs = $this->int('status', -1);
        if ($rs >= 0) {
            $q->where('status', $rs);
        }
        $q->order('id', 'desc');

        $data = $this->adminPage($q);
        $uids = array_values(array_unique(array_column($data['list'], 'uid')));
        $users = $uids === [] ? [] : \think\facade\Db::name('user_profiles')->whereIn('uid', $uids)->column('nickname', 'uid');
        foreach ($data['list'] as &$r) {
            $r['id']        = (string)$r['id'];
            $r['uid']       = (string)$r['uid'];
            $r['nickname']  = (string)($users[$r['uid']] ?? ('用户' . substr((string)$r['uid'], -4)));
            $r['statusName']= self::recordStatusName((int)$r['status']);
        }
        unset($r);
        return $data;
    }

    /* ====================================================================
     * 内部工具
     * ================================================================== */

    private function routeId(): string
    {
        return (string)$this->request->param('id');
    }

    private function mustFind(string $id): array
    {
        $task = \think\facade\Db::name('grant_task')->where('id', (int)$id)->find();
        if (!$task) {
            throw ApiException::notFound();
        }
        return $task;
    }

    /** 创建/编辑入参收集与校验 */
    private function taskInput(): array
    {
        $body = $this->body();
        $name = trim((string)($body['name'] ?? ''));
        $type = (int)($body['type'] ?? 0);
        $iid  = (string)($body['iid'] ?? '');
        $perUser  = max(1, (int)($body['quantityPerUser'] ?? 1));
        $total    = max(0, (int)($body['totalQuantity'] ?? 0));
        $targetType = (int)($body['targetType'] ?? 3);
        $ticketNo = trim((string)($body['ticketNo'] ?? ''));
        $remark   = trim((string)($body['remark'] ?? ''));
        $start    = trim((string)($body['startTime'] ?? ''));
        $end      = trim((string)($body['endTime'] ?? ''));

        if ($name === '') {
            throw ApiException::missing('name');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new ApiException('任务类型不合法', ApiException::CODE_SYSTEM_ERROR);
        }
        if ($type === GrantService::TYPE_COMPENSE && $ticketNo === '') {
            throw new ApiException('运营补偿任务必须关联工单号', ApiException::CODE_SYSTEM_ERROR);
        }
        $item = \think\facade\Db::name('items')->where('id', (int)$iid)->where('is_deleted', 0)
            ->field('id,name')->find();
        if (!$item) {
            throw new ApiException('发放藏品不存在', ApiException::CODE_SYSTEM_ERROR);
        }
        if (!in_array($targetType, self::TARGET_TYPES, true)) {
            throw new ApiException('目标类型不合法', ApiException::CODE_SYSTEM_ERROR);
        }
        if ($total > 0 && $total < $perUser) {
            throw new ApiException('计划总量不能小于每人数量', ApiException::CODE_SYSTEM_ERROR);
        }

        // 目标规则收集：1=注册时间 2=持仓藏品 3=uid 名单
        $rule = [];
        if ($targetType === 1) {
            $after  = trim((string)($body['registerAfter'] ?? ''));
            $before = trim((string)($body['registerBefore'] ?? ''));
            if ($after === '' && $before === '' && $type === GrantService::TYPE_NEWBIE) {
                // 新人任务允许全空 = 生效期内所有新注册
            }
            $rule = ['registerAfter' => $after, 'registerBefore' => $before];
        } elseif ($targetType === 2) {
            $holdIid = trim((string)($body['holdIid'] ?? ''));
            if ($holdIid === '') {
                throw ApiException::missing('holdIid');
            }
            $rule = ['holdIid' => $holdIid];
        } else {
            $uids = array_values(array_unique(array_filter(array_map(
                static fn ($v) => trim((string)$v),
                is_array($body['uids'] ?? null) ? $body['uids'] : preg_split('/[\s,;，；]+/', (string)($body['uids'] ?? ''))
            ))));
            if ($uids === []) {
                throw ApiException::missing('uids');
            }
            if (count($uids) > 5000) {
                throw new ApiException('单次名单上限 5000 人', ApiException::CODE_SYSTEM_ERROR);
            }
            $rule = ['uids' => $uids];
        }

        // 触发方式由类型决定：新人=事件驱动；空投/补偿=手动
        $trigger = $type === GrantService::TYPE_NEWBIE ? 1 : 3;

        return [
            'name'              => $name,
            'type'              => $type,
            'iid'               => (int)$iid,
            'quantity_per_user' => $perUser,
            'total_quantity'    => $total,
            'target_type'       => $targetType,
            'target_rule'       => json_encode($rule, JSON_UNESCAPED_UNICODE),
            'trigger_type'      => $trigger,
            'start_time'        => $start !== '' ? $start : null,
            'end_time'          => $end !== '' ? $end : null,
            'ticket_no'         => $ticketNo,
            'remark'            => $remark,
        ];
    }

    /** 输出整形 */
    private function shapeTask(array $r): array
    {
        $r['id'] = (string)$r['id'];
        $r['iid'] = (string)$r['iid'];
        $r['creatorId'] = (string)$r['creator_id'];
        $r['auditorId'] = (string)$r['auditor_id'];
        $r['statusName'] = self::taskStatusName((int)$r['status']);
        $r['typeName'] = [1 => '新人发放', 2 => '活动空投', 3 => '运营补偿'][(int)$r['type']] ?? '未知';
        $r['targetTypeName'] = [1 => '注册时间规则', 2 => '持仓快照', 3 => '指定名单'][(int)$r['target_type']] ?? '未知';
        $rule = json_decode((string)$r['target_rule'], true) ?: [];
        $r['targetRule'] = $rule;
        unset($r['target_rule'], $r['creator_id'], $r['auditor_id']);

        // 藏品摘要
        $item = \think\facade\Db::name('items')->where('id', (int)$r['iid'])
            ->field('name,image,image_url')->find();
        $r['itemName'] = (string)($item['name'] ?? '');
        $r['itemImage'] = (string)(($item['image_url'] ?? '') ?: ($item['image'] ?? ''));

        // 创建/审核人姓名
        $adminIds = array_filter([(int)$r['creatorId'], (int)$r['auditorId']]);
        if ($adminIds !== []) {
            $names = \think\facade\Db::name('admin_users')->whereIn('id', $adminIds)->column('real_name', 'id');
            $r['creatorName'] = (string)($names[(int)$r['creatorId']] ?? '');
            $r['auditorName'] = (string)($names[(int)$r['auditorId']] ?? '');
        } else {
            $r['creatorName'] = '';
            $r['auditorName'] = '';
        }
        return $r;
    }

    private function progressOf(array $task): array
    {
        $rows = \think\facade\Db::name('grant_record')
            ->where('task_id', $task['id'])
            ->group('status')
            ->field('status, COUNT(*) AS cnt')
            ->select()->toArray();
        $map = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($rows as $r) {
            $map[(int)$r['status']] = (int)$r['cnt'];
        }
        return [
            'pending' => $map[0] + $map[1],
            'success' => $map[2],
            'failed'  => $map[3],
            'canceled'=> $map[4],
            'pieces'  => (int)$task['granted_quantity'],
        ];
    }

    private static function taskStatusName(int $s): string
    {
        return [0 => '草稿', 1 => '待审核', 2 => '已通过', 3 => '进行中', 4 => '已完成', 5 => '已取消', 6 => '已驳回'][$s] ?? '未知';
    }

    private static function recordStatusName(int $s): string
    {
        return [0 => '待发放', 1 => '发放中', 2 => '成功', 3 => '失败', 4 => '已取消'][$s] ?? '未知';
    }
}
