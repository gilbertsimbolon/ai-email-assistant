<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\EdgeBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowEdge extends Model
{
    protected $fillable = [
        'workflow_id',
        'from_node_id',
        'to_node_id',
        'branch',
    ];

    protected $casts = [
        'branch' => EdgeBranch::class,
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'to_node_id');
    }
}
