<?php
declare(strict_types=1);

namespace app\model;

/**
 * 成交记录 —— 表 `nft_trade_logs`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftTradeLog extends BaseModel
{
    protected $table = 'nft_trade_logs';

    protected $allowField = [
        'id',
        'nft_id',
        'iid',
        'token',
        'chain_id',
        'sold_price',
        'sold_time',
        'order_id',
    ];
}
