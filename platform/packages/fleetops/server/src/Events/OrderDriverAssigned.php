<?php

namespace GridX\FleetOps\Events;

use GridX\Events\ResourceLifecycleEvent;
use GridX\FleetOps\Flow\Activity;

class OrderDriverAssigned extends ResourceLifecycleEvent
{
    /**
     * The event name.
     *
     * @var string
     */
    public $eventName = 'driver_assigned';

    /**
     * Assosciated activity which triggered the event.
     */
    public ?Activity $activity = null;
}
