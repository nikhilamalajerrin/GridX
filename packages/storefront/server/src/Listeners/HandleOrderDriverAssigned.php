<?php

namespace GridX\Storefront\Listeners;

use GridX\FleetOps\Events\OrderDriverAssigned;
use GridX\FleetOps\Models\Order;
use GridX\Storefront\Notifications\StorefrontOrderDriverAssigned;
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

        // if storefront order notify customer driver has been addigned
        if ($order->hasMeta('storefront_id')) {
            $order->load(['customer']);
            $order->customer->notify(new StorefrontOrderDriverAssigned($order));
        }
    }
}
