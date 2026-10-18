<?php
declare(strict_types=1);

namespace app\model;

/**
 * 开箱记录 —— 表 `nft_box_open_logs`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftBoxOpenLog extends BaseModel
{
    protected $table = 'nft_box_open_logs';

    protected $type = [
        'pay_way' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'iid',
        'nft_id',
        'cost_amount',
        'pay_way',
    ];
}
