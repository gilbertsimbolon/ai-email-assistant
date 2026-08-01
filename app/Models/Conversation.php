<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Models\AiCenter\AiLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'gmail_account_id',
        'gmail_thread_id',
        'ghl_conversation_id',
        'ghl_location_id',
        'contact_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'channel',
        'subject',
        'status',
        'is_read',
        'is_starred',
        'last_message_at',
        'synced_at',
        'metadata',
    ];

    protected $casts = [
        'status' => ConversationStatus::class,
        'channel' => ChannelType::class,
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'last_message_at' => 'datetime',
        'synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function gmailAccount()
    {
        return $this->belongsTo(GmailAccount::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Only the most recent message — used by the conversation list so it
     * doesn't have to eager-load every message body of every thread just to
     * render a one-line preview.
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    public function analysis()
    {
        return $this->hasOne(Analysis::class);
    }

    public function drafts()
    {
        return $this->hasMany(Draft::class);
    }

    public function aiLogs()
    {
        return $this->hasMany(AiLog::class);
    }
}
