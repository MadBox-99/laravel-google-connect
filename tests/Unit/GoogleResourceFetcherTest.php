<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Services\GoogleResourceFetcher;
use MadBox\GoogleConnect\Tests\Fixtures\Team;

beforeEach(function (): void {
    $this->team = Team::query()->create(['name' => 'Acme']);
    $this->settings = GoogleOAuthSettings::query()->create([
        'team_id' => $this->team->id,
        'access_token' => 'at',
        'refresh_token' => 'rt',
        'token_expires_at' => now()->addHour(),
        'is_connected' => true,
    ]);
});

it('lists search console sites from the api response', function (): void {
    Http::fake([
        'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'https://a.test/', 'permissionLevel' => 'siteOwner'],
                ['siteUrl' => 'sc-domain:b.test', 'permissionLevel' => 'siteFullUser'],
            ],
        ]),
    ]);

    $sites = app(GoogleResourceFetcher::class)->listSearchConsoleSites($this->settings);

    expect($sites)->toHaveCount(2)
        ->and($sites[0])->toBe(['id' => 'https://a.test/', 'name' => 'https://a.test/'])
        ->and($sites[1])->toBe(['id' => 'sc-domain:b.test', 'name' => 'sc-domain:b.test']);
});

it('lists google ads accounts, expanding manager accounts into their serving clients', function (): void {
    Http::fake([
        'https://googleads.googleapis.com/v23/customers:listAccessibleCustomers' => Http::response([
            'resourceNames' => ['customers/111', 'customers/222'],
        ]),
        'https://googleads.googleapis.com/v23/customers/111/googleAds:search' => Http::response([
            'results' => [
                ['customer' => ['id' => '111', 'descriptiveName' => 'Direct Account', 'manager' => false, 'currencyCode' => 'USD']],
            ],
        ]),
        'https://googleads.googleapis.com/v23/customers/222/googleAds:search' => function ($request) {
            $body = json_decode((string) $request->body(), true);

            if (str_contains((string) $body['query'], 'FROM customer LIMIT 1')) {
                return Http::response([
                    'results' => [
                        ['customer' => ['id' => '222', 'descriptiveName' => 'My MCC', 'manager' => true, 'currencyCode' => 'USD']],
                    ],
                ]);
            }

            return Http::response([
                'results' => [
                    ['customerClient' => ['id' => '333', 'descriptiveName' => 'Client Under MCC', 'manager' => false, 'status' => 'ENABLED']],
                    ['customerClient' => ['id' => '444', 'descriptiveName' => 'Disabled Client', 'manager' => false, 'status' => 'PAUSED']],
                ],
            ]);
        },
    ]);

    config()->set('google-connect.developer_token', 'dev-token');

    $accounts = app(GoogleResourceFetcher::class)->listGoogleAdsAccounts($this->settings);

    expect($accounts)->toBe([
        ['id' => '111', 'name' => 'Direct Account (111)', 'manager_id' => null],
        ['id' => '333', 'name' => 'Client Under MCC (333)', 'manager_id' => '222'],
    ]);
});

it('lists analytics properties from the api response', function (): void {
    Http::fake([
        'https://analyticsadmin.googleapis.com/v1beta/accountSummaries*' => Http::response([
            'accountSummaries' => [
                [
                    'account' => 'accounts/1',
                    'displayName' => 'Acme Account',
                    'propertySummaries' => [
                        ['property' => 'properties/1', 'displayName' => 'Acme Property'],
                    ],
                ],
            ],
        ]),
    ]);

    $properties = app(GoogleResourceFetcher::class)->listAnalyticsProperties($this->settings);

    expect($properties)->toBe([
        [
            'account_id' => 'accounts/1',
            'account_name' => 'Acme Account',
            'property_id' => 'properties/1',
            'property_name' => 'Acme Property',
        ],
    ]);
});

it('caches results for 10 minutes so a second call does not hit the api again', function (): void {
    Http::fake([
        'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'https://first.test/', 'permissionLevel' => 'siteOwner'],
            ],
        ]),
    ]);

    $fetcher = app(GoogleResourceFetcher::class);

    $first = $fetcher->listSearchConsoleSites($this->settings);

    Http::fake([
        'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'https://second.test/', 'permissionLevel' => 'siteOwner'],
            ],
        ]),
    ]);

    $second = $fetcher->listSearchConsoleSites($this->settings);

    expect($first)->toBe($second)
        ->and($second[0]['id'])->toBe('https://first.test/');
});
