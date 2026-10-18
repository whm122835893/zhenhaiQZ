<?php
declare(strict_types=1);

namespace app\model;

/**
 * 藏品主表 —— 表 `items`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class Item extends BaseModel
{
    protected $table = 'items';

    protected $autoSoftDelete = true;

    protected $type = [
        'type' => 'integer',
        'is_primary' => 'integer',
        'is_primary_apply' => 'integer',
        'is_recommend' => 'integer',
        'is_popular' => 'integer',
        'is_give' => 'integer',
        'is_resale' => 'integer',
        'is_secondary' => 'integer',
        'is_secondary_batch' => 'integer',
        'is_secondary_apply' => 'integer',
        'is_secondary_trust' => 'integer',
        'is_sold_out' => 'integer',
        'is_pre' => 'integer',
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'iid',
        'cid',
        'author_id',
        'name',
        'contract',
        'image',
        'image_url',
        'content',
        'price',
        'type',
        'issue_quantity',
        'buy_quantity',
        'nft_quantity',
        'pre_start_time',
        'pre_end_time',
        'start_time',
        'end_time',
        'box_open_time',
        'is_primary',
        'is_primary_apply',
        'is_recommend',
        'is_popular',
        'is_give',
        'is_resale',
        'is_secondary',
        'is_secondary_batch',
        'is_secondary_apply',
        'is_secondary_trust',
        'secondary_low_price',
        'secondary_high_price',
        'secondary_apply_low_price',
        'secondary_apply_high_price',
        'sell_quantity',
        'min_price',
        'max_price',
        'trade_quantity',
        'trade_amount',
        'last_trade_amount',
        'today_trade_quantity',
        'hour_trade_quantity',
        'today_trade_amount',
        'today_trade_max_amount',
        'today_trade_min_amount',
        'price_change',
        'is_sold_out',
        'is_pre',
        'view_count',
        'follower_count',
        'status',
    ];
}
