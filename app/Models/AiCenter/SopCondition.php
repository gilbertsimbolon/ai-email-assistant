<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\BooleanOperator;
use App\Enums\AiCenter\ConditionField;
use App\Enums\AiCenter\ConditionOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopCondition extends Model
{
    protected $fillable = [
        'sop_rule_id',
        'field',
        'operator',
        'value',
        'boolean_operator',
        'order',
    ];

    protected $casts = [
        'field' => ConditionField::class,
        'operator' => ConditionOperator::class,
        'boolean_operator' => BooleanOperator::class,
        'order' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SopRule::class, 'sop_rule_id');
    }
}
