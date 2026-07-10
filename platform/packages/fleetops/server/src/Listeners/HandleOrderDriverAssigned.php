<?php

namespace GridX\FleetOps\Listeners;

use GridX\FleetOps\Events\OrderDriverAssigned;
use GridX\FleetOps\Models\Driver;
use GridX\FleetOps\Models\Order;
use GridX\FleetOps\Notifications\OrderAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleOrderDriverAssigned implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @param object $event
     *
     * @return void
     */
    public function handle(OrderDriverAssigned $event)
    {
        /** @var Order $order */
        $order = $event->getModelRecord();

        // halt if unable to resolve order record from event
        if (!$order instanceof Order) {
            return;
        }

        /** @var Driver */
        $driver = Driver::where('uuid', $order->driver_assigned_uuid)->withoutGlobalScopes()->first();
        $order->setRelation('driverAssigned', $driver);

        // notify driver order has been assigned - only if order is not adhoc
        if ($driver && $order->adhoc === false) {
            $driver->notify(new OrderAssigned($order));
        }
    }
}
