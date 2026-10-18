<?php
declare(strict_types=1);

namespace app\model;

/**
 * 订单 —— 表 `orders`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class Order extends BaseModel
{
    protected $table = 'orders';

    protected $autoSoftDelete = true;

    protected $type = [
        'pay_way' => 'integer',
        'status' => 'integer',
        'is_snap_up' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'iid',
        'order_type',
        'pay_way',
        'status',
        'title',
        'image',
        'image_url',
        'unit_price',
        'quantity',
        'item_quantity',
        'amount',
        'subject',
        'out_trade_no',
        'is_snap_up',
        'seller_uid',
        'related_order_id',
        'end_pay_time',
        'pay_time',
        'cancelled_at',
        'remark',
        'created_ts',
    ];
}
