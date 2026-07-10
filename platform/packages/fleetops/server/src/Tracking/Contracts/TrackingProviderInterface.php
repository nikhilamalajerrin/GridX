<?php

namespace GridX\FleetOps\Tracking\Contracts;

use GridX\FleetOps\Tracking\TrackingContext;
use GridX\FleetOps\Tracking\TrackingOptions;
use GridX\FleetOps\Tracking\TrackingProviderCapabilities;
use GridX\FleetOps\Tracking\TrackingProviderResult;

interface TrackingProviderInterface
{
    public function key(): string;

    public function capabilities(): TrackingProviderCapabilities;

    public function canTrack(TrackingContext $context): bool;

    public function track(TrackingContext $context, TrackingOptions $options): TrackingProviderResult;
}
