<?php
declare(strict_types=1);

namespace app\model;

/**
 * 市场关注 —— 表 `nft_market_follows`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NftMarketFollow extends BaseModel
{
    protected $table = 'nft_market_follows';

    protected $allowField = [
        'id',
        'iid',
        'uid',
    ];
}
