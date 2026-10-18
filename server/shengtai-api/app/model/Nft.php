<?php
declare(strict_types=1);

namespace app\model;

/**
 * NFT 资产 —— 表 `nfts`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class Nft extends BaseModel
{
    protected $table = 'nfts';

    protected $autoSoftDelete = true;

    protected $type = [
        'status' => 'integer',
        'pay_way' => 'integer',
    ];

    protected $allowField = [
        'id',
        'nft_id',
        'uid',
        'iid',
        'name',
        'image',
        'image_url',
        'token',
        'status',
        'get_time',
        'price',
        'buy_price',
        'pay_lock',
        'pay_way',
        'chain_id',
        'chain_completed',
        'sell_status',
    ];
}
