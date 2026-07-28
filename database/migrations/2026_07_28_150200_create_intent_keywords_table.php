<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intent_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intent_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->timestamps();

            $table->unique(['intent_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intent_keywords');
    }
};
