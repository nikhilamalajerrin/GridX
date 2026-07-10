<?php

namespace GridX\FleetOps\Listeners;

use GridX\FleetOps\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleOrderCreated implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(Order $order)
    {
        // set google matrix based distance and time
        $order->setDistanceAndTime();
    }
}
