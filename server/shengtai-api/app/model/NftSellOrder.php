<?php
declare(strict_types=1);

namespace app\model;

/**
 * 寄售挂单 —— 表 `nft_sell_orders`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftSellOrder extends BaseModel
{
    protected $table = 'nft_sell_orders';

    protected $autoSoftDelete = true;

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'nft_id',
        'iid',
        'uid',
        'price',
        'quantity',
        'sold_quantity',
        'market_type',
        'status',
        'sold_at',
        'buyer_uid',
    ];
}
