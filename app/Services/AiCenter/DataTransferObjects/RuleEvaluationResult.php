<?php

namespace App\Services\AiCenter\DataTransferObjects;

use App\Models\AiCenter\SopRule;
use Illuminate\Support\Collection;

final class RuleEvaluationResult
{
    /**
     * @param  Collection<int, \App\Models\AiCenter\SopAction>  $actions
     */
    public function __construct(
        public readonly ?SopRule $rule,
        public readonly Collection $actions,
    ) {
    }
}
