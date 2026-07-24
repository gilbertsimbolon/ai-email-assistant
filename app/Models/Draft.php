<?php

namespace App\Models;

use App\Enums\DraftStatus;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Draft extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'type',
        'provider',
        'content',
        'version',
        'status',
    ];

    protected $casts = [
        'status' => DraftStatus::class,
        'type' => MessageType::class,
        'content' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
