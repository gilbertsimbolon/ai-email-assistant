<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intent_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intent_id')->constrained()->cascadeOnDelete();
            $table->text('example_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intent_examples');
    }
};
