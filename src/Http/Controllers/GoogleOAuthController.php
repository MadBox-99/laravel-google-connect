<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MadBox\GoogleConnect\Services\GoogleOAuthService;
use MadBox\GoogleConnect\Support\OAuthState;
use MadBox\GoogleConnect\Support\TenantAuthorizer;

final class GoogleOAuthController
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
        private readonly OAuthState $state,
        private readonly TenantAuthorizer $authorizer,
    ) {}

    /**
     * Redirect to the unified Google consent flow.
     */
    public function redirect(Request $request, Model $team): RedirectResponse
    {
        $this->authorize($request, $team);

        try {
            $authUrl = $this->oauthService->getAuthorizationUrl($team, $this->state->issue($team));

            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('Google OAuth redirect failed', ['error' => $e->getMessage(), 'team_id' => $team->getKey()]);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('error', __('Failed to initiate Google connection: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Handle the OAuth callback forwarded back from cegem360.eu. The team is
     * whatever this session started the flow for — never the request's say.
     */
    public function callback(Request $request): RedirectResponse
    {
        $teamId = $this->state->consume($request->string('state')->toString());

        /** @var class-string<Model> $model */
        $model = config('google-connect.tenant_model');
        $team = $teamId === null ? null : $model::query()->find($teamId);

        if (! $team instanceof Model) {
            return to_route(config('google-connect.dashboard_route'))
                ->with('error', __('Invalid OAuth callback parameters.'));
        }

        $this->authorize($request, $team);

        if ($request->has('error')) {
            Log::error('Google OAuth callback error', ['error' => $request->get('error'), 'team_id' => $teamId]);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('error', __('Google connection was denied or failed.'));
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
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
    public function disconnect(Request $request, Model $team): RedirectResponse
    {
        $this->authorize($request, $team);

        try {
            $this->oauthService->disconnect($team);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('success', __('Google account disconnected successfully.'));
        } catch (Exception $e) {
            Log::error('Google OAuth disconnect failed', ['error' => $e->getMessage(), 'team_id' => $team->getKey()]);

            return to_route(config('google-connect.settings_route'), ['tenant' => $team])
                ->with('error', __('Failed to disconnect Google account: :message', ['message' => $e->getMessage()]));
        }
    }

    private function authorize(Request $request, Model $team): void
    {
        abort_unless($this->authorizer->allows($request->user(), $team), 403);
    }
}
