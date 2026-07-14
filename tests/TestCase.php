<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Tests;

use Filament\FilamentServiceProvider;
use Illuminate\Encryption\Encrypter;
use Livewire\LivewireServiceProvider;
use MadBox\GoogleConnect\GoogleConnectServiceProvider;
use MadBox\GoogleConnect\Tests\Fixtures\Team;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            GoogleConnectServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey($app['config']->get('app.cipher', 'AES-256-CBC'))
        ));
        $app['config']->set('google-connect.tenant_model', Team::class);
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
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
