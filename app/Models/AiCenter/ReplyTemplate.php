<?php

namespace App\Models\AiCenter;

use App\Enums\AiCenter\PublishStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReplyTemplate extends Model
{
    protected $fillable = [
        'name',
        'category',
        'subject',
        'body',
        'status',
        'version',
    ];

    protected $casts = [
        'status' => PublishStatus::class,
        'version' => 'integer',
    ];

    public function sops(): HasMany
    {
        return $this->hasMany(Sop::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', PublishStatus::Published);
    }
}
