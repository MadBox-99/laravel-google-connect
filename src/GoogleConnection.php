<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect;

use Illuminate\Database\Eloquent\Model;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;

final class GoogleConnection
{
    /**
     * Return the ready-to-use unified Google connection for a team, or null
     * when the team has not connected via the unified OAuth flow. Callers
     * fall back to their legacy per-API mechanism on null.
     */
    public static function for(Model $team): ?ConnectedGoogle
    {
        /** @var GoogleOAuthSettings|null $settings */
        $settings = $team->getKey() === null
            ? null
            : GoogleOAuthSettings::query()->where('team_id', $team->getKey())->first();

        if (! $settings instanceof GoogleOAuthSettings) {
            return null;
        }

        if (! $settings->is_connected || $settings->refresh_token === null) {
            return null;
        }

        return new ConnectedGoogle($settings);
    }
}
