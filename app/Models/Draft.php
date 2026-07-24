<?php

namespace App\Models;

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

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
