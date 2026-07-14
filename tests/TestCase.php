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
        $app['config']->set('app.key', 'base64:' . base64_encode(
            \Illuminate\Encryption\Encrypter::generateKey($app['config']->get('app.cipher', 'AES-256-CBC'))
        ));
        $app['config']->set('google-connect.tenant_model', \MadBox\GoogleConnect\Tests\Fixtures\Team::class);
        $app['config']->set('google-connect.client_id', 'test-client-id');
        $app['config']->set('google-connect.client_secret', 'test-client-secret');
        $app['config']->set('google-connect.proxy_base_url', 'https://proxy.test');
        $app['config']->set('google-connect.developer_token', 'test-dev-token');
        $app['config']->set('app.url', 'https://tenant.test');
        $app['config']->set('google-connect.routes.middleware', ['web']);
        $app['config']->set('google-connect.settings_route', 'settings.stub');
        $app['config']->set('google-connect.dashboard_route', 'dashboard.stub');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
