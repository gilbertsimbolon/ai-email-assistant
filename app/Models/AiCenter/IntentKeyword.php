<?php

namespace App\Models\AiCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentKeyword extends Model
{
    protected $fillable = ['intent_id', 'keyword'];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }
}
