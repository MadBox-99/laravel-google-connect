<?php

declare(strict_types=1);

namespace MadBox\GoogleConnect\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use MadBox\GoogleConnect\Models\GoogleOAuthSettings;
use MadBox\GoogleConnect\Services\GoogleOAuthService;
use MadBox\GoogleConnect\Services\GoogleResourceFetcher;

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
        $this->persist('selected_ads_customer_id', $value);
        $this->persist('selected_ads_manager_customer_id', $value === null ? null
            : app(GoogleResourceFetcher::class)->findManagerIdForAccount($this->settings(), $value));
    }

    public function updatedSelectedSearchConsoleSiteUrl(?string $value): void
    {
        $this->persist('selected_search_console_site_url', $value);
    }

    public function updatedSelectedGa4PropertyId(?string $value): void
    {
        $this->persist('selected_ga4_property_id', $value);
    }

    public function disconnect(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Model) {
            app(GoogleOAuthService::class)->disconnect($tenant);
        }

        $this->redirectRoute(config('google-connect.settings_route'), ['tenant' => $tenant]);
    }

    /**
     * @return array{ads: array<int, array{id: string, name: string, manager_id: ?string}>, sites: array<int, array{id: string, name: string}>, properties: array<int, array{account_id: string, account_name: string, property_id: string, property_name: string}>}
     */
    public function resourceOptions(): array
    {
        $settings = $this->settings();

        if (! $settings instanceof GoogleOAuthSettings || ! $settings->is_connected) {
            return ['ads' => [], 'sites' => [], 'properties' => []];
        }

        $fetcher = app(GoogleResourceFetcher::class);

        return [
            'ads' => $fetcher->listGoogleAdsAccounts($settings),
            'sites' => $fetcher->listSearchConsoleSites($settings),
            'properties' => $fetcher->listAnalyticsProperties($settings),
        ];
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
}
