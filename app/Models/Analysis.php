<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'language',
        'summary',
        'customer_intent',
        'sentiment',
        'priority',
        'last_customer_request',
        'recommended_action',
        'refund_requested',
        'escalation_required',
        'confidence_score',
        'raw_json',
    ];

    protected $casts = [
        'refund_requested' => 'boolean',
        'escalation_required' => 'boolean',
        'confidence_score' => 'decimal:2',
        'raw_json' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
