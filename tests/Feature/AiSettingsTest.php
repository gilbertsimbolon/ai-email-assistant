<?php

use App\Models\AiSetting;
use App\Models\User;

test('non-admin cannot access the AI configuration page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.ai-config.index'))->assertForbidden();
});

test('admin can view the AI configuration page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('settings.ai-config.index'))->assertOk();
});

test('admin can save AI configuration and the api key is persisted encrypted in the database', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('settings.ai-config.update'), [
        'provider' => 'openai',
        'api_key' => 'sk-super-secret-value',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o',
        'temperature' => '0.3',
        'max_tokens' => '1200',
        'timeout' => '60',
        'enabled' => '1',
    ]);

    $response->assertRedirect(route('settings.ai-config.index'));
    $response->assertSessionHas('success');

    $setting = AiSetting::current();
    expect($setting)->not->toBeNull();
    expect($setting->provider)->toBe(App\Enums\AiProvider::OpenAi);
    expect($setting->api_key)->toBe('sk-super-secret-value');
    expect($setting->model)->toBe('gpt-4o');
    expect($setting->enabled)->toBeTrue();

    // Stored ciphertext in the raw database column must not equal the
    // plaintext key — the encrypted cast must actually be encrypting.
    expect($setting->getRawOriginal('api_key'))->not->toBe('sk-super-secret-value');
});

test('saving with a blank api key keeps the previously saved key', function () {
    $admin = User::factory()->admin()->create();

    AiSetting::create([
        'provider' => 'openai',
        'api_key' => 'original-key',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o',
        'temperature' => 0.3,
        'max_tokens' => 1200,
        'timeout' => 60,
        'enabled' => true,
    ]);

    $this->actingAs($admin)->put(route('settings.ai-config.update'), [
        'provider' => 'openai',
        'api_key' => '',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o-mini',
        'temperature' => '0.5',
        'max_tokens' => '800',
        'timeout' => '30',
        'enabled' => '1',
    ]);

    $setting = AiSetting::current();
    expect($setting->model)->toBe('gpt-4o-mini');
    expect($setting->api_key)->toBe('original-key');
});

test('unchecking the enabled checkbox (which browsers omit entirely) disables AI', function () {
    $admin = User::factory()->admin()->create();

    AiSetting::create([
        'provider' => 'openai',
        'api_key' => 'a-key',
        'model' => 'gpt-4o',
        'temperature' => 0.3,
        'max_tokens' => 1200,
        'timeout' => 60,
        'enabled' => true,
    ]);

    $this->actingAs($admin)->put(route('settings.ai-config.update'), [
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'temperature' => '0.3',
        'max_tokens' => '1200',
        'timeout' => '60',
        // no 'enabled' key at all
    ]);

    expect(AiSetting::current()->enabled)->toBeFalse();
});
