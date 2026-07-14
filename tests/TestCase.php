<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Tests;

use MadBox\GoogleConnect\GoogleConnectServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GoogleConnectServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('google-connect.tenant_model', \MadBox\GoogleConnect\Tests\Fixtures\Team::class);
        $app['config']->set('google-connect.client_id', 'test-client-id');
        $app['config']->set('google-connect.client_secret', 'test-client-secret');
        $app['config']->set('google-connect.proxy_base_url', 'https://proxy.test');
        $app['config']->set('google-connect.developer_token', 'test-dev-token');
        $app['config']->set('app.url', 'https://tenant.test');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
