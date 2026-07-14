<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MadBox\GoogleConnect\Http\Controllers\GoogleOAuthController;

Route::middleware((array) config('google-connect.routes.middleware'))
    ->prefix((string) config('google-connect.routes.prefix'))
    ->group(function (): void {
        Route::get('/redirect/{team}', [GoogleOAuthController::class, 'redirect'])->name('google.oauth.redirect');
        Route::get('/callback', [GoogleOAuthController::class, 'callback'])->name('google.oauth.callback');
        Route::post('/disconnect/{team}', [GoogleOAuthController::class, 'disconnect'])->name('google.oauth.disconnect');
    });
