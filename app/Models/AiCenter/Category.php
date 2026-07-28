<?php

namespace App\Models\AiCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name'];

    public function intents(): HasMany
    {
        return $this->hasMany(Intent::class);
    }

    public function sops(): HasMany
    {
        return $this->hasMany(Sop::class);
    }
}
