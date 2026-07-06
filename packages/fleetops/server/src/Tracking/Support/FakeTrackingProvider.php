<?php

namespace GridX\FleetOps\Tracking\Support;

use GridX\FleetOps\Tracking\Contracts\TrackingProviderInterface;
use GridX\FleetOps\Tracking\Providers\CalculatedTrackingProvider;
use GridX\FleetOps\Tracking\TrackingContext;
use GridX\FleetOps\Tracking\TrackingOptions;
use GridX\FleetOps\Tracking\TrackingProviderCapabilities;
use GridX\FleetOps\Tracking\TrackingProviderResult;

class FakeTrackingProvider implements TrackingProviderInterface
{
    public function __construct(protected string $providerKey = 'fake')
    {
    }

    public function key(): string
    {
        return $this->providerKey;
    }

    public function capabilities(): TrackingProviderCapabilities
    {
        return new TrackingProviderCapabilities(traffic: true, perLegEta: true);
    }

    public function canTrack(TrackingContext $context): bool
    {
        return $context->canRoute();
    }

    public function track(TrackingContext $context, TrackingOptions $options): TrackingProviderResult
    {
        $result             = (new CalculatedTrackingProvider())->track($context, $options);
        $result->provider   = $this->providerKey;
        $result->confidence = 'high';
        $result->warnings   = [];

        return $result;
    }
}
