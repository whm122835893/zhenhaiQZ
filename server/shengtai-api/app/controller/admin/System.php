<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;

/**
 * 系统管理 —— 运营参数读写 + 审计/操作日志查询
 */
class System extends AdminBase
{
    /** GET /admin/system/configs */
    public function configs()
    {
        $rows = \think\facade\Db::name('system_configs')
            ->where('is_deleted', 0)
            ->field('id,config_key,config_value,remark,updated_at')
            ->order('config_key', 'asc')
            ->select()->toArray();
        foreach ($rows as &$r) {
            $r['id'] = (string)$r['id'];
        }
        unset($r);
        return ['list' => $rows];
    }

    /** PUT /admin/system/configs —— 批量保存 {items:[{key,value}]} */
    public function configsSave()
    {
        $this->needWriter();
        $items = $this->bodyNeed('items');
        if (!is_array($items)) {
            throw ApiException::paramType('itemsmust be array');
        }

        $changed = [];
        foreach ($items as $it) {
            $key   = (string)($it['key'] ?? '');
            $value = (string)($it['value'] ?? '');
            if ($key === '') {
                continue;
            }
            $row = \think\facade\Db::name('system_configs')->where('config_key', $key)->where('is_deleted', 0)->find();
            if ($row) {
                if ((string)$row['config_value'] === $value) {
                    continue;
                }
                \think\facade\Db::name('system_configs')->where('id', (int)$row['id'])->update(['config_value' => $value]);
                $changed[] = ['key' => $key, 'from' => (string)$row['config_value'], 'to' => $value];
            } else {
                // 新键 upsert：首次保存品牌等未预置的配置项时直接插入，避免静默丢失
                \think\facade\Db::name('system_configs')->insert([
                    'config_key'   => $key,
                    'config_value' => $value,
                    'remark'       => '',
                ]);
                $changed[] = ['key' => $key, 'from' => null, 'to' => $value];
            }
        }

        if ($changed !== []) {
            $this->opLog('system', 'configs_update', null, $changed);
            // 清静态缓存：同进程内 SystemConfigService 的请求级缓存随请求结束释放，无需处理
        }
        return ['changed' => $changed];
    }

    /** GET /admin/system/logs/audit */
    public function auditLogs()
    {
        $q = \think\facade\Db::name('audit_logs')->field('id,actor_type,actor_id,module,action,target,detail,ip,created_at');
        $module = $this->str('module');
        if ($module !== '') {
            $q->where('module', $module);
        }
        $q->order('id', 'desc');
        return $this->adminPage($q);
    }

    /** GET /admin/system/logs/operation */
    public function operationLogs()
    {
        $q = \think\facade\Db::name('operation_logs')
            ->alias('l')
            ->leftJoin('admin_users a', 'a.id = l.admin_id')
            ->field('l.id,l.admin_id,a.username,l.module,l.action,l.target,l.before_data,l.after_data,l.ip,l.created_at');
        $module = $this->str('module');
        if ($module !== '') {
            $q->where('l.module', $module);
        }
        $q->order('l.id', 'desc');
        return $this->adminPage($q);
    }
}
