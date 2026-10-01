<?php

declare(strict_types=1);

use Google\Client;
use Illuminate\Support\Facades\Http;
use MadBox\GoogleConnect\Services\GoogleOAuthService;
use MadBox\GoogleConnect\Tests\Fixtures\Team;

it('builds a proxy authorization url carrying the tenant and the given state', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);

    $url = app(GoogleOAuthService::class)->getAuthorizationUrl($team, 'nonce-123');

    expect($url)->toStartWith('https://proxy.test/oauth/google/start?')
        ->and($url)->toContain('tenant='.urlencode('https://tenant.test'))
        ->and($url)->toContain('state=nonce-123')
        ->and($url)->not->toContain('state='.$team->id.'&');
});

it('persists tokens and identity on callback', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);

    Http::fake([
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'a@b.test', 'name' => 'A B']),
    ]);

    $fakeClient = Mockery::mock(Client::class);
    $fakeClient->shouldIgnoreMissing();
    $fakeClient->shouldReceive('fetchAccessTokenWithAuthCode')->with('the-code')
        ->andReturn(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600]);
    $this->app->instance(Client::class, $fakeClient);

    $settings = app(GoogleOAuthService::class)->handleCallback('the-code', $team->id);

    expect($settings->is_connected)->toBeTrue()
        ->and($settings->access_token)->toBe('at')
        ->and($settings->refresh_token)->toBe('rt')
        ->and($settings->connected_email)->toBe('a@b.test');
});

it('reports missing credentials', function (): void {
    config()->set('google-connect.client_id', null);
    expect(app(GoogleOAuthService::class)->hasCredentials())->toBeFalse();
});
