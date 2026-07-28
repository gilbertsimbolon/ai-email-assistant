<?php

namespace App\Services\AiCenter\DataTransferObjects;

use Illuminate\Support\Collection;

final class WorkflowRunResult
{
    /**
     * @param  Collection<int, \App\Models\AiCenter\WorkflowNode>  $actions
     */
    public function __construct(
        public readonly Collection $actions,
    ) {
    }
}
