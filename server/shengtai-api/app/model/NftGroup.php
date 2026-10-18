<?php
declare(strict_types=1);

namespace app\model;

/**
 * 仓库聚合 —— 表 `nft_groups`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftGroup extends BaseModel
{
    protected $table = 'nft_groups';

    protected $type = [
        'type' => 'integer',
    ];

    protected $allowField = [
        'id',
        'iid',
        'name',
        'image',
        'image_url',
        'type',
        'own_quantity',
        'min_price',
        'price_change',
    ];
}
