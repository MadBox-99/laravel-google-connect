<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect;

use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClient;
use Google\Client;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Services\GoogleAdsClientFactory;
use MadBox\GoogleConnect\Services\GoogleClientFactory;

final readonly class ConnectedGoogle
{
    public function __construct(
        public GoogleOAuthSettings $settings,
    ) {}

    /**
     * @param  array<string>|string  $scopes
     */
    public function googleClient(array|string $scopes): Client
    {
        return GoogleClientFactory::makeFromUnified($this->settings, $scopes);
    }

    public function adsClient(): GoogleAdsClient
    {
        return GoogleAdsClientFactory::makeForUnified($this->settings);
    }

    public function adsCustomerId(): ?string
    {
        return $this->settings->selected_ads_customer_id;
    }

    public function searchConsoleSiteUrl(): ?string
    {
        return $this->settings->selected_search_console_site_url;
    }

    public function ga4PropertyId(): ?string
    {
        return $this->settings->selected_ga4_property_id;
    }
}
