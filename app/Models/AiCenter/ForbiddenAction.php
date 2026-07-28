<?php

namespace App\Models\AiCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ForbiddenAction extends Model
{
    protected $fillable = ['label', 'description'];

    public function sops(): BelongsToMany
    {
        return $this->belongsToMany(Sop::class, 'sop_forbidden_action');
    }
}
