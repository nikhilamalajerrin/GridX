<?php

namespace GridX\Storefront\Providers;

use GridX\Storefront\Models\Product;
use GridX\Storefront\Observers\ProductObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The model observers for your application.
     *
     * @var array
     */
    // protected $observers = [
    //     Product::class => [ProductObserver::class],
    // ];

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        /*
         * Order Events
         */
        \GridX\FleetOps\Events\OrderStarted::class        => [\GridX\Storefront\Listeners\HandleOrderStarted::class],
        \GridX\FleetOps\Events\OrderDispatched::class     => [\GridX\Storefront\Listeners\HandleOrderDispatched::class],
        \GridX\FleetOps\Events\OrderCompleted::class      => [\GridX\Storefront\Listeners\HandleOrderCompleted::class],
        \GridX\FleetOps\Events\OrderDriverAssigned::class => [\GridX\Storefront\Listeners\HandleOrderDriverAssigned::class],
    ];
}
