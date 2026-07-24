<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Enums\SenderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'ghl_message_id',
        'sender_type',
        'message_type',
        'body',
        'attachments',
        'sent_at',
    ];

    protected $casts = [
        'sender_type' => SenderType::class,
        'message_type' => MessageType::class,
        'attachments' => 'array',
        'sent_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
