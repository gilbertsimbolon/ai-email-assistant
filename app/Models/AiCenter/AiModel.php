<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\PublishStatus;
use App\Enums\AiCenter\ResponseFormat;
use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Multi-row AI provider/model configuration — the "AI Models" menu. Replaces
 * the legacy single-row AiSetting as the source of truth for
 * AiConfigurationService (see AiConfigurationService::setting()), which
 * still falls back to AiSetting::current() if no AiModel row exists.
 */
class AiModel extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'base_url',
        'model',
        'temperature',
        'top_p',
        'max_tokens',
        'reasoning_effort',
        'presence_penalty',
        'frequency_penalty',
        'response_format',
        'timeout',
        'is_default',
        'enabled',
        'status',
        'version',
    ];

    protected $casts = [
        'provider' => AiProvider::class,
        'api_key' => 'encrypted',
        'temperature' => 'float',
        'top_p' => 'float',
        'max_tokens' => 'integer',
        'presence_penalty' => 'float',
        'frequency_penalty' => 'float',
        'response_format' => ResponseFormat::class,
        'timeout' => 'integer',
        'is_default' => 'boolean',
        'enabled' => 'boolean',
        'status' => PublishStatus::class,
        'version' => 'integer',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function aiLogs()
    {
        return $this->hasMany(AiLog::class);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Makes this row the sole is_default=true row (application-layer
     * enforcement — see the ai_models migration comment on why this isn't a
     * DB partial unique index).
     */
    public function markAsDefault(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
