<?php
declare(strict_types=1);

namespace app\model;

/**
 * AI 问答 —— 表 `ai_answers`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class AiAnswer extends BaseModel
{
    protected $table = 'ai_answers';

    protected $allowField = [
        'id',
        'uid',
        'question',
        'answer',
        'session_id',
        'token_used',
    ];
}
