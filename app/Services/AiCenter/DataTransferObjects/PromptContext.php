<?php

namespace App\Services\AiCenter\DataTransferObjects;

use App\Models\AiCenter\Intent;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\SopRule;
use App\Models\Analysis;
use App\Models\Conversation;
use Illuminate\Support\Collection;

final class PromptContext
{
    /**
     * @param  Collection<int, \App\Models\AiCenter\SopAction>  $ruleActions
     * @param  Collection<int, \App\Models\AiCenter\ForbiddenAction>  $forbiddenActions
     * @param  Collection<int, \App\Models\AiCenter\KnowledgeBase>  $knowledgeBases
     * @param  array<string, string>  $templateVariables
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?Analysis $analysis,
        public readonly ?Intent $intent,
        public readonly ?Sop $sop,
        public readonly ?SopRule $rule,
        public readonly Collection $ruleActions,
        public readonly Collection $forbiddenActions,
        public readonly Collection $knowledgeBases,
        public readonly ?ReplyTemplate $replyTemplate,
        public readonly array $templateVariables,
        public readonly string $thread,
    ) {
    }
}
