<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\WorkflowNodeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowNode extends Model
{
    protected $fillable = [
        'workflow_id',
        'type',
        'label',
        'config',
        'order',
    ];

    protected $casts = [
        'type' => WorkflowNodeType::class,
        'config' => 'array',
        'order' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'from_node_id');
    }
}
