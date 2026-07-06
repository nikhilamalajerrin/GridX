<?php

namespace GridX\FleetOps\Events;

use GridX\Events\ResourceLifecycleEvent;
use GridX\FleetOps\Flow\Activity;
use GridX\FleetOps\Models\Waypoint;

class OrderCanceled extends ResourceLifecycleEvent
{
    /**
     * The event name.
     *
     * @var string
     */
    public $eventName = 'canceled';

    /**
     * Assosciated activity which triggered the event.
     */
    public ?Activity $activity = null;

    /**
     * Assosciated order waypoint which event is for.
     */
    public ?Waypoint $waypoint = null;
}
