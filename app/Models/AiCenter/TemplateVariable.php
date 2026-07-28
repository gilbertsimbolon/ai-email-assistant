<?php

namespace App\Models\AiCenter;

use Illuminate\Database\Eloquent\Model;

class TemplateVariable extends Model
{
    protected $fillable = ['key', 'label', 'description'];
}
