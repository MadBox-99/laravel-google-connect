<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use MadBox\GoogleConnect\Livewire\GoogleConnect;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Tests\Fixtures\Team;
use MadBox\GoogleConnect\Tests\Fixtures\User;

beforeEach(function (): void {
    Route::get('/settings-stub', fn () => 'settings')->name('settings.stub');

    $this->team = Team::query()->create(['name' => 'Acme']);
    $this->actingAs(new User(['id' => 1, 'team_ids' => [$this->team->id]]));
    Filament::shouldReceive('getTenant')->andReturn($this->team);
});

function connectTeam(Team $team, array $attributes = []): GoogleOAuthSettings
{
    return GoogleOAuthSettings::query()->create([
        'team_id' => $team->id,
        'is_connected' => true,
        'access_token' => 'at',
        'refresh_token' => 'rt',
        'token_expires_at' => now()->addHour(),
        ...$attributes,
    ]);
}

function fakeGoogleLists(): void
{
    Http::fake([
        'analyticsadmin.googleapis.com/*' => Http::response(['accountSummaries' => [[
            'account' => 'accounts/1',
            'displayName' => 'Anest',
            'propertySummaries' => [['property' => 'properties/999', 'displayName' => 'anest.hu']],
        ]]]),
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:anest.hu']]]),
        'googleads.googleapis.com/*' => Http::response(['resourceNames' => []]),
    ]);
}

it('shows the connect prompt when the tenant is not connected', function (): void {
    Livewire::test(GoogleConnect::class)->assertSee(__('Not connected'));
});

it('persists a ga4 property the account can see', function (): void {
    fakeGoogleLists();
    $settings = connectTeam($this->team);

    Livewire::test(GoogleConnect::class)->set('selectedGa4PropertyId', 'properties/999');

    expect($settings->fresh()->selected_ga4_property_id)->toBe('properties/999');
});

it('ignores a ga4 property the account cannot see', function (): void {
    fakeGoogleLists();
    $settings = connectTeam($this->team, ['selected_ga4_property_id' => 'properties/999']);

    Livewire::test(GoogleConnect::class)
        ->set('selectedGa4PropertyId', 'properties/123')
        ->assertSet('selectedGa4PropertyId', 'properties/999');

    expect($settings->fresh()->selected_ga4_property_id)->toBe('properties/999');
});

it('ignores a search console site the account cannot see', function (): void {
    fakeGoogleLists();
    $settings = connectTeam($this->team);

    Livewire::test(GoogleConnect::class)->set('selectedSearchConsoleSiteUrl', 'https://evil.test/');

    expect($settings->fresh()->selected_search_console_site_url)->toBeNull();
});

it('clears a selection with an empty value', function (): void {
    fakeGoogleLists();
    $settings = connectTeam($this->team, ['selected_search_console_site_url' => 'sc-domain:anest.hu']);

    Livewire::test(GoogleConnect::class)->set('selectedSearchConsoleSiteUrl', '');

    expect($settings->fresh()->selected_search_console_site_url)->toBeNull();
});

it('hides the ads selector and skips the ads api when ads is not enabled', function (): void {
    fakeGoogleLists();
    config()->set('google-connect.resources', ['search_console', 'ga4']);
    connectTeam($this->team);

    Livewire::test(GoogleConnect::class)
        ->assertDontSee(__('Google Ads Account'))
        ->assertSee(__('GA4 Property'));

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'googleads.googleapis.com'));
});

it('refuses to persist a resource that is not enabled', function (): void {
    fakeGoogleLists();
    config()->set('google-connect.resources', ['ga4']);
    $settings = connectTeam($this->team);

    Livewire::test(GoogleConnect::class)->set('selectedSearchConsoleSiteUrl', 'sc-domain:anest.hu');

    expect($settings->fresh()->selected_search_console_site_url)->toBeNull();
});

it('refuses to disconnect when the user cannot access the tenant', function (): void {
    fakeGoogleLists();
    $settings = connectTeam($this->team);
    $this->actingAs(new User(['id' => 2, 'team_ids' => []]));

    Livewire::test(GoogleConnect::class)->call('disconnect')->assertForbidden();

    expect($settings->fresh()->is_connected)->toBeTrue();
});
