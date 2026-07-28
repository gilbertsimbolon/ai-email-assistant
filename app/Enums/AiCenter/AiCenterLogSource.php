<?php

namespace App\Enums\AiCenter;

enum AiCenterLogSource: string
{
    case Production = 'production';
    case Playground = 'playground';
}
