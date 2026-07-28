<?php

namespace App\Models\AiCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopTrigger extends Model
{
    protected $fillable = ['sop_id', 'phrase'];

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }
}
