<?php
declare(strict_types=1);

namespace app\model;

/**
 * 合成消耗物 —— 表 `nft_merge_needs`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftMergeNeed extends BaseModel
{
    protected $table = 'nft_merge_needs';

    protected $allowField = [
        'id',
        'merge_id',
        'iid',
        'quantity',
        'name',
        'image',
        'image_url',
        'own_quantity',
    ];
}
