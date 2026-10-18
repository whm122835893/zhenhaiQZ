<?php
declare(strict_types=1);

namespace app\model;

/**
 * 用户钱包 —— 表 `user_wallets`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class UserWallet extends BaseModel
{
    protected $table = 'user_wallets';

    protected $allowField = [
        'id',
        'uid',
        'balance',
        'frozen_balance',
        'total_income',
        'total_expense',
        'currency',
    ];
}
