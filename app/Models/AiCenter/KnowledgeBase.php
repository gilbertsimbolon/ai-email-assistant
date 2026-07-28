<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\KnowledgeBaseType;
use App\Enums\AiCenter\PublishStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeBase extends Model
{
    protected $fillable = [
        'title',
        'type',
        'content',
        'keywords',
        'status',
        'version',
        'sort_order',
    ];

    protected $casts = [
        'type' => KnowledgeBaseType::class,
        'keywords' => 'array',
        'status' => PublishStatus::class,
        'version' => 'integer',
        'sort_order' => 'integer',
    ];

    public function sops(): BelongsToMany
    {
        return $this->belongsToMany(Sop::class, 'sop_knowledge_base');
    }

    public function scopePublished($query)
    {
        return $query->where('status', PublishStatus::Published);
    }
}
