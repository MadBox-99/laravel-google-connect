<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class AllowAcme
{
    public function __invoke(Authenticatable $user, Model $team): bool
    {
        return $team->getAttribute('name') === 'Acme';
    }
}
