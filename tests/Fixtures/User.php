<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Never persisted: tests build it with the team ids it may access and hand
 * it to actingAs(), which is all the authorization checks look at.
 */
final class User extends Authenticatable
{
    protected $guarded = [];

    protected $casts = ['team_ids' => 'array'];

    public function canAccessTenant(Model $tenant): bool
    {
        return in_array($tenant->getKey(), $this->team_ids ?? [], true);
    }
}
