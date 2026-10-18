<?php
declare(strict_types=1);

namespace app\model;

/**
 * 合成配方主表 —— 表 `nft_merge_recipes`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftMergeRecipe extends BaseModel
{
    protected $table = 'nft_merge_recipes';

    protected $autoSoftDelete = true;

    protected $type = [
        'type' => 'integer',
        'is_pre' => 'integer',
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'name',
        'cover',
        'cover_url',
        'content',
        'type',
        'merge_quantity',
        'pre_start_time',
        'pre_end_time',
        'start_time',
        'end_time',
        'merge_time',
        'is_pre',
        'status',
    ];
}
