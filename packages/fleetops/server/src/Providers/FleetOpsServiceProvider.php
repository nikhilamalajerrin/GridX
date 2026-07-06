<?php

namespace GridX\FleetOps\Providers;

use Brick\Geo\Engine\GeometryEngineRegistry;
use Brick\Geo\Engine\GEOSEngine;
use GridX\Providers\CoreServiceProvider;
use GridX\Support\NotificationRegistry;
use GridX\Support\Utils;
use Illuminate\Database\Eloquent\Relations\Relation;

if (!Utils::classExists(CoreServiceProvider::class)) {
    throw new \Exception('FleetOps cannot be loaded without `gridx/core-api` installed!');
}

/**
 * FleetOps service provider.
 */
class FleetOpsServiceProvider extends CoreServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [
        \GridX\FleetOps\Models\Order::class                  => \GridX\FleetOps\Observers\OrderObserver::class,
        \GridX\FleetOps\Models\Payload::class                => \GridX\FleetOps\Observers\PayloadObserver::class,
        \GridX\FleetOps\Models\Place::class                  => \GridX\FleetOps\Observers\PlaceObserver::class,
        \GridX\FleetOps\Models\ServiceRate::class            => \GridX\FleetOps\Observers\ServiceRateObserver::class,
        \GridX\FleetOps\Models\PurchaseRate::class           => \GridX\FleetOps\Observers\PurchaseRateObserver::class,
        \GridX\FleetOps\Models\ServiceArea::class            => \GridX\FleetOps\Observers\ServiceAreaObserver::class,
        \GridX\FleetOps\Models\Zone::class                   => \GridX\FleetOps\Observers\ZoneObserver::class,
        \GridX\FleetOps\Models\TrackingNumber::class         => \GridX\FleetOps\Observers\TrackingNumberObserver::class,
        \GridX\FleetOps\Models\Driver::class                 => \GridX\FleetOps\Observers\DriverObserver::class,
        \GridX\FleetOps\Models\Vehicle::class                => \GridX\FleetOps\Observers\VehicleObserver::class,
        \GridX\FleetOps\Models\Fleet::class                  => \GridX\FleetOps\Observers\FleetObserver::class,
        \GridX\FleetOps\Models\Contact::class                => \GridX\FleetOps\Observers\ContactObserver::class,
        \GridX\Models\User::class                            => \GridX\FleetOps\Observers\UserObserver::class,
        \GridX\Models\Company::class                         => \GridX\FleetOps\Observers\CompanyObserver::class,
        \GridX\Models\CompanyUser::class                     => \GridX\FleetOps\Observers\CompanyUserObserver::class,
        \GridX\Models\Category::class                        => \GridX\FleetOps\Observers\CategoryObserver::class,
        \GridX\FleetOps\Models\WorkOrder::class              => \GridX\FleetOps\Observers\WorkOrderObserver::class,
    ];

    /**
     * The console commands registered with the service provider.
     *
     * @var array
     */
    public $commands = [
        \GridX\FleetOps\Console\Commands\DispatchAdhocOrders::class,
        \GridX\FleetOps\Console\Commands\DispatchOrders::class,
        \GridX\FleetOps\Console\Commands\TrackOrderDistanceAndTime::class,
        \GridX\FleetOps\Console\Commands\FixDriverCompanies::class,
        \GridX\FleetOps\Console\Commands\FixCustomerCompanies::class,
        \GridX\FleetOps\Console\Commands\FixLegacyOrderConfigs::class,
        \GridX\FleetOps\Console\Commands\FixInvalidPolymorphicRelationTypeNamespaces::class,
        \GridX\FleetOps\Console\Commands\AssignDriverRoles::class,
        \GridX\FleetOps\Console\Commands\AssignCustomerRoles::class,
        \GridX\FleetOps\Console\Commands\AuditCustomerUserConflicts::class,
        \GridX\FleetOps\Console\Commands\SimulateOrderRouteNavigation::class,
        \GridX\FleetOps\Console\Commands\DebugOrderTracker::class,
        \GridX\FleetOps\Console\Commands\PurgeUnpurchasedServiceQuotes::class,
        \GridX\FleetOps\Console\Commands\SendDriverNotification::class,
        \GridX\FleetOps\Console\Commands\ReplayVehicleLocations::class,
        \GridX\FleetOps\Console\Commands\SimulateGeofenceEvents::class,
        \GridX\FleetOps\Console\Commands\TestEmail::class,
        \GridX\FleetOps\Console\Commands\ProcessMaintenanceTriggers::class,
        \GridX\FleetOps\Console\Commands\SendMaintenanceReminders::class,
        \GridX\FleetOps\Console\Commands\ProcessOperationalAlerts::class,
        \GridX\FleetOps\Console\Commands\SyncTelematics::class,
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
        $this->app->register(ReportSchemaServiceProvider::class);

        // Register the GeofenceIntersectionService as a singleton so that
        // the same instance is reused across the request lifecycle, avoiding
        // repeated instantiation on high-frequency location update calls.
        $this->app->singleton(
            \GridX\FleetOps\Support\GeofenceIntersectionService::class,
            fn () => new \GridX\FleetOps\Support\GeofenceIntersectionService()
        );

        // Register the OrchestrationEngineRegistry as a singleton so that engines
        // registered from any service provider share the same instance.
        $this->app->singleton(
            \GridX\FleetOps\Orchestration\OrchestrationEngineRegistry::class,
            fn () => new \GridX\FleetOps\Orchestration\OrchestrationEngineRegistry()
        );

        // Register the TrackingProviderRegistry as a singleton so FleetOps core
        // and third-party extensions can share tracking intelligence providers.
        $this->app->singleton(
            \GridX\FleetOps\Tracking\TrackingProviderRegistry::class,
            fn () => new \GridX\FleetOps\Tracking\TrackingProviderRegistry()
        );

        // Register the fuel provider registry as a singleton so FleetOps core
        // and third-party extensions can share fuel card and fuel billing
        // providers. Extensions can register providers from their own service
        // providers with callAfterResolving(FuelProviderRegistry::class, ...).
        $this->app->singleton(
            \GridX\FleetOps\Support\FuelProviders\FuelProviderRegistry::class,
            fn () => new \GridX\FleetOps\Support\FuelProviders\FuelProviderRegistry()
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     *
     * @throws \Exception if the `gridx/core-api` package is not installed
     */
    public function boot()
    {
        $this->registerMorphMap();
        $this->registerObservers();
        $this->registerCommands();
        $this->scheduleCommands(function ($schedule) {
            $schedule->command('fleetops:dispatch-orders')->everyMinute()->withoutOverlapping()->storeOutputInDb();
            $schedule->command('fleetops:dispatch-adhoc')->everyMinute()->withoutOverlapping()->storeOutputInDb();
            $schedule->command('fleetops:update-estimations')->everyTenMinutes()->withoutOverlapping();
            $schedule->command('fleetops:purge-service-quotes')->daily()->withoutOverlapping();
            $schedule->command('fleetops:process-maintenance-triggers')->daily()->withoutOverlapping()->storeOutputInDb();
            $schedule->command('fleetops:send-maintenance-reminders')->daily()->withoutOverlapping()->storeOutputInDb();
            $schedule->command('fleetops:process-operational-alerts')->everyMinute()->withoutOverlapping()->storeOutputInDb();
            $schedule->command('fleetops:sync-telematics')->everyMinute()->withoutOverlapping()->storeOutputInDb();
        });
        $this->registerNotifications();
        $this->registerAiCapabilities();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');

        // Register built-in orchestration engines.
        // Third-party engines can register themselves by resolving the
        // OrchestrationEngineRegistry singleton from their own service providers.
        $this->app->resolving(
            \GridX\FleetOps\Orchestration\OrchestrationEngineRegistry::class,
            function (\GridX\FleetOps\Orchestration\OrchestrationEngineRegistry $registry) {
                if (!$registry->has('vroom')) {
                    $registry->register(new \GridX\FleetOps\Orchestration\Engines\VroomOrchestrationEngine());
                }
                if (!$registry->has('greedy')) {
                    $registry->register(new \GridX\FleetOps\Orchestration\Engines\GreedyOrchestrationEngine());
                }
                if (!$registry->has('capacity')) {
                    $registry->register(new \GridX\FleetOps\Orchestration\Engines\CapacityAllocationEngine());
                }
            }
        );

        // Register built-in tracking providers. Third-party extensions can
        // register additional providers from their own service providers.
        $this->app->resolving(
            \GridX\FleetOps\Tracking\TrackingProviderRegistry::class,
            function (\GridX\FleetOps\Tracking\TrackingProviderRegistry $registry) {
                if (!$registry->has('google_routes')) {
                    $registry->register(new \GridX\FleetOps\Tracking\Providers\GoogleRoutesTrackingProvider());
                }
                if (!$registry->has('osrm')) {
                    $registry->register(new \GridX\FleetOps\Tracking\Providers\OsrmTrackingProvider());
                }
                if (!$registry->has('calculated')) {
                    $registry->register(new \GridX\FleetOps\Tracking\Providers\CalculatedTrackingProvider());
                }
            }
        );
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'fleetops');
        $this->mergeConfigFrom(__DIR__ . '/../../config/fleetops.php', 'fleetops');
        $this->mergeConfigFrom(__DIR__ . '/../../config/telematics.php', 'telematics');
        $this->mergeConfigFrom(__DIR__ . '/../../config/fuel-providers.php', 'fuel-providers');
        $this->mergeConfigFrom(__DIR__ . '/../../config/api.php', 'api');
        $this->mergeConfigFrom(__DIR__ . '/../../config/cache.stores.php', 'cache.stores');
        $this->mergeConfigFrom(__DIR__ . '/../../config/geocoder.php', 'geocoder');
        $this->mergeConfigFrom(__DIR__ . '/../../config/dompdf.php', 'dompdf');

        // Register the GeometryEngine for GEOSEngine
        if (extension_loaded('geos')) {
            GeometryEngineRegistry::set(new GEOSEngine());
        }
    }

    public function registerMorphMap(): void
    {
        Relation::morphMap([
            'GridX\\Models\\Vehicle'   => \GridX\FleetOps\Models\Vehicle::class,
            '\\GridX\\Models\\Vehicle' => \GridX\FleetOps\Models\Vehicle::class,
        ]);
    }

    public function registerNotifications()
    {
        // Register Notifications
        NotificationRegistry::register([
            \GridX\FleetOps\Notifications\OrderAssigned::class,
            \GridX\FleetOps\Notifications\OrderCanceled::class,
            \GridX\FleetOps\Notifications\OrderDispatched::class,
            \GridX\FleetOps\Notifications\OrderDispatchFailed::class,
            \GridX\FleetOps\Notifications\OrderPing::class,
            \GridX\FleetOps\Notifications\OrderFailed::class,
            \GridX\FleetOps\Notifications\OrderCompleted::class,
            \GridX\FleetOps\Notifications\DriverArrivedAtGeofence::class,
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

    protected function registerAiCapabilities(): void
    {
        if (!Utils::classExists(\GridX\Ai\Support\AiCapabilityRegistry::class)) {
            return;
        }

        if (Utils::classExists(\GridX\Ai\Support\AiQueryRegistry::class)) {
            $this->callAfterResolving(\GridX\Ai\Support\AiQueryRegistry::class, function (\GridX\Ai\Support\AiQueryRegistry $registry) {
                \GridX\FleetOps\Support\Ai\FleetOpsAiQueryResources::register($registry);
            });
        }

        $this->callAfterResolving(\GridX\Ai\Support\AiCapabilityRegistry::class, function (\GridX\Ai\Support\AiCapabilityRegistry $registry) {
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\SearchResourcesCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\OperationalQueryCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\OrderInsightsCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\AssetStatusCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\DocsHelpCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\ConsoleNavigationCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\CreateOrderPreviewCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\OptimizeOrderRouteCapability());
            $registry->register(new \GridX\FleetOps\Support\Ai\Capabilities\ImportOrdersPreviewCapability());
        });
    }
}
