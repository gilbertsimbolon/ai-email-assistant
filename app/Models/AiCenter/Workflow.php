<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\PublishStatus;
use App\Enums\AiCenter\WorkflowNodeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'version',
    ];

    protected $casts = [
        'status' => PublishStatus::class,
        'version' => 'integer',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class)->orderBy('order');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function sops(): HasMany
    {
        return $this->hasMany(Sop::class);
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(AiLog::class);
    }

    public function startNode(): ?WorkflowNode
    {
        return $this->nodes->firstWhere('type', WorkflowNodeType::Start);
    }
}
