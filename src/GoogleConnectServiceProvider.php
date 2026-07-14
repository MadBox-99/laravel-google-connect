<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GoogleConnectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/google-connect.php', 'google-connect');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'google-connect');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $tenantModel */
        $tenantModel = config('google-connect.tenant_model');
        Route::model('team', $tenantModel);

        $this->publishes([
            __DIR__ . '/../config/google-connect.php' => config_path('google-connect.php'),
        ], 'google-connect-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'google-connect-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/google-connect'),
        ], 'google-connect-translations');
    }
}
