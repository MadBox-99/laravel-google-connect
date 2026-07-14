<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_oauth_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained(config('google-connect.tenant_table', 'teams'))->cascadeOnDelete();

            // Identity of the connected Google account (display only).
            $table->string('connected_email')->nullable();
            $table->string('connected_name')->nullable();

            // OAuth tokens issued by the unified `google` provider.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_connected')->default(false);

            // User-selected resources (filled in from selectors after connect).
            $table->string('selected_ads_customer_id')->nullable();
            $table->string('selected_ads_manager_customer_id')->nullable();
            $table->string('selected_search_console_site_url')->nullable();
            $table->string('selected_ga4_account_id')->nullable();
            $table->string('selected_ga4_property_id')->nullable();

            $table->timestamp('last_sync_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_oauth_settings');
    }
};
