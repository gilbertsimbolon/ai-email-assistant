<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\PriorityLevel;
use App\Enums\AiCenter\PublishStatus;
use App\Models\Analysis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intent extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'priority',
        'status',
        'version',
        'description',
    ];

    protected $casts = [
        'priority' => PriorityLevel::class,
        'status' => PublishStatus::class,
        'version' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(IntentKeyword::class);
    }

    public function examples(): HasMany
    {
        return $this->hasMany(IntentExample::class);
    }

    public function sops(): HasMany
    {
        return $this->hasMany(Sop::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', PublishStatus::Published);
    }
}
