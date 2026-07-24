<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ghl_conversation_id',
        'ghl_location_id',
        'contact_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'channel',
        'subject',
        'status',
        'last_message_at',
        'synced_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function analysis()
    {
        return $this->hasOne(Analysis::class);
    }

    public function drafts()
    {
        return $this->hasMany(Draft::class);
    }
    
}
