<?php
declare(strict_types=1);

namespace app\model;

/**
 * 抽奖活动 —— 表 `activity_lotteries`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ActivityLottery extends BaseModel
{
    protected $table = 'activity_lotteries';

    protected $autoSoftDelete = true;

    protected $type = [
        'points' => 'integer',
        // ⚠️ schema 里 `status` 是 varchar(32) DEFAULT 'active'（字符串枚举）。
        //    之前这里写成 'integer'，ThinkPHP 会把 'active' 强转成 0，
        //    于是 `where('status','active')` 永远匹配不上、接口恒返回 null。
        'status' => 'string',
    ];

    protected $allowField = [
        'id',
        'name',
        'cover',
        'cover_url',
        'points',
        'start_time',
        'end_time',
        'rule',
        'status',
    ];
}
