<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Services;

use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClientBuilder;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use RuntimeException;

final class GoogleAdsClientFactory
{
    /**
     * Build a Google Ads client from the unified Google OAuth tokens that
     * back the Site Kit-style selector flow.
     */
    public static function makeForUnified(GoogleOAuthSettings $settings): GoogleAdsClient
    {
        $oauthService = app(GoogleOAuthService::class);

        if (! $oauthService->hasCredentials()) {
            throw new RuntimeException('Unified Google OAuth credentials are not configured.');
        }

        if (! $settings->is_connected || $settings->refresh_token === null) {
            throw new RuntimeException('Google account is not connected. Open Settings → Connect Google.');
        }

        if ($settings->selected_ads_customer_id === null || $settings->selected_ads_customer_id === '') {
            throw new RuntimeException('Google Ads account has not been selected. Open Settings → Connect Google and pick an account.');
        }

        $devToken = (string) config('google-connect.developer_token');

        if ($devToken === '') {
            throw new RuntimeException('Google Ads developer token is not configured.');
        }

        $settings = $oauthService->refreshTokenIfNeeded($settings);

        $oAuth2Credential = (new OAuth2TokenBuilder)
            ->withClientId((string) config('google-connect.client_id'))
            ->withClientSecret((string) config('google-connect.client_secret'))
            ->withRefreshToken($settings->refresh_token)
            ->build();

        $builder = (new GoogleAdsClientBuilder)
            ->withDeveloperToken($devToken)
            ->withOAuth2Credential($oAuth2Credential);

        if ($settings->selected_ads_manager_customer_id !== null && $settings->selected_ads_manager_customer_id !== '') {
            $builder->withLoginCustomerId((int) self::normalizeCustomerId($settings->selected_ads_manager_customer_id));
        }

        return $builder->build();
    }

    /**
     * Normalize customer ID by removing dashes.
     */
    private static function normalizeCustomerId(?string $customerId): string
    {
        if ($customerId === null) {
            throw new RuntimeException('Customer ID is not set.');
        }

        return str_replace('-', '', $customerId);
    }
}
