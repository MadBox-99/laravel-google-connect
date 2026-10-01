<?php

declare(strict_types=1);

use Google\Client;
use Illuminate\Support\Facades\Http;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Services\GoogleOAuthService;
use MadBox\GoogleConnect\Services\GoogleResourceFetcher;
use MadBox\GoogleConnect\Tests\Fixtures\Team;

beforeEach(function (): void {
    $this->team = Team::query()->create(['name' => 'Acme']);

    Http::fake([
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'y@b.test']),
        'www.googleapis.com/webmasters/v3/sites' => Http::sequence()
            ->push(['siteEntry' => [['siteUrl' => 'sc-domain:account-x.test']]])
            ->push(['siteEntry' => [['siteUrl' => 'sc-domain:account-y.test']]]),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldIgnoreMissing();
    $client->shouldReceive('fetchAccessTokenWithAuthCode')
        ->andReturn(['access_token' => 'at-y', 'refresh_token' => 'rt-y', 'expires_in' => 3600]);
    app()->instance(Client::class, $client);

    $this->settings = GoogleOAuthSettings::query()->create([
        'team_id' => $this->team->id,
        'is_connected' => true,
        'access_token' => 'at-x',
        'refresh_token' => 'rt-x',
        'token_expires_at' => now()->addHour(),
    ]);

    // Account X's list is now cached for this team.
    expect(app(GoogleResourceFetcher::class)->listSearchConsoleSites($this->settings)[0]['id'])
        ->toBe('sc-domain:account-x.test');
});

it('forgets the previous account resource lists when a new account connects', function (): void {
    $settings = app(GoogleOAuthService::class)->handleCallback('code', $this->team->id);

    expect(app(GoogleResourceFetcher::class)->listSearchConsoleSites($settings)[0]['id'])
        ->toBe('sc-domain:account-y.test');
});

it('forgets the resource lists on disconnect', function (): void {
    app(GoogleOAuthService::class)->disconnect($this->team);

    $this->settings->fresh()->update(['is_connected' => true, 'access_token' => 'at-y', 'refresh_token' => 'rt-y', 'token_expires_at' => now()->addHour()]);

    expect(app(GoogleResourceFetcher::class)->listSearchConsoleSites($this->settings->fresh())[0]['id'])
        ->toBe('sc-domain:account-y.test');
});
