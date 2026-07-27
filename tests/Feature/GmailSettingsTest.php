<?php

use App\Models\GmailSetting;
use App\Models\User;
use App\Services\Gmail\GmailConfigurationService;
use Illuminate\Support\Facades\Http;

test('non-admin cannot access the Gmail configuration page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.gmail-config.index'))->assertForbidden();
});

test('admin can view the Gmail configuration page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('settings.gmail-config.index'))->assertOk();
});

test('admin can save Gmail configuration and it is persisted encrypted in the database', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('settings.gmail-config.update'), [
        'client_id' => 'client-id-123.apps.googleusercontent.com',
        'client_secret' => 'super-secret-value',
        'redirect_uri' => 'https://example.test/settings/gmail/callback',
        'enabled' => '1',
    ]);

    $response->assertRedirect(route('settings.gmail-config.index'));
    $response->assertSessionHas('success');

    $setting = GmailSetting::current();
    expect($setting)->not->toBeNull();
    expect($setting->client_id)->toBe('client-id-123.apps.googleusercontent.com');
    expect($setting->client_secret)->toBe('super-secret-value');
    expect($setting->enabled)->toBeTrue();

    // Stored ciphertext in the raw database column must not equal the
    // plaintext secret — the encrypted cast must actually be encrypting.
    expect($setting->getRawOriginal('client_secret'))->not->toBe('super-secret-value');
});

test('saving with a blank client secret keeps the previously saved secret', function () {
    $admin = User::factory()->admin()->create();

    GmailSetting::create([
        'client_id' => 'old-client-id',
        'client_secret' => 'original-secret',
        'redirect_uri' => 'https://example.test/callback',
        'enabled' => true,
    ]);

    $this->actingAs($admin)->put(route('settings.gmail-config.update'), [
        'client_id' => 'new-client-id',
        'client_secret' => '',
        'redirect_uri' => 'https://example.test/callback',
        'enabled' => '1',
    ]);

    $setting = GmailSetting::current();
    expect($setting->client_id)->toBe('new-client-id');
    expect($setting->client_secret)->toBe('original-secret');
});

test('unchecking the enabled checkbox (which browsers omit entirely) disables the integration', function () {
    $admin = User::factory()->admin()->create();

    GmailSetting::create([
        'client_id' => 'client-id',
        'client_secret' => 'secret',
        'redirect_uri' => 'https://example.test/callback',
        'enabled' => true,
    ]);

    // An unchecked HTML checkbox is never submitted at all, so the request
    // deliberately has no "enabled" key.
    $response = $this->actingAs($admin)->put(route('settings.gmail-config.update'), [
        'client_id' => 'client-id',
        'client_secret' => '',
        'redirect_uri' => 'https://example.test/callback',
    ]);

    $response->assertRedirect(route('settings.gmail-config.index'));
    expect(GmailSetting::current()->enabled)->toBeFalse();
});

test('GmailConfigurationService falls back to env-based config when no settings row exists', function () {
    $service = app(GmailConfigurationService::class);

    expect($service->isEnabled())->toBeTrue();
    expect($service->source())->toBe('env');
});

test('GmailConfigurationService reads from the database once a row is saved', function () {
    GmailSetting::create([
        'client_id' => 'db-client-id',
        'client_secret' => 'db-secret',
        'redirect_uri' => 'https://example.test/callback',
        'enabled' => false,
    ]);

    $service = app(GmailConfigurationService::class);

    expect($service->getClientId())->toBe('db-client-id');
    expect($service->getClientSecret())->toBe('db-secret');
    expect($service->isEnabled())->toBeFalse();
    expect($service->source())->toBe('database');
});

test('disabling Gmail integration blocks starting a new OAuth connection', function () {
    GmailSetting::create([
        'client_id' => 'db-client-id',
        'client_secret' => 'db-secret',
        'redirect_uri' => 'https://example.test/callback',
        'enabled' => false,
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.gmail.connect'));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHasErrors('gmail');
});

test('test connection endpoint reports success when Google accepts the client credentials', function () {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $response = $this->actingAs($admin)->postJson(route('settings.gmail-config.test-connection'), [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect_uri' => 'https://example.test/callback',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

test('test connection endpoint reports failure when Google rejects the client credentials', function () {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    $response = $this->actingAs($admin)->postJson(route('settings.gmail-config.test-connection'), [
        'client_id' => 'wrong-client-id',
        'client_secret' => 'wrong-secret',
        'redirect_uri' => 'https://example.test/callback',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => false]);
});

test('non-admin cannot call the test connection endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('settings.gmail-config.test-connection'), [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect_uri' => 'https://example.test/callback',
    ])->assertForbidden();
});
