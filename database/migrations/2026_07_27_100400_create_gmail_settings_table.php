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
        Schema::create('gmail_settings', function (Blueprint $table) {
            $table->id();

            $table->string('client_id')->nullable();

            // Encrypted at the model level (see GmailSetting::casts()), same
            // pattern as GmailAccount's OAuth token columns.
            $table->text('client_secret')->nullable();

            $table->string('redirect_uri')->nullable();

            // Global on/off switch for Gmail integration, independent from
            // whether credentials happen to be filled in.
            $table->boolean('enabled')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_settings');
    }
};
