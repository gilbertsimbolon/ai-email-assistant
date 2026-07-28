<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\PriorityLevel;
use App\Enums\AiCenter\PublishStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sop extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'intent_id',
        'priority',
        'status',
        'version',
        'description',
        'channels',
        'workflow_id',
        'reply_template_id',
    ];

    protected $casts = [
        'priority' => PriorityLevel::class,
        'status' => PublishStatus::class,
        'version' => 'integer',
        'channels' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function replyTemplate(): BelongsTo
    {
        return $this->belongsTo(ReplyTemplate::class);
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(SopTrigger::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(SopRule::class)->orderBy('order');
    }

    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBase::class, 'sop_knowledge_base');
    }

    public function forbiddenActions(): BelongsToMany
    {
        return $this->belongsToMany(ForbiddenAction::class, 'sop_forbidden_action');
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(AiLog::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', PublishStatus::Published);
    }
}
