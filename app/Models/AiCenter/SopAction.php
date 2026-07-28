<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\AiAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopAction extends Model
{
    protected $fillable = [
        'sop_rule_id',
        'action_type',
        'payload',
        'order',
    ];

    protected $casts = [
        'action_type' => AiAction::class,
        'payload' => 'array',
        'order' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SopRule::class, 'sop_rule_id');
    }
}
