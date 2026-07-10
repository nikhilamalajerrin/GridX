<?php

namespace GridX\FleetOps\Events;

use GridX\Events\ResourceLifecycleEvent;
use GridX\FleetOps\Models\Order;

class OrderDispatchFailed extends ResourceLifecycleEvent
{
    /**
     * The event name.
     *
     * @var string
     */
    public $eventName = 'dispatch_failed';

    /**
     * The reason dispatch failed.
     *
     * @var string
     */
    public $reason = 'dispatch_failed';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Order $order, $reason = '')
    {
        parent::__construct($order);
        $this->reason = $reason;
    }

    /**
     * Returns the reason the dispatch failed.
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
