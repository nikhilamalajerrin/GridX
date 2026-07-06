<?php

namespace GridX\FleetOps\Events;

use GridX\Events\ResourceLifecycleEvent;
use GridX\FleetOps\Flow\Activity;

class OrderReady extends ResourceLifecycleEvent
{
    /**
     * The event name.
     *
     * @var string
     */
    public $eventName = 'ready';

    /**
     * Assosciated activity which triggered the event.
     */
    public ?Activity $activity = null;
}
