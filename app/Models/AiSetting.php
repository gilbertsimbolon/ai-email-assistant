<?php

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row holding the global AI provider configuration (provider,
 * api key, base url, model, temperature, max tokens, timeout, enabled) as
 * managed from the Settings page. This is the only place AI credentials are
 * stored — no provider config lives in .env anymore.
 */
class AiSetting extends Model
{
    protected $fillable = [
        'provider',
        'api_key',
        'base_url',
        'model',
        'temperature',
        'max_tokens',
        'timeout',
        'enabled',
    ];

    protected $casts = [
        'provider' => AiProvider::class,
        'api_key' => 'encrypted',
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'timeout' => 'integer',
        'enabled' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * There is only ever one row: the global AI configuration.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
