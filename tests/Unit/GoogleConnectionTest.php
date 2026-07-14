<?php

declare(strict_types=1);

use MadBox\GoogleConnect\ConnectedGoogle;
use MadBox\GoogleConnect\GoogleConnection;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Tests\Fixtures\Team;

it('returns null when the team has no connection', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    expect(GoogleConnection::for($team))->toBeNull();
});

it('returns null when connected flag is false or refresh token missing', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    GoogleOAuthSettings::query()->create([
        'team_id' => $team->id,
        'is_connected' => false,
        'refresh_token' => 'rt',
    ]);
    expect(GoogleConnection::for($team))->toBeNull();
});

it('returns null when connected flag is true but refresh token is null', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    GoogleOAuthSettings::query()->create([
        'team_id' => $team->id,
        'is_connected' => true,
        'refresh_token' => null,
    ]);
    expect(GoogleConnection::for($team))->toBeNull();
});

it('returns null for an unsaved team without querying', function (): void {
    expect(GoogleConnection::for(new Team(['name' => 'Acme'])))->toBeNull();
});

it('returns a ConnectedGoogle exposing selected resources when connected', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    GoogleOAuthSettings::query()->create([
        'team_id' => $team->id,
        'is_connected' => true,
        'refresh_token' => 'rt',
        'selected_ga4_property_id' => 'properties/123',
        'selected_search_console_site_url' => 'https://a.test/',
        'selected_ads_customer_id' => '111',
    ]);

    $conn = GoogleConnection::for($team);

    expect($conn)->toBeInstanceOf(ConnectedGoogle::class)
        ->and($conn->ga4PropertyId())->toBe('properties/123')
        ->and($conn->searchConsoleSiteUrl())->toBe('https://a.test/')
        ->and($conn->adsCustomerId())->toBe('111');
});
