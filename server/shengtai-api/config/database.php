<?php

// 数据库配置 —— 臻海艺术本地后端
// schema 来源：database/create_schema.sql（48 张表）

return [
    'default'         => env('DB_DRIVER', 'mysql'),

    'time_query_rule' => [],

    // 自动写入时间戳：源站业务时间统一用 bigint 毫秒，
    // created_at / updated_at 这两个本地审计字段才用框架的 datetime 自动维护
    'auto_timestamp'  => 'datetime',

    'datetime_format' => 'Y-m-d H:i:s',

    'datetime_field'  => '',

    'connections'     => [
        'mysql' => [
            'type'            => env('DB_TYPE', 'mysql'),
            'hostname'        => env('DB_HOST', '127.0.0.1'),
            'database'        => env('DB_NAME', 'shengtai'),
            'username'        => env('DB_USER', 'root'),
            'password'        => env('DB_PASS', ''),
            'hostport'        => env('DB_PORT', '3306'),
            'params'          => [
                // 雪花 ID 是 19 位 bigint。必须关闭 PDO 模拟预处理，
                // 否则 PHP 拿到的是字符串，比较/自增会出问题
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
            'charset'         => env('DB_CHARSET', 'utf8mb4'),
            'prefix'          => env('DB_PREFIX', ''),

            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => true,
            'trigger_sql'     => env('APP_DEBUG', false),
            'fields_cache'    => false,
        ],
    ],
];
