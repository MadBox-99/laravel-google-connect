<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use MadBox\GoogleConnect\Livewire\GoogleConnect;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Tests\Fixtures\Team;

it('shows the connect prompt when the tenant is not connected', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    Filament::shouldReceive('getTenant')->andReturn($team);

    Livewire::test(GoogleConnect::class)
        ->assertSee(__('Not connected'));
});

it('persists a selected ga4 property live', function (): void {
    Http::fake();

    $team = Team::query()->create(['name' => 'Acme']);
    GoogleOAuthSettings::query()->create([
        'team_id' => $team->id,
        'is_connected' => true,
        'access_token' => 'at',
        'refresh_token' => 'rt',
        'token_expires_at' => now()->addHour(),
    ]);
    Filament::shouldReceive('getTenant')->andReturn($team);

    Livewire::test(GoogleConnect::class)
        ->set('selectedGa4PropertyId', 'properties/999');

    expect($team->googleOAuthSettings->fresh()->selected_ga4_property_id)->toBe('properties/999');
});
