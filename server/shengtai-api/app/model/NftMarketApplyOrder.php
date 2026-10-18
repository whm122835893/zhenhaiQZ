<?php
declare(strict_types=1);

namespace app\model;

/**
 * 求购单 —— 表 `nft_market_apply_orders`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftMarketApplyOrder extends BaseModel
{
    protected $table = 'nft_market_apply_orders';

    protected $autoSoftDelete = true;

    protected $type = [
        'pay_way' => 'integer',
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'iid',
        'title',
        'image',
        'image_url',
        'unit_price',
        'quantity',
        'completed_quantity',
        'amount',
        'created_ts',
        'pay_way',
        'status',
        'expire_at',
    ];
}
