<?php

namespace GridX\FleetOps\Listeners;

use GridX\FleetOps\Notifications\OrderAssigned;
use GridX\FleetOps\Notifications\OrderCanceled;
use GridX\FleetOps\Notifications\OrderCompleted;
use GridX\FleetOps\Notifications\OrderDispatched;
use GridX\FleetOps\Notifications\OrderDispatchFailed;
use GridX\FleetOps\Notifications\OrderFailed;
use GridX\Support\NotificationRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyOrderEvent implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @param object $event
     *
     * @return void
     */
    public function handle($event)
    {
        // Get the order record from the event
        $order = $event->getModelRecord();

        if ($order) {
            // Send a notification for order events
            if ($event instanceof \GridX\FleetOps\Events\OrderCanceled) {
                $reason = $event->activity ? $event->activity->get('details') : '';
                NotificationRegistry::notify(OrderCanceled::class, $order, $reason, $event->waypoint);
            }

            if ($event instanceof \GridX\FleetOps\Events\OrderCompleted) {
                NotificationRegistry::notify(OrderCompleted::class, $order, $event->waypoint);
            }

            if ($event instanceof \GridX\FleetOps\Events\OrderFailed) {
                $reason = $event->activity ? $event->activity->get('details') : '';
                NotificationRegistry::notify(OrderFailed::class, $order, $reason, $event->waypoint);
            }

            if ($event instanceof \GridX\FleetOps\Events\OrderDispatchFailed) {
                NotificationRegistry::notify(OrderDispatchFailed::class, $order);
            }

            if ($event instanceof \GridX\FleetOps\Events\OrderDispatched) {
                NotificationRegistry::notify(OrderDispatched::class, $order, $event->waypoint);
            }

            if ($event instanceof \GridX\FleetOps\Events\OrderDriverAssigned) {
                NotificationRegistry::notify(OrderAssigned::class, $order);
            }
        }
    }
}
