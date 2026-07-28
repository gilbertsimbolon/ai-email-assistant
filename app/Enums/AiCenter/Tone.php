<?php

namespace App\Enums\AiCenter;

enum Tone: string
{
    case Friendly = 'friendly';
    case Professional = 'professional';
    case Formal = 'formal';
    case Empathetic = 'empathetic';
    case Neutral = 'neutral';
    case Casual = 'casual';
    case Persuasive = 'persuasive';
    case Custom = 'custom';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
