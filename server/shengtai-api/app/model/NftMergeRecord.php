<?php
declare(strict_types=1);

namespace app\model;

/**
 * 合成记录 —— 表 `nft_merge_records`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftMergeRecord extends BaseModel
{
    protected $table = 'nft_merge_records';

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'mid',
        'consume_nft_ids',
        'produce_nft_ids',
        'status',
    ];
}
