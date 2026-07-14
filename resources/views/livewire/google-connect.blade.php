<div class="space-y-4">
    @if (! $settings?->is_connected)
        <div class="text-sm text-danger-600 dark:text-danger-400">{{ __('Not connected') }}</div>
        <a href="{{ route('google.oauth.redirect', ['team' => \Filament\Facades\Filament::getTenant()]) }}"
           class="fi-btn fi-btn-color-primary inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold text-white bg-primary-600">
            {{ __('Connect Google') }}
        </a>
    @else
        @php($options = $this->resourceOptions())
        <div class="text-sm text-success-600 dark:text-success-400">
            {{ __('Connected as :email', ['email' => $settings->connected_email ?? __('Connected')]) }}
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('Google Ads Account') }}</label>
            <select wire:model.live="selectedAdsCustomerId" class="fi-input block w-full rounded-lg">
                <option value="">{{ __('— Select —') }}</option>
                @foreach ($options['ads'] as $account)
                    <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('Search Console Property') }}</label>
            <select wire:model.live="selectedSearchConsoleSiteUrl" class="fi-input block w-full rounded-lg">
                <option value="">{{ __('— Select —') }}</option>
                @foreach ($options['sites'] as $site)
                    <option value="{{ $site['id'] }}">{{ $site['name'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('GA4 Property') }}</label>
            <select wire:model.live="selectedGa4PropertyId" class="fi-input block w-full rounded-lg">
                <option value="">{{ __('— Select —') }}</option>
                @foreach ($options['properties'] as $property)
                    <option value="{{ $property['property_id'] }}">{{ $property['property_name'] }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" wire:click="disconnect"
                class="fi-btn fi-btn-color-danger inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold">
            {{ __('Disconnect') }}
        </button>
    @endif
</div>
