<?php
declare(strict_types=1);

namespace app\model;

/**
 * 版本控制 —— 表 `app_versions`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class AppVersion extends BaseModel
{
    protected $table = 'app_versions';

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'platform',
        'version',
        'min_version',
        'force_update',
        'description',
        'download_url',
        'status',
    ];
}
