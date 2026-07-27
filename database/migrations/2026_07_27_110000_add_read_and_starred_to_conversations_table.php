<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_read')->default(false)->after('status');
            $table->boolean('is_starred')->default(false)->after('is_read');

            $table->index('is_read');
            $table->index('is_starred');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['is_read']);
            $table->dropIndex(['is_starred']);
            $table->dropColumn(['is_read', 'is_starred']);
        });
    }
};
