<?php

namespace GridX\FleetOps\Tracking\Providers;

use GridX\FleetOps\Support\Utils;
use GridX\FleetOps\Tracking\Contracts\TrackingProviderInterface;
use GridX\FleetOps\Tracking\TrackingContext;
use GridX\FleetOps\Tracking\TrackingOptions;
use GridX\FleetOps\Tracking\TrackingProviderCapabilities;
use GridX\FleetOps\Tracking\TrackingProviderResult;

class CalculatedTrackingProvider implements TrackingProviderInterface
{
    public function key(): string
    {
        return 'calculated';
    }

    public function capabilities(): TrackingProviderCapabilities
    {
        return new TrackingProviderCapabilities();
    }

    public function canTrack(TrackingContext $context): bool
    {
        return $context->canRoute();
    }

    public function track(TrackingContext $context, TrackingOptions $options): TrackingProviderResult
    {
        $points   = $context->routePoints();
        $distance = 0;
        $legs     = [];

        for ($i = 0; $i < count($points) - 1; $i++) {
            $legDistance = Utils::vincentyGreatCircleDistance($points[$i], $points[$i + 1]);
            $legDuration = $this->durationFromDistance($legDistance, $options);
            $distance += $legDistance;
            $legs[] = [
                'index'                 => $i,
                'distance_m'            => $legDistance,
                'duration_s'            => $legDuration,
                'duration_in_traffic_s' => null,
                'provider'              => $this->key(),
            ];
        }

        return new TrackingProviderResult(
            provider: $this->key(),
            distanceMeters: $distance,
            durationSeconds: $this->durationFromDistance($distance, $options),
            durationInTrafficSeconds: null,
            legs: $legs,
            warnings: ['calculated_route_used'],
            confidence: 'low'
        );
    }

    protected function durationFromDistance(float $distanceMeters, TrackingOptions $options): float
    {
        $metersPerSecond = max($options->defaultVehicleSpeedKph, 1) * 1000 / 3600;

        return round($distanceMeters / $metersPerSecond);
    }
}
