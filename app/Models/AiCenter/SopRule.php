<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\EscalationTarget;
use App\Enums\AiCenter\Tone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SopRule extends Model
{
    protected $fillable = [
        'sop_id',
        'name',
        'order',
        'tone',
        'escalation_target',
    ];

    protected $casts = [
        'order' => 'integer',
        'tone' => Tone::class,
        'escalation_target' => EscalationTarget::class,
    ];

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(SopCondition::class)->orderBy('order');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SopAction::class)->orderBy('order');
    }
}
