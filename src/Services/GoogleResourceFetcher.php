<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use RuntimeException;
use Throwable;

/**
 * Site Kit-style resource selector data source. After the user connects
 * via the unified Google OAuth flow, the UI shows three dropdowns
 * (Ads customer, Search Console property, GA4 account/property). This
 * service queries the relevant Google APIs to populate them.
 *
 * Results are cached per-team for 10 minutes to keep the UI snappy and
 * avoid hammering the APIs as the user clicks around the selectors.
 */
final readonly class GoogleResourceFetcher
{
    private const int CACHE_TTL_SECONDS = 600;

    private const string ADS_API_VERSION = 'v23';

    public function __construct(
        private GoogleOAuthService $oauthService,
    ) {}

    /**
     * @return list<array{id: string, name: string, manager_id: ?string}>
     */
    public function listGoogleAdsAccounts(GoogleOAuthSettings $settings): array
    {
        return $this->cached($settings, 'ads_accounts', function () use ($settings): array {
            $settings = $this->oauthService->refreshTokenIfNeeded($settings);

            $devToken = (string) config('google-connect.developer_token');

            if ($devToken === '') {
                throw new RuntimeException('Google Ads developer token is not configured.');
            }

            $response = Http::withToken($settings->access_token)
                ->withHeaders(['developer-token' => $devToken])
                ->get(sprintf('https://googleads.googleapis.com/%s/customers:listAccessibleCustomers', self::ADS_API_VERSION));

            if (! $response->successful()) {
                Log::warning('listAccessibleCustomers failed', ['status' => $response->status(), 'body' => $response->body()]);

                return [];
            }

            $resourceNames = (array) ($response->json('resourceNames') ?? []);

            /** @var array<string, array{id: string, name: string, manager_id: ?string}> $accounts */
            $accounts = [];

            foreach ($resourceNames as $resourceName) {
                $id = str_replace('customers/', '', (string) $resourceName);

                if ($id === '') {
                    continue;
                }

                $details = $this->fetchGoogleAdsCustomerDetails($settings->access_token, $devToken, $id);

                if ($details === null) {
                    continue;
                }

                // Manager (MCC) accounts cannot serve metric queries
                // ("REQUESTED_METRICS_FOR_MANAGER"), so we never offer them
                // directly. Instead we expand them into their serving client
                // accounts, which is the only way users whose sole accessible
                // customer is an MCC can pick anything at all.
                if ($details['manager']) {
                    foreach ($this->listClientAccountsUnderManager($settings->access_token, $devToken, $id) as $client) {
                        $accounts[$client['id']] = $client;
                    }

                    continue;
                }

                $accounts[$id] = [
                    'id' => $id,
                    'name' => $details['name'] !== '' ? sprintf('%s (%s)', $details['name'], $id) : $id,
                    'manager_id' => null,
                ];
            }

            return array_values($accounts);
        });
    }

    /**
     * Returns the manager customer id that should be used as
     * `login-customer-id` when querying the given client account, or null
     * when the account is accessible directly. Backs the Settings selector so
     * the right MCC is stored alongside the chosen Ads account.
     */
    public function findManagerIdForAccount(GoogleOAuthSettings $settings, string $accountId): ?string
    {
        foreach ($this->listGoogleAdsAccounts($settings) as $account) {
            if ($account['id'] === $accountId) {
                return $account['manager_id'];
            }
        }

        return null;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listSearchConsoleSites(GoogleOAuthSettings $settings): array
    {
        return $this->cached($settings, 'search_console_sites', function () use ($settings): array {
            $settings = $this->oauthService->refreshTokenIfNeeded($settings);

            $response = Http::withToken($settings->access_token)
                ->get('https://www.googleapis.com/webmasters/v3/sites');

            if (! $response->successful()) {
                Log::warning('Search Console sites:list failed', ['status' => $response->status(), 'body' => $response->body()]);

                return [];
            }

            $sites = (array) ($response->json('siteEntry') ?? []);

            return array_values(array_map(fn (array $site): array => [
                'id' => (string) ($site['siteUrl'] ?? ''),
                'name' => (string) ($site['siteUrl'] ?? ''),
            ], $sites));
        });
    }

    /**
     * @return list<array{account_id: string, account_name: string, property_id: string, property_name: string}>
     */
    public function listAnalyticsProperties(GoogleOAuthSettings $settings): array
    {
        return $this->cached($settings, 'ga4_properties', function () use ($settings): array {
            $settings = $this->oauthService->refreshTokenIfNeeded($settings);

            $rows = [];
            $pageToken = null;

            do {
                $query = ['pageSize' => 200];

                if ($pageToken !== null) {
                    $query['pageToken'] = $pageToken;
                }

                $response = Http::withToken($settings->access_token)
                    ->get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', $query);

                if (! $response->successful()) {
                    Log::warning('GA4 accountSummaries failed', ['status' => $response->status(), 'body' => $response->body()]);

                    return $rows;
                }

                foreach ((array) ($response->json('accountSummaries') ?? []) as $summary) {
                    $accountId = (string) ($summary['account'] ?? '');
                    $accountName = (string) ($summary['displayName'] ?? $accountId);

                    foreach ((array) ($summary['propertySummaries'] ?? []) as $property) {
                        $rows[] = [
                            'account_id' => $accountId,
                            'account_name' => $accountName,
                            'property_id' => (string) ($property['property'] ?? ''),
                            'property_name' => (string) ($property['displayName'] ?? ''),
                        ];
                    }
                }

                $pageToken = $response->json('nextPageToken');
            } while (filled($pageToken));

            return $rows;
        });
    }

    /**
     * Manually clear all cached resource lists for a team (used after
     * disconnect/reconnect so stale account lists do not linger).
     */
    public function forgetCachedResources(GoogleOAuthSettings $settings): void
    {
        foreach (['ads_accounts', 'search_console_sites', 'ga4_properties'] as $key) {
            Cache::forget($this->cacheKey($settings, $key));
        }
    }

    /**
     * Lists the serving (non-manager) client accounts beneath an MCC, tagging
     * each with the manager id so it can be used as `login-customer-id` later.
     *
     * @return list<array{id: string, name: string, manager_id: string}>
     */
    private function listClientAccountsUnderManager(string $accessToken, string $devToken, string $managerId): array
    {
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'developer-token' => $devToken,
                'login-customer-id' => $managerId,
            ])
            ->post(
                sprintf('https://googleads.googleapis.com/%s/customers/%s/googleAds:search', self::ADS_API_VERSION, $managerId),
                ['query' => 'SELECT customer_client.id, customer_client.descriptive_name, customer_client.manager, customer_client.status FROM customer_client WHERE customer_client.level > 0'],
            );

        if (! $response->successful()) {
            Log::warning('Google Ads customer_client lookup failed', [
                'manager_id' => $managerId,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 400),
            ]);

            return [];
        }

        /** @var array<string, array{id: string, name: string, manager_id: string}> $clients */
        $clients = [];

        foreach ((array) ($response->json('results') ?? []) as $row) {
            $client = $row['customerClient'] ?? null;

            if (! is_array($client)) {
                continue;
            }
            // Skip nested sub-managers and non-active accounts; only serving,
            // enabled clients can be imported.
            if ((bool) ($client['manager'] ?? false)) {
                continue;
            }
            if (($client['status'] ?? '') !== 'ENABLED') {
                continue;
            }

            $clientId = (string) ($client['id'] ?? '');

            if ($clientId === '') {
                continue;
            }

            $name = (string) ($client['descriptiveName'] ?? '');

            $clients[$clientId] = [
                'id' => $clientId,
                'name' => $name !== '' ? sprintf('%s (%s)', $name, $clientId) : $clientId,
                'manager_id' => $managerId,
            ];
        }

        return array_values($clients);
    }

    /**
     * @return array{name: string, manager: bool, currency: string}|null
     */
    private function fetchGoogleAdsCustomerDetails(string $accessToken, string $devToken, string $customerId): ?array
    {
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'developer-token' => $devToken,
                'login-customer-id' => $customerId,
            ])
            ->post(
                sprintf('https://googleads.googleapis.com/%s/customers/%s/googleAds:search', self::ADS_API_VERSION, $customerId),
                ['query' => 'SELECT customer.id, customer.descriptive_name, customer.manager, customer.currency_code FROM customer LIMIT 1'],
            );

        if (! $response->successful()) {
            Log::warning('Google Ads customer details lookup failed', [
                'customer_id' => $customerId,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 400),
            ]);

            return null;
        }

        $row = $response->json('results.0.customer');

        if (! is_array($row)) {
            return null;
        }

        return [
            'name' => (string) ($row['descriptiveName'] ?? ''),
            'manager' => (bool) ($row['manager'] ?? false),
            'currency' => (string) ($row['currencyCode'] ?? ''),
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    private function cached(GoogleOAuthSettings $settings, string $resource, callable $resolver): mixed
    {
        try {
            return Cache::remember($this->cacheKey($settings, $resource), self::CACHE_TTL_SECONDS, $resolver);
        } catch (Throwable $e) {
            Log::warning('GoogleResourceFetcher cache resolver failed', [
                'resource' => $resource,
                'team_id' => $settings->team_id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function cacheKey(GoogleOAuthSettings $settings, string $resource): string
    {
        return "google_resources:{$resource}:team:{$settings->team_id}";
    }
}
