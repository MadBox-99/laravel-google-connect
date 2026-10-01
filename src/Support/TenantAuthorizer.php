<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may connect, disconnect or finish a Google consent for a team. Fails
 * closed: without a user, and without either a configured callable or the
 * Filament HasTenants method, the answer is no.
 */
final class TenantAuthorizer
{
    public function allows(?Authenticatable $user, Model $team): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        $callback = config('google-connect.authorize');

        // A class name keeps the config cacheable; a closure would make
        // `php artisan config:cache` refuse to run.
        if (is_string($callback) && class_exists($callback)) {
            $callback = app($callback);
        }

        if (is_callable($callback)) {
            return (bool) $callback($user, $team);
        }

        if (method_exists($user, 'canAccessTenant')) {
            return (bool) $user->canAccessTenant($team);
        }

        return false;
    }
}
