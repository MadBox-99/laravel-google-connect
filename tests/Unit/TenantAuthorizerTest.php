<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as PlainUser;
use MadBox\GoogleConnect\Support\TenantAuthorizer;
use MadBox\GoogleConnect\Tests\Fixtures\Team;
use MadBox\GoogleConnect\Tests\Fixtures\User;

it('denies when nobody is logged in', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);

    expect(app(TenantAuthorizer::class)->allows(null, $team))->toBeFalse();
});

it('uses canAccessTenant by default', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    $other = Team::query()->create(['name' => 'Other']);
    $user = new User(['id' => 1, 'team_ids' => [$team->id]]);

    expect(app(TenantAuthorizer::class)->allows($user, $team))->toBeTrue()
        ->and(app(TenantAuthorizer::class)->allows($user, $other))->toBeFalse();
});

it('denies a user that has no canAccessTenant method', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);

    expect(app(TenantAuthorizer::class)->allows(new PlainUser, $team))->toBeFalse();
});

it('lets a configured callable override the default', function (): void {
    $team = Team::query()->create(['name' => 'Acme']);
    config()->set('google-connect.authorize', fn ($user, $tenant): bool => $tenant->name === 'Acme');

    expect(app(TenantAuthorizer::class)->allows(new User(['id' => 1, 'team_ids' => []]), $team))->toBeTrue();
});
