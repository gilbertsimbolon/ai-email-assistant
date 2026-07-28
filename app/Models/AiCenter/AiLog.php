<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\AiCenterLogSource;
use App\Enums\AiCenter\AiCenterLogStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLog extends Model
{
    protected $fillable = [
        'source',
        'conversation_id',
        'intent_id',
        'sop_id',
        'workflow_id',
        'reply_template_id',
        'ai_model_id',
        'triggered_by',
        'matched_rule_ids',
        'matched_action_types',
        'matched_knowledge_base_ids',
        'prompt',
        'response',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'cost',
        'confidence_score',
        'status',
        'error',
    ];

    protected $casts = [
        'source' => AiCenterLogSource::class,
        'status' => AiCenterLogStatus::class,
        'matched_rule_ids' => 'array',
        'matched_action_types' => 'array',
        'matched_knowledge_base_ids' => 'array',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'latency_ms' => 'integer',
        'cost' => 'float',
        'confidence_score' => 'float',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function replyTemplate(): BelongsTo
    {
        return $this->belongsTo(ReplyTemplate::class);
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
