<?php

namespace App\Models\AiCenter;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row holding global AI Center orchestration settings, same
 * pattern as App\Models\AiSetting / App\Models\GmailSetting.
 */
class AiCenterSetting extends Model
{
    protected $fillable = [
        'confidence_review_threshold',
        'default_fallback_tone',
        'default_escalation_target',
        'company_name',
        'default_agent_name',
    ];

    protected $casts = [
        'confidence_review_threshold' => 'float',
    ];

    public static function current(): ?self
    {
        return static::query()->first();
    }
}
