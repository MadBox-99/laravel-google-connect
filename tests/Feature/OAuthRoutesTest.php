<?php

declare(strict_types=1);

use Google\Client;
use Illuminate\Support\Facades\Http;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Tests\Fixtures\Team;
use MadBox\GoogleConnect\Tests\Fixtures\User;

beforeEach(function (): void {
    Route::get('/settings-stub', fn () => 'settings')->name('settings.stub');
    Route::get('/dashboard-stub', fn () => 'dashboard')->name('dashboard.stub');

    $this->team = Team::query()->create(['name' => 'Acme']);
    $this->other = Team::query()->create(['name' => 'Other']);
    $this->actingAs(new User(['id' => 1, 'team_ids' => [$this->team->id]]));
});

/** Start the flow and return the nonce the proxy URL carries. */
function startFlow(Team $team): string
{
    $location = test()->get(route('google.oauth.redirect', ['team' => $team->id]))
        ->assertRedirect()
        ->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query['state'];
}

function fakeTokenExchange(): void
{
    Http::fake([
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'a@b.test', 'name' => 'A B']),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldIgnoreMissing();
    $client->shouldReceive('fetchAccessTokenWithAuthCode')
        ->andReturn(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600]);
    app()->instance(Client::class, $client);
}

it('redirects a member to the proxy with a random state', function (): void {
    $state = startFlow($this->team);

    expect($state)->toHaveLength(40)->not->toBe((string) $this->team->id);
});

it('refuses to start the flow for a team the user cannot access', function (): void {
    $this->get(route('google.oauth.redirect', ['team' => $this->other->id]))->assertForbidden();
});

it('refuses to start the flow for a guest', function (): void {
    auth()->logout();

    $this->get(route('google.oauth.redirect', ['team' => $this->team->id]))->assertForbidden();
});

it('stores the connection for the team that started the flow', function (): void {
    fakeTokenExchange();
    $state = startFlow($this->team);

    $this->get(route('google.oauth.callback', ['state' => $state, 'code' => 'abc']))
        ->assertRedirect(route('settings.stub', ['tenant' => $this->team]));

    expect(GoogleOAuthSettings::query()->where('team_id', $this->team->id)->exists())->toBeTrue();
});

it('takes the team from the session, not from the request', function (): void {
    fakeTokenExchange();
    $state = startFlow($this->team);

    $this->get(route('google.oauth.callback', ['state' => $state, 'code' => 'abc', 'team' => $this->other->id]));

    expect(GoogleOAuthSettings::query()->where('team_id', $this->other->id)->exists())->toBeFalse()
        ->and(GoogleOAuthSettings::query()->where('team_id', $this->team->id)->exists())->toBeTrue();
});

it('rejects a callback whose state was never issued', function (): void {
    fakeTokenExchange();

    $this->get(route('google.oauth.callback', ['state' => (string) $this->team->id, 'code' => 'abc']))
        ->assertRedirect(route('dashboard.stub'));

    expect(GoogleOAuthSettings::query()->count())->toBe(0);
});

it('rejects a replayed callback', function (): void {
    fakeTokenExchange();
    $state = startFlow($this->team);
    $this->get(route('google.oauth.callback', ['state' => $state, 'code' => 'abc']));
    GoogleOAuthSettings::query()->delete();

    $this->get(route('google.oauth.callback', ['state' => $state, 'code' => 'abc']))
        ->assertRedirect(route('dashboard.stub'));

    expect(GoogleOAuthSettings::query()->count())->toBe(0);
});

it('rejects a callback when the user lost access in between', function (): void {
    fakeTokenExchange();
    $state = startFlow($this->team);
    $this->actingAs(new User(['id' => 1, 'team_ids' => []]));

    $this->get(route('google.oauth.callback', ['state' => $state, 'code' => 'abc']))->assertForbidden();

    expect(GoogleOAuthSettings::query()->count())->toBe(0);
});

it('sends a denied consent back to the team settings and consumes the state', function (): void {
    $state = startFlow($this->team);

    $this->get(route('google.oauth.callback', ['state' => $state, 'error' => 'access_denied']))
        ->assertRedirect(route('settings.stub', ['tenant' => $this->team]));

    $this->get(route('google.oauth.callback', ['state' => $state, 'code' => 'abc']))
        ->assertRedirect(route('dashboard.stub'));
});

it('refuses to disconnect a team the user cannot access', function (): void {
    Http::fake();
    GoogleOAuthSettings::query()->create(['team_id' => $this->other->id, 'is_connected' => true, 'refresh_token' => 'rt']);

    $this->post(route('google.oauth.disconnect', ['team' => $this->other->id]))->assertForbidden();

    expect(GoogleOAuthSettings::query()->where('team_id', $this->other->id)->value('is_connected'))->toBeTrue();
});
