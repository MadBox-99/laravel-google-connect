<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MadBox\GoogleConnect\Services\GoogleOAuthService;

final class GoogleOAuthController
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
    ) {}

    /**
     * Redirect to the unified Google consent flow.
     */
    public function redirect(Model $team): RedirectResponse
    {
        try {
            $authUrl = $this->oauthService->getAuthorizationUrl($team);

            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('Google OAuth redirect failed', ['error' => $e->getMessage(), 'team_id' => $team->id]);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('error', __('Failed to initiate Google connection: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Handle the OAuth callback forwarded back from cegem360.eu.
     */
    public function callback(Request $request): RedirectResponse
    {
        $teamId = (int) $request->get('state');
        $model = config('google-connect.tenant_model');
        $team = $model::query()->find($teamId);

        if ($request->has('error')) {
            Log::error('Google OAuth callback error', ['error' => $request->get('error'), 'team_id' => $teamId]);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('error', __('Google connection was denied or failed.'));
        }

        $code = $request->get('code');

        if (! $code || ! $team) {
            return to_route(config('google-connect.dashboard_route'))
                ->with('error', __('Invalid OAuth callback parameters.'));
        }

        try {
            $this->oauthService->handleCallback($code, $teamId);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('success', __('Google account connected successfully! Now select which accounts you want to use.'));
        } catch (Exception $e) {
            Log::error('Google OAuth callback failed', ['error' => $e->getMessage(), 'team_id' => $teamId]);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('error', __('Failed to connect Google account: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Disconnect the unified Google connection.
     */
    public function disconnect(Model $team): RedirectResponse
    {
        try {
            $this->oauthService->disconnect($team);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('success', __('Google account disconnected successfully.'));
        } catch (Exception $e) {
            Log::error('Google OAuth disconnect failed', ['error' => $e->getMessage(), 'team_id' => $team->id]);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('error', __('Failed to disconnect Google account: :message', ['message' => $e->getMessage()]));
        }
    }
}
