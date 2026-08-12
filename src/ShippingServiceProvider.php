<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping;

use Illuminate\Support\ServiceProvider;

/**
 * Registers this module's schema and nothing else.
 *
 * It binds neither seam on purpose. {@see Contracts\FetchesCarrierRates} unbound
 * is a supported deployment, and {@see Contracts\ResolvesParcels} unbound must
 * fail loudly at the boundary rather than be papered over by a default that
 * invents a weight.
 *
 * The package is never auto-discovered: `extra.laravel.providers` is empty, and
 * the host registers this provider only when the module is enabled.
 */
final class ShippingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ecommerce-shipping-migrations');
        }
    }
}
