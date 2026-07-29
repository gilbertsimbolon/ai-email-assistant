<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Cost Estimation
    |--------------------------------------------------------------------------
    |
    | Simple, swappable per-1K-token pricing used to estimate the cost of an
    | AI Center run (see App\Services\Reports\AiCostEstimator). Keyed by the
    | AiModel.model value (case-insensitive, partial match on the key). Add
    | real vendor pricing here as it becomes available — nothing else needs
    | to change, since AiCostEstimator is the only place this file is read.
    |
    */

    'models' => [
        'gpt-5.5-mini' => ['prompt' => 0.00025, 'completion' => 0.00100],
        'gpt-5.5' => ['prompt' => 0.00500, 'completion' => 0.01500],
        'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.00060],
        'gpt-4o' => ['prompt' => 0.00250, 'completion' => 0.01000],
        'claude' => ['prompt' => 0.00300, 'completion' => 0.01500],
        'gemini' => ['prompt' => 0.00025, 'completion' => 0.00100],
    ],

    // Used when the model name doesn't match anything above.
    'default' => ['prompt' => 0.00100, 'completion' => 0.00300],
];
