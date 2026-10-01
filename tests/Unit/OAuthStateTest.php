<?php

declare(strict_types=1);

use MadBox\GoogleConnect\Support\OAuthState;
use MadBox\GoogleConnect\Tests\Fixtures\Team;

it('issues a 40 character nonce and resolves it back to the team once', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    $state = app(OAuthState::class);

    $nonce = $state->issue($team);

    expect($nonce)->toHaveLength(40)
        ->and($state->consume($nonce))->toBe($team->id)
        ->and($state->consume($nonce))->toBeNull();
});

it('rejects a wrong nonce and still clears the pending state', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    $state = app(OAuthState::class);
    $nonce = $state->issue($team);

    expect($state->consume('wrong'))->toBeNull()
        ->and($state->consume($nonce))->toBeNull();
});

it('rejects a missing state and the raw team id', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    $state = app(OAuthState::class);

    expect($state->consume(null))->toBeNull();

    $state->issue($team);

    expect($state->consume((string) $team->id))->toBeNull();
});
