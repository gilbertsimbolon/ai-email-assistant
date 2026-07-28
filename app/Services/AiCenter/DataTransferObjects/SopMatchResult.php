<?php

namespace App\Services\AiCenter\DataTransferObjects;

use App\Models\AiCenter\Sop;

final class SopMatchResult
{
    public function __construct(
        public readonly ?Sop $sop,
        public readonly int $score,
    ) {
    }
}
