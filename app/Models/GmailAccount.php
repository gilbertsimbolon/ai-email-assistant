<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GmailAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scope',
        'history_id',
        'last_synced_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
