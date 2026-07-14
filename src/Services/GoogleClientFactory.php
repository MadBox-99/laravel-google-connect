<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Services;

use Google\Client;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;

final class GoogleClientFactory
{
    /**
     * Build a Google API client authenticated by the user's unified OAuth
     * tokens. The token is refreshed first if needed so the caller always
     * gets a usable access token.
     *
     * @param  array<string>|string  $scopes
     */
    public static function makeFromUnified(GoogleOAuthSettings $settings, array|string $scopes): Client
    {
        $oauthService = app(GoogleOAuthService::class);
        $settings = $oauthService->refreshTokenIfNeeded($settings);

        $client = new Client;
        $client->setClientId((string) config('google-connect.client_id'));
        $client->setClientSecret((string) config('google-connect.client_secret'));
        $client->setScopes($scopes);
        $client->setAccessToken([
            'access_token' => $settings->access_token,
            'refresh_token' => $settings->refresh_token,
            'expires_in' => max(0, (int) now()->diffInSeconds($settings->token_expires_at, absolute: false)),
            'created' => $settings->token_expires_at?->subSeconds(3600)->getTimestamp() ?? now()->getTimestamp(),
        ]);

        return $client;
    }
}
