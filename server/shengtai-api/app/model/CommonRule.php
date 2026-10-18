<?php
declare(strict_types=1);

namespace app\model;

/**
 * 规则 —— 表 `common_rules`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class CommonRule extends BaseModel
{
    protected $table = 'common_rules';

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'rule_key',
        'title',
        'content',
        'version',
        'status',
    ];
}
