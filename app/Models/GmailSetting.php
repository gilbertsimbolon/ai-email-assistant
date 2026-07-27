<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row holding the global Gmail OAuth configuration (client id/
 * secret/redirect uri) as managed from the Settings page. Per-account OAuth
 * tokens stay on GmailAccount — this table never stores user tokens.
 */
class GmailSetting extends Model
{
    protected $fillable = [
        'client_id',
        'client_secret',
        'redirect_uri',
        'enabled',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'enabled' => 'boolean',
    ];

    protected $hidden = [
        'client_secret',
    ];

    /**
     * There is only ever one row: the global Gmail configuration.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
