<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Per-team unified Google OAuth tokens plus the user-selected resources
 * (Ads customer, Search Console property, GA4 account/property) that the
 * import jobs pull from. Replaces the per-API GoogleAdsSettings table for
 * tenants that have migrated to the Site Kit-style consent flow.
 */
#[Fillable([
    'team_id',
    'connected_email',
    'connected_name',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'is_connected',
    'selected_ads_customer_id',
    'selected_ads_manager_customer_id',
    'selected_search_console_site_url',
    'selected_ga4_account_id',
    'selected_ga4_property_id',
    'last_sync_at',
])]
#[Hidden([
    'access_token',
    'refresh_token',
])]
#[Table(name: 'google_oauth_settings')]
final class GoogleOAuthSettings extends Model
{
    /**
     * @return BelongsTo<Model, $this>
     */
    public function team(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('google-connect.tenant_model');

        return $this->belongsTo($model, 'team_id');
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Returns null if the OAuth tokens are present and valid for API calls,
     * or a translated {title, body} pair describing the missing piece. The
     * resource selections (ads_customer_id etc.) are checked separately by
     * each consumer because they can be missing while the connection itself
     * is fine.
     *
     * @return array{title: string, body: string}|null
     */
    public function getOAuthIssue(): ?array
    {
        if (! $this->is_connected || $this->refresh_token === null) {
            return [
                'title' => __('Google account is not connected.'),
                'body' => __('Open Settings → Google and click "Connect Google" to authorize this app.'),
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_connected' => 'boolean',
            'token_expires_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
        ];
    }

    protected function hasAdsSelection(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->selected_ads_customer_id));
    }

    protected function hasSearchConsoleSelection(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->selected_search_console_site_url));
    }

    protected function hasGa4Selection(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->selected_ga4_property_id));
    }
}
