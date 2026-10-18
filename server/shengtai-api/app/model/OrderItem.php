<?php
declare(strict_types=1);

namespace app\model;

/**
 * 订单明细 —— 表 `order_items`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class OrderItem extends BaseModel
{
    protected $table = 'order_items';

    protected $allowField = [
        'id',
        'order_id',
        'nft_id',
        'token',
        'amount',
        'seller_uid',
        'created_ts',
    ];
}
