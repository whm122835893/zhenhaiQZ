<?php
declare(strict_types=1);

namespace app\model;

use app\helper\Snowflake;
use think\Model;

/**
 * 模型基类
 *
 * 解决三件事：
 *   1. 主键：源站是 19 位雪花 ID，不用自增，插入时自动生成
 *   2. 时间：源站业务时间统一 bigint 毫秒，不走框架的 datetime 时间戳
 *   3. 软删除：is_deleted 标记位
 */
abstract class BaseModel extends Model
{
    /** 主键：雪花 ID，不用 AUTO_INCREMENT */
    protected $pk = 'id';

    /** 主键类型：字符串（源站 ID 格式） */
    protected $autoWriteTimestamp = false;

    /** 创建时自动生成雪花 ID */
 protected $autoInsertTimestamp = false;

    /** 是否启用软删除（按表实际情况在子类覆盖） */
    protected $autoSoftDelete = false;

    /** 软删除字段名 */
    protected $softDeleteField = 'is_deleted';

    public function onBeforeInsert($model)
    {
        // 缺 id 就补雪花 ID
        $pk = $model->getPk();
        if (empty($model->getData($pk))) {
            $model->set($pk, Snowflake::nextIdStr());
        }
    }

    /**
     * 分页查询：自动排除软删除
     */
    public function scopeAlive($query)
    {
        if ($this->autoSoftDelete) {
            return $query->where($this->softDeleteField, 0);
        }
        return $query;
    }
}
