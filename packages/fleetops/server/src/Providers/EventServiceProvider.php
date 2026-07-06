<?php

namespace GridX\FleetOps\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        /*
         * Order Events
         */
        \GridX\FleetOps\Events\OrderCanceled::class       => [\GridX\FleetOps\Listeners\HandleOrderCanceled::class, \GridX\Listeners\SendResourceLifecycleWebhook::class, \GridX\FleetOps\Listeners\NotifyOrderEvent::class],
        \GridX\FleetOps\Events\OrderDispatched::class     => [\GridX\FleetOps\Listeners\HandleOrderDispatched::class, \GridX\Listeners\SendResourceLifecycleWebhook::class, \GridX\FleetOps\Listeners\NotifyOrderEvent::class],
        \GridX\FleetOps\Events\OrderDispatchFailed::class => [\GridX\FleetOps\Listeners\HandleOrderDispatchFailed::class, \GridX\Listeners\SendResourceLifecycleWebhook::class, \GridX\FleetOps\Listeners\NotifyOrderEvent::class],
        \GridX\FleetOps\Events\OrderDriverAssigned::class => [\GridX\FleetOps\Listeners\HandleOrderDriverAssigned::class, \GridX\Listeners\SendResourceLifecycleWebhook::class, \GridX\FleetOps\Listeners\NotifyOrderEvent::class],
        \GridX\FleetOps\Events\OrderCompleted::class      => [\GridX\Listeners\SendResourceLifecycleWebhook::class, \GridX\FleetOps\Listeners\NotifyOrderEvent::class, \GridX\FleetOps\Listeners\HandleDeliveryCompletion::class],
        \GridX\FleetOps\Events\OrderFailed::class         => [\GridX\Listeners\SendResourceLifecycleWebhook::class, \GridX\FleetOps\Listeners\NotifyOrderEvent::class],
        \GridX\FleetOps\Events\OrderReady::class          => [\GridX\FleetOps\Listeners\HandleOrderReady::class],

        /*
         * Geofence Events
         *
         * Each event is handled by a domain listener (business logic, event log)
         * and the generic SendResourceLifecycleWebhook listener (webhook delivery).
         */
        \GridX\FleetOps\Events\GeofenceEntered::class => [
            \GridX\FleetOps\Listeners\HandleGeofenceEntered::class,
            \GridX\Listeners\SendResourceLifecycleWebhook::class,
        ],
        \GridX\FleetOps\Events\GeofenceExited::class  => [
            \GridX\FleetOps\Listeners\HandleGeofenceExited::class,
            \GridX\Listeners\SendResourceLifecycleWebhook::class,
        ],
        \GridX\FleetOps\Events\GeofenceDwelled::class => [
            \GridX\FleetOps\Listeners\HandleGeofenceDwelled::class,
            \GridX\Listeners\SendResourceLifecycleWebhook::class,
        ],

        /*
         * Core Events
         */
        \GridX\Events\UserRemovedFromCompany::class => [\GridX\FleetOps\Listeners\HandleUserRemovedFromCompany::class],

        /*
         * Scheduling Events
         */
        \GridX\Events\ScheduleItemCreated::class => [\GridX\FleetOps\Listeners\NotifyDriverOnShiftChange::class],
        \GridX\Events\ScheduleItemUpdated::class => [\GridX\FleetOps\Listeners\NotifyDriverOnShiftChange::class],
    ];
}
