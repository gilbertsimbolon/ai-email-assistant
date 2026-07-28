<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sop_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_rule_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->json('payload')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_actions');
    }
};
