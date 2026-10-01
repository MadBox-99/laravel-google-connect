<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The OAuth `state` is a random nonce remembered in the session together with
 * the team that started the consent. The callback learns the team from here,
 * never from the request, so a forged or replayed callback cannot attach a
 * Google account to someone else's team.
 */
final class OAuthState
{
    private const string SESSION_KEY = 'google-connect.oauth_state';

    public function issue(Model $team): string
    {
        $nonce = Str::random(40);

        session()->put(self::SESSION_KEY, [
            'nonce' => $nonce,
            'team_id' => (int) $team->getKey(),
        ]);

        return $nonce;
    }

    /**
     * Single use: the pending state is removed whether or not it matches.
     */
    public function consume(?string $state): ?int
    {
        $pending = session()->pull(self::SESSION_KEY);

        if (! is_array($pending) || ! is_string($state) || $state === '') {
            return null;
        }

        if (! hash_equals((string) ($pending['nonce'] ?? ''), $state)) {
            return null;
        }

        return (int) $pending['team_id'];
    }
}
