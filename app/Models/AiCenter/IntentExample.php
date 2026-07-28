<?php

namespace App\Models\AiCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentExample extends Model
{
    protected $fillable = ['intent_id', 'example_text'];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }
}
