<?php

declare(strict_types=1);
use App\Models\Team;

return [
    'tenant_model' => Team::class,
    'tenant_table' => 'teams',

    'client_id' => env('GOOGLE_CLIENT_ID', env('GOOGLE_ADS_PROXY_CLIENT_ID', env('GOOGLE_ADS_CLIENT_ID'))),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', env('GOOGLE_ADS_PROXY_CLIENT_SECRET', env('GOOGLE_ADS_CLIENT_SECRET'))),
    'proxy_base_url' => env('GOOGLE_PROXY_BASE_URL', env('GOOGLE_ADS_PROXY_BASE_URL', 'https://cegem360.eu')),
    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),

    'routes' => [
        'prefix' => 'google/auth',
        'middleware' => ['web', 'auth'],
    ],

    'settings_route' => 'filament.admin.pages.settings',
    'dashboard_route' => 'filament.admin.pages.dashboard',

    'scopes' => [
        'https://www.googleapis.com/auth/adwords',
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
        'openid',
        'email',
        'profile',
    ],
];
