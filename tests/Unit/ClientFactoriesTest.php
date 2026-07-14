<?php

declare(strict_types=1);

use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Services\GoogleAdsClientFactory;

it('refuses to build an ads client when no account is selected', function (): void {
    $settings = new GoogleOAuthSettings([
        'is_connected' => true,
        'refresh_token' => 'rt',
        'selected_ads_customer_id' => null,
    ]);

    GoogleAdsClientFactory::makeForUnified($settings);
})->throws(RuntimeException::class, 'account has not been selected');

it('refuses to build an ads client when not connected', function (): void {
    $settings = new GoogleOAuthSettings(['is_connected' => false, 'refresh_token' => null]);

    GoogleAdsClientFactory::makeForUnified($settings);
})->throws(RuntimeException::class, 'not connected');
