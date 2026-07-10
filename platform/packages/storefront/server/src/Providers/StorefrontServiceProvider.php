<?php

namespace GridX\Storefront\Providers;

use GridX\FleetOps\Providers\FleetOpsServiceProvider;
use GridX\Providers\CoreServiceProvider;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('Storefront cannot be loaded without `gridx/core-api` installed!');
}

if (!class_exists(FleetOpsServiceProvider::class)) {
    throw new \Exception('Storefront cannot be loaded without `gridx/fleetops-api` installed!');
}

/**
 * Storefront service provider.
 */
class StorefrontServiceProvider extends CoreServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [
        \GridX\Storefront\Models\Product::class   => \GridX\Storefront\Observers\ProductObserver::class,
        \GridX\Storefront\Models\Network::class   => \GridX\Storefront\Observers\NetworkObserver::class,
        \GridX\Storefront\Models\Catalog::class   => \GridX\Storefront\Observers\CatalogObserver::class,
        \GridX\Storefront\Models\FoodTruck::class => \GridX\Storefront\Observers\FoodTruckObserver::class,
        \GridX\Models\Company::class              => \GridX\Storefront\Observers\CompanyObserver::class,
    ];

    /**
     * The middleware groups registered with the service provider.
     *
     * @var array
     */
    public $middleware = [
        'storefront.api' => [
            \GridX\Storefront\Http\Middleware\ThrottleRequests::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \GridX\Storefront\Http\Middleware\SetStorefrontSession::class,
            \GridX\Http\Middleware\ConvertStringBooleans::class,
            \GridX\Http\Middleware\SetGlobalHeaders::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \GridX\Http\Middleware\LogApiRequests::class,
        ],
    ];

    /**
     * The console commands registered with the service provider.
     *
     * @var array
     */
    public $commands = [
        \GridX\Storefront\Console\Commands\NotifyStorefrontOrderNearby::class,
        \GridX\Storefront\Console\Commands\SendOrderNotification::class,
        \GridX\Storefront\Console\Commands\PurgeExpiredCarts::class,
        \GridX\Storefront\Console\Commands\MigrateStripeSandboxCustomers::class,
    ];

    /**
     * Register any application services.
     *
     * Within the register method, you should only bind things into the
     * service container. You should never attempt to register any event
     * listeners, routes, or any other piece of functionality within the
     * register method.
     *
     * More information on this can be found in the Laravel documentation:
     * https://laravel.com/docs/8.x/providers
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
        $this->app->register(FleetOpsServiceProvider::class);
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     *
     * @throws \Exception if the `gridx/core-api` package is not installed
     * @throws \Exception if the `gridx/fleetops-api` package is not installed
     */
    public function boot()
    {
        $this->registerCommands();
        $this->scheduleCommands(function ($schedule) {
            $schedule->command('storefront:notify-order-nearby')->everyMinute()->storeOutputInDb();
            $schedule->command('storefront:purge-carts')->daily()->storeOutputInDb();
        });
        $this->registerObservers();
        $this->registerMiddleware();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../config/database.connections.php', 'database.connections');
        $this->mergeConfigFrom(__DIR__ . '/../../config/storefront.php', 'storefront');
        $this->mergeConfigFrom(__DIR__ . '/../../config/api.php', 'storefront.api');
    }
}
