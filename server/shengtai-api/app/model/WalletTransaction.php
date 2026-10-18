<?php
declare(strict_types=1);

namespace app\model;

/**
 * 资金流水 —— 表 `wallet_transactions`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class WalletTransaction extends BaseModel
{
    protected $table = 'wallet_transactions';

    protected $allowField = [
        'id',
        'uid',
        'order_id',
        'trans_type',
        'amount',
        'balance_after',
        'remark',
    ];
}
