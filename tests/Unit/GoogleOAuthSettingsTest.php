<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Tests\Fixtures\Team;

it('encrypts tokens at rest and decrypts on read', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);

    $settings = GoogleOAuthSettings::query()->create([
        'team_id' => $team->id,
        'access_token' => 'plain-access',
        'refresh_token' => 'plain-refresh',
        'is_connected' => true,
    ]);

    $raw = DB::table('google_oauth_settings')->where('id', $settings->id)->value('access_token');
    expect($raw)->not->toBe('plain-access');
    expect(Crypt::decryptString($raw))->toBe('plain-access');
    expect($settings->fresh()->access_token)->toBe('plain-access');
});

it('reports token expiry with a five-minute buffer', function (): void {
    $settings = new GoogleOAuthSettings(['token_expires_at' => now()->addMinutes(4)]);
    expect($settings->isTokenExpired())->toBeTrue();

    $settings->token_expires_at = now()->addMinutes(10);
    expect($settings->isTokenExpired())->toBeFalse();
});

it('flags an oauth issue when not connected', function (): void {
    $settings = new GoogleOAuthSettings(['is_connected' => false]);
    expect($settings->getOAuthIssue())->toHaveKeys(['title', 'body']);

    $connected = new GoogleOAuthSettings(['is_connected' => true, 'refresh_token' => 'r']);
    expect($connected->getOAuthIssue())->toBeNull();
});

it('computes resource-selection accessors', function (): void {
    $settings = new GoogleOAuthSettings(['selected_ads_customer_id' => '123']);
    expect($settings->has_ads_selection)->toBeTrue()
        ->and($settings->has_search_console_selection)->toBeFalse()
        ->and($settings->has_ga4_selection)->toBeFalse();
});
