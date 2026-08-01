<?php

namespace App\Models;

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
        // Not cast to ChannelType — GHL conversations can be SMS/FB/IG/GMB/
        // live chat/etc, not just email/whatsapp (claude.txt section 16),
        // so `channel` stays a plain string. Use channelLabel() for display.
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

    /**
     * `channel` is a plain string (claude.txt section 16 — GHL channels
     * aren't limited to email/whatsapp), but some callers still pass a
     * ChannelType enum instance around (e.g. the AI Center Playground's
     * unsaved Conversation) — normalize either shape to its backing string.
     */
    public function channelValue(): ?string
    {
        return $this->channel instanceof \BackedEnum ? $this->channel->value : $this->channel;
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
