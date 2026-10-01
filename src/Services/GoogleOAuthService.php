<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Services;

use Exception;
use Google\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use RuntimeException;

/**
 * Unified Google OAuth flow that bundles Ads, Search Console, Analytics,
 * and the openid/email/profile scopes in a single consent. Tenants connect
 * once via the cegem360.eu proxy and then resolve account/property
 * selection separately through GoogleResourceFetcher.
 */
final class GoogleOAuthService
{
    /**
     * $state must come from OAuthState::issue() — it is what ties the proxy's
     * callback back to this browser session and team.
     */
    public function getAuthorizationUrl(Model $team, string $state): string
    {
        $base = mb_rtrim((string) config('google-connect.proxy_base_url'), '/');
        $returnPath = parse_url(route('google.oauth.callback'), PHP_URL_PATH) ?: '/google/auth/callback';

        return $base.'/oauth/google/start?'.http_build_query([
            'tenant' => mb_rtrim((string) config('app.url'), '/'),
            'return_path' => $returnPath,
            'state' => $state,
        ]);
    }

    public function handleCallback(string $code, int $teamId): GoogleOAuthSettings
    {
        $client = $this->createOAuthClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            Log::error('Google OAuth error', $token);
            throw new RuntimeException('OAuth error: '.($token['error_description'] ?? $token['error']));
        }

        $userInfo = $this->fetchUserInfo($token['access_token']);

        $settings = GoogleOAuthSettings::query()->updateOrCreate(
            ['team_id' => $teamId],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($token['expires_in']),
                'is_connected' => true,
                'connected_email' => $userInfo['email'] ?? null,
                'connected_name' => $userInfo['name'] ?? null,
            ],
        );

        // A reconnect may be a different Google account; its resource lists
        // must not be served from the previous account's cache.
        app(GoogleResourceFetcher::class)->forgetCachedResources($settings);

        return $settings;
    }

    public function refreshTokenIfNeeded(GoogleOAuthSettings $settings): GoogleOAuthSettings
    {
        if (! $settings->isTokenExpired()) {
            return $settings;
        }

        if (! $settings->refresh_token) {
            throw new RuntimeException('No refresh token available. Please reconnect your Google account.');
        }

        $client = $this->createOAuthClient();
        $client->fetchAccessTokenWithRefreshToken($settings->refresh_token);
        $token = $client->getAccessToken();

        if (isset($token['error'])) {
            Log::error('Google OAuth token refresh error', $token);
            $settings->update(['is_connected' => false]);
            throw new RuntimeException('Token refresh failed: '.($token['error_description'] ?? $token['error']));
        }

        $settings->update([
            'access_token' => $token['access_token'],
            'token_expires_at' => now()->addSeconds($token['expires_in']),
        ]);

        return $settings->fresh();
    }

    public function disconnect(Model $team): void
    {
        $settings = $team->googleOAuthSettings;

        if ($settings === null) {
            return;
        }

        if ($settings->access_token) {
            try {
                $client = $this->createOAuthClient();
                $client->revokeToken($settings->access_token);
            } catch (Exception $e) {
                Log::warning('Failed to revoke Google OAuth token', ['error' => $e->getMessage()]);
            }
        }

        app(GoogleResourceFetcher::class)->forgetCachedResources($settings);

        $settings->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'is_connected' => false,
            'connected_email' => null,
            'connected_name' => null,
            'selected_ads_customer_id' => null,
            'selected_ads_manager_customer_id' => null,
            'selected_search_console_site_url' => null,
            'selected_ga4_account_id' => null,
            'selected_ga4_property_id' => null,
        ]);
    }

    public function hasCredentials(): bool
    {
        return filled(config('google-connect.client_id'))
            && filled(config('google-connect.client_secret'))
            && filled(config('google-connect.proxy_base_url'));
    }

    public function createOAuthClient(): Client
    {
        if (! $this->hasCredentials()) {
            throw new RuntimeException('Unified Google OAuth credentials are not configured. Please set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_PROXY_BASE_URL in your .env file.');
        }

        $client = app(Client::class);
        $client->setClientId((string) config('google-connect.client_id'));
        $client->setClientSecret((string) config('google-connect.client_secret'));
        $client->setRedirectUri($this->getProxyCallbackUrl());
        $client->setScopes((array) config('google-connect.scopes'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    private function getProxyCallbackUrl(): string
    {
        return mb_rtrim((string) config('google-connect.proxy_base_url'), '/').'/oauth/google/callback';
    }

    /**
     * Fetch the userinfo (email + name) for the connected account so the UI
     * can show "Connected as user@example.com" without an extra round-trip.
     *
     * @return array{email?: string, name?: string}
     */
    private function fetchUserInfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->get('https://openidconnect.googleapis.com/v1/userinfo');

            $data = $response->json();

            return is_array($data) ? $data : [];
        } catch (Exception $e) {
            Log::warning('Failed to fetch Google user info', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
