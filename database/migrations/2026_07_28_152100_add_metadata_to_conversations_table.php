<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Generic bucket for business fields SOP Conditions/Rules and
            // Reply Template variables reference (plan, country, VIP,
            // subscription status, invoice number, etc.) that have no real
            // billing/CRM integration yet. Phase-1 limitation: readers must
            // fall back gracefully when a key is absent.
            $table->json('metadata')->nullable()->after('is_starred');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
