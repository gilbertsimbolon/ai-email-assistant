<?php

use App\Models\GmailAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function fakeGoogleOAuthResponses(): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/gmail.modify',
        ], 200),
        'openidconnect.googleapis.com/*' => Http::response(['email' => 'agent@example.com'], 200),
        'gmail.googleapis.com/*' => Http::response(['emailAddress' => 'agent@example.com', 'historyId' => '100'], 200),
    ]);
}

function extractStateFromRedirect(\Illuminate\Testing\TestResponse $response): string
{
    $query = [];
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);

    return $query['state'];
}

test('redirect sends the user to Google with a state parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.gmail.connect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com');
    expect(extractStateFromRedirect($response))->not->toBeEmpty();
});

test('callback rejects a mismatched state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.gmail.connect'));

    $response = $this->actingAs($user)->get(route('settings.gmail.callback', [
        'code' => 'irrelevant',
        'state' => 'not-the-real-state',
    ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHasErrors('gmail');
});

test('callback with a valid state connects the Gmail account', function () {
    $user = User::factory()->create();

    $redirect = $this->actingAs($user)->get(route('settings.gmail.connect'));
    $state = extractStateFromRedirect($redirect);

    fakeGoogleOAuthResponses();

    $response = $this->actingAs($user)->get(route('settings.gmail.callback', [
        'code' => 'auth-code',
        'state' => $state,
    ]));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('success');

    expect(GmailAccount::where('user_id', $user->id)->where('email', 'agent@example.com')->exists())->toBeTrue();
});

test('only the owning user can disconnect a Gmail account', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $account = GmailAccount::create([
        'user_id' => $owner->id,
        'email' => 'agent@example.com',
        'access_token' => 'token',
        'refresh_token' => 'refresh',
    ]);

    Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 200)]);

    $this->actingAs($stranger)->delete(route('settings.gmail.disconnect', $account))->assertForbidden();
    expect(GmailAccount::find($account->id))->not->toBeNull();

    $this->actingAs($owner)->delete(route('settings.gmail.disconnect', $account))->assertRedirect(route('settings.index'));
    expect(GmailAccount::find($account->id))->toBeNull();
});
