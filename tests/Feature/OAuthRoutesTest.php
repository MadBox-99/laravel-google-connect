<?php

declare(strict_types=1);

use MadBox\GoogleConnect\Tests\Fixtures\Team;

beforeEach(function (): void {
    Route::get('/settings-stub', fn () => 'settings')->name('settings.stub');
    Route::get('/dashboard-stub', fn () => 'dashboard')->name('dashboard.stub');
});

it('redirects to the proxy start url', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);

    $this->get(route('google.oauth.redirect', ['team' => $team->id]))
        ->assertRedirect();
});

it('sends an errored callback back to the settings route', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);

    $this->get(route('google.oauth.callback', ['state' => $team->id, 'error' => 'access_denied']))
        ->assertRedirect(route('settings.stub', ['tenant' => $team]));
});
