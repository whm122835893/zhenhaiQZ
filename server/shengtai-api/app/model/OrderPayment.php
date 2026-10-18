<?php
declare(strict_types=1);

namespace app\model;

/**
 * 支付流水 —— 表 `order_payments`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class OrderPayment extends BaseModel
{
    protected $table = 'order_payments';

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'order_id',
        'uid',
        'channel',
        'trade_no',
        'cashier_url',
        'amount',
        'status',
        'paid_at',
        'notify_payload',
    ];
}
