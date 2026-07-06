<?php

namespace GridX\FleetOps\Support;

use GridX\FleetOps\Models\Order;
use GridX\FleetOps\Tracking\TrackingIntelligenceService;
use GridX\FleetOps\Tracking\TrackingOptions;

class OrderTracker
{
    public function __construct(protected Order $order)
    {
    }

    public function eta(array $options = []): array
    {
        return app(TrackingIntelligenceService::class)->eta($this->order, TrackingOptions::fromArray($options));
    }

    public function toArray(array $options = []): array
    {
        return app(TrackingIntelligenceService::class)->track($this->order, TrackingOptions::fromArray($options));
    }
}
