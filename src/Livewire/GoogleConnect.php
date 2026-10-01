<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Services\GoogleOAuthService;
use MadBox\GoogleConnect\Services\GoogleResourceFetcher;
use MadBox\GoogleConnect\Support\TenantAuthorizer;

final class GoogleConnect extends Component
{
    public ?string $selectedAdsCustomerId = null;

    public ?string $selectedSearchConsoleSiteUrl = null;

    public ?string $selectedGa4PropertyId = null;

    public function mount(): void
    {
        $settings = $this->settings();
        $this->selectedAdsCustomerId = $settings?->selected_ads_customer_id;
        $this->selectedSearchConsoleSiteUrl = $settings?->selected_search_console_site_url;
        $this->selectedGa4PropertyId = $settings?->selected_ga4_property_id;
    }

    public function settings(): ?GoogleOAuthSettings
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Model ? $tenant->googleOAuthSettings : null;
    }

    public function updatedSelectedAdsCustomerId(?string $value): void
    {
        $value = $this->acceptOrRevert('ads', 'selectedAdsCustomerId', 'selected_ads_customer_id', $value);

        if ($value === false) {
            return;
        }

        $this->persist('selected_ads_customer_id', $value);
        $this->persist('selected_ads_manager_customer_id', $value === null ? null
            : app(GoogleResourceFetcher::class)->findManagerIdForAccount($this->settings(), $value));
    }

    public function updatedSelectedSearchConsoleSiteUrl(?string $value): void
    {
        $value = $this->acceptOrRevert('search_console', 'selectedSearchConsoleSiteUrl', 'selected_search_console_site_url', $value);

        if ($value !== false) {
            $this->persist('selected_search_console_site_url', $value);
        }
    }

    public function updatedSelectedGa4PropertyId(?string $value): void
    {
        $value = $this->acceptOrRevert('ga4', 'selectedGa4PropertyId', 'selected_ga4_property_id', $value);

        if ($value !== false) {
            $this->persist('selected_ga4_property_id', $value);
        }
    }

    public function disconnect(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Model) {
            return;
        }

        abort_unless(app(TenantAuthorizer::class)->allows(Auth::user(), $tenant), 403);

        app(GoogleOAuthService::class)->disconnect($tenant);

        $this->redirectRoute(config('google-connect.settings_route'), ['tenant' => $tenant]);
    }

    public function showsResource(string $resource): bool
    {
        return in_array($resource, (array) config('google-connect.resources'), true);
    }

    /**
     * @return array{ads: array<int, array{id: string, name: string, manager_id: ?string}>, sites: array<int, array{id: string, name: string}>, properties: array<int, array{account_id: string, account_name: string, property_id: string, property_name: string}>}
     */
    public function resourceOptions(): array
    {
        $settings = $this->settings();
        $options = ['ads' => [], 'sites' => [], 'properties' => []];

        if (! $settings instanceof GoogleOAuthSettings || ! $settings->is_connected) {
            return $options;
        }

        $fetcher = app(GoogleResourceFetcher::class);

        if ($this->showsResource('ads')) {
            $options['ads'] = $fetcher->listGoogleAdsAccounts($settings);
        }

        if ($this->showsResource('search_console')) {
            $options['sites'] = $fetcher->listSearchConsoleSites($settings);
        }

        if ($this->showsResource('ga4')) {
            $options['properties'] = $fetcher->listAnalyticsProperties($settings);
        }

        return $options;
    }

    public function render(): View
    {
        return view('google-connect::livewire.google-connect', [
            'settings' => $this->settings(),
        ]);
    }

    private function persist(string $column, ?string $value): void
    {
        $this->settings()?->update([$column => $value]);
    }

    /**
     * An empty value clears the selection. Anything else must be enabled and
     * appear in the connected account's own list. Otherwise the property
     * snaps back to the stored value and false tells the caller to skip
     * persisting.
     */
    private function acceptOrRevert(string $resource, string $property, string $column, ?string $value): string|null|false
    {
        if ($value === null || $value === '') {
            return $this->showsResource($resource) ? null : false;
        }

        $allowed = match ($resource) {
            'ads' => array_column($this->resourceOptions()['ads'], 'id'),
            'search_console' => array_column($this->resourceOptions()['sites'], 'id'),
            'ga4' => array_column($this->resourceOptions()['properties'], 'property_id'),
        };

        if ($this->showsResource($resource) && in_array($value, $allowed, true)) {
            return $value;
        }

        $this->{$property} = $this->settings()?->{$column};

        return false;
    }
}
