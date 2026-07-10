<?php

namespace GridX\FleetOps\Events;

use GridX\Events\ResourceLifecycleEvent;
use GridX\FleetOps\Flow\Activity;

class OrderStarted extends ResourceLifecycleEvent
{
    /**
     * The event name.
     *
     * @var string
     */
    public $eventName = 'started';

    /**
     * Assosciated activity which triggered the event.
     */
    public ?Activity $activity = null;
}
