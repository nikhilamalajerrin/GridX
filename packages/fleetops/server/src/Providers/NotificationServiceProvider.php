<?php

namespace GridX\FleetOps\Providers;

use GridX\Providers\CoreServiceProvider;
use GridX\Support\NotificationRegistry;
use GridX\Support\Utils;

if (!Utils::classExists(CoreServiceProvider::class)) {
    throw new \Exception('FleetOps cannot be loaded without `gridx/core-api` installed!');
}

/**
 * NotificationServiceProvider service provider.
 */
class NotificationServiceProvider extends CoreServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     *
     * @throws \Exception if the `gridx/core-api` package is not installed
     */
    public function boot()
    {
        // Register Notifications
        NotificationRegistry::register([
            \GridX\FleetOps\Notifications\OrderAssigned::class,
            \GridX\FleetOps\Notifications\OrderCanceled::class,
            \GridX\FleetOps\Notifications\OrderDispatched::class,
            \GridX\FleetOps\Notifications\OrderDispatchFailed::class,
            \GridX\FleetOps\Notifications\OrderPing::class,
            \GridX\FleetOps\Notifications\LateDeparture::class,
            \GridX\FleetOps\Notifications\RouteDeviation::class,
            \GridX\FleetOps\Notifications\ProlongedStoppage::class,
        ]);

        // Register Notifiables
        NotificationRegistry::registerNotifiable([
            \GridX\FleetOps\Models\Contact::class,
            \GridX\FleetOps\Models\Driver::class,
            \GridX\FleetOps\Models\Vendor::class,
            \GridX\FleetOps\Models\Fleet::class,
            'dynamic:customer',
            'dynamic:driver',
            'dynamic:facilitator',
        ]);
    }
}
