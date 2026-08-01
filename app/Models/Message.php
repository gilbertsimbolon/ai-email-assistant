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
        'gmail_message_id',
        'ghl_message_id',
        'message_id_header',
        'sender_type',
        'message_type',
        'body',
        'attachments',
        'label_ids',
        'sent_at',
    ];

    protected $casts = [
        'sender_type' => SenderType::class,
        'message_type' => MessageType::class,
        'attachments' => 'array',
        'label_ids' => 'array',
        'sent_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
