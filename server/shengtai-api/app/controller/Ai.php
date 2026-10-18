<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\AiAnswer;
use app\service\Transformer;

/**
 * AI 问答控制器
 *
 * 对应源站 ai 分包。
 *
 * 源站该端点是把问题转发给第三方大模型再返回答案。
 * 本地不接外部服务，改为：
 *   - 命中ai_answers 表里的配置答案则直接返回
 *   - 否则返回兜底话术
 *
 * 这样保持接口契约（字段名、包络）不变，同时不引入外部依赖。
 */
class Ai extends Base
{
    /**
     * POST /ai/answer/get_answer —— 提问
     */
    public function answer()
    {
        $uid = $this->uid();

        $question = trim((string)$this->bodyNeed('question'));

        // 源站前端有最大长度限制，这里服务端也校验
        if (mb_strlen($question) > 500) {
            throw ApiException::paramType('questionis too long');
        }

        $sessionId = (string)($this->body()['sessionId'] ?? '');

        // 精确匹配优先
        $row = AiAnswer::where('question', $question)->order('id', 'asc')->find();

        // 没有精确匹配则尝试关键词包含
        if (!$row) {
            $row = AiAnswer::where('question', 'like', '%' . $question . '%')
                ->order('id', 'asc')
                ->find();
        }

        $answer = $row
            ? (string)$row['answer']
            : '这个问题我还在学习中，换个问法试试吧。';

        // 记录问答日志
        AiAnswer::create([
            'uid'        => $uid,
            'question'   => $question,
            'answer'     => $answer,
            'session_id' => $sessionId,
            'token_used' => mb_strlen($question) + mb_strlen($answer),
        ]);

        return [
            'question'  => $question,
            'answer'    => $answer,
            'sessionId' => $sessionId,
        ];
    }
}