<?php

use App\Models\AiSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copies the single legacy ai_settings row (if any) into ai_models as
     * the new is_default=true row, so upgraded installs keep working through
     * AiConfigurationService's AiModel::default() -> AiSetting::current()
     * fallback without any manual reconfiguration.
     */
    public function up(): void
    {
        $setting = AiSetting::query()->first();

        if (! $setting) {
            return;
        }

        DB::table('ai_models')->insert([
            'name' => 'Migrated Default',
            'provider' => $setting->provider?->value ?? 'openai',
            'api_key' => $setting->getRawOriginal('api_key'),
            'base_url' => $setting->base_url,
            'model' => $setting->model,
            'temperature' => $setting->temperature,
            'max_tokens' => $setting->max_tokens,
            'timeout' => $setting->timeout,
            'is_default' => true,
            'enabled' => $setting->enabled,
            'status' => 'published',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_models')->where('name', 'Migrated Default')->delete();
    }
};
