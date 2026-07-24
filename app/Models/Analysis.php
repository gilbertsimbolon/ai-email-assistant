<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\Priority;
use App\Enums\Sentiment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Analysis extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'conversation_id',
        'language',
        'summary',
        'customer_intent',
        'sentiment',
        'customer_status',
        'priority',
        'last_customer_request',
        'recommended_action',
        'refund_requested',
        'escalation_required',
        'confidence_score',
        'raw_json',
    ];

    /**
     * Default attributes.
     */
    protected $attributes = [
        'refund_requested' => false,
        'escalation_required' => false,
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'priority' => Priority::class,
        'sentiment' => Sentiment::class,
        'customer_status' => CustomerStatus::class,

        'refund_requested' => 'boolean',
        'escalation_required' => 'boolean',

        'confidence_score' => 'decimal:2',

        'raw_json' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors / Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isHighPriority(): bool
    {
        return $this->priority === Priority::High;
    }

    public function isMediumPriority(): bool
    {
        return $this->priority === Priority::Medium;
    }

    public function isLowPriority(): bool
    {
        return $this->priority === Priority::Low;
    }

    public function isPositive(): bool
    {
        return $this->sentiment === Sentiment::Positive;
    }

    public function isNeutral(): bool
    {
        return $this->sentiment === Sentiment::Neutral;
    }

    public function isNegative(): bool
    {
        return $this->sentiment === Sentiment::Negative;
    }

    public function needsEscalation(): bool
    {
        return $this->escalation_required;
    }

    public function isRefundRequest(): bool
    {
        return $this->refund_requested;
    }

    public function isExistingCustomer(): bool
    {
        return $this->customer_status === CustomerStatus::ExistingCustomer;
    }

    public function isNewCustomer(): bool
    {
        return $this->customer_status === CustomerStatus::NewCustomer;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', Priority::High);
    }

    public function scopeMediumPriority(Builder $query): Builder
    {
        return $query->where('priority', Priority::Medium);
    }

    public function scopeLowPriority(Builder $query): Builder
    {
        return $query->where('priority', Priority::Low);
    }

    public function scopeEscalated(Builder $query): Builder
    {
        return $query->where('escalation_required', true);
    }

    public function scopeRefund(Builder $query): Builder
    {
        return $query->where('refund_requested', true);
    }

    public function scopePositive(Builder $query): Builder
    {
        return $query->where('sentiment', Sentiment::Positive);
    }

    public function scopeNeutral(Builder $query): Builder
    {
        return $query->where('sentiment', Sentiment::Neutral);
    }

    public function scopeNegative(Builder $query): Builder
    {
        return $query->where('sentiment', Sentiment::Negative);
    }

    public function scopeExistingCustomer(Builder $query): Builder
    {
        return $query->where('customer_status', CustomerStatus::ExistingCustomer);
    }
}