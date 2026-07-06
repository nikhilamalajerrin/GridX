<?php

namespace GridX\FleetOps\Observers;

use GridX\FleetOps\Models\ServiceArea;
use GridX\FleetOps\Models\Zone;

class ZoneObserver
{
    /**
     * Handle the Zone "created" event.
     */
    public function created(Zone $zone): void
    {
        $this->invalidateServiceAreaCache($zone);
    }

    /**
     * Handle the Zone "updated" event.
     */
    public function updated(Zone $zone): void
    {
        $this->invalidateServiceAreaCache($zone, $zone->getOriginal('service_area_uuid'));
        $this->invalidateServiceAreaCache($zone);
    }

    /**
     * Handle the Zone "deleted" event.
     */
    public function deleted(Zone $zone): void
    {
        $this->invalidateServiceAreaCache($zone);
    }

    /**
     * Handle the Zone "restored" event.
     */
    public function restored(Zone $zone): void
    {
        $this->invalidateServiceAreaCache($zone);
    }

    /**
     * Invalidate service area API cache because service area responses embed zones.
     */
    protected function invalidateServiceAreaCache(Zone $zone, ?string $serviceAreaUuid = null): void
    {
        $serviceAreaUuid ??= $zone->service_area_uuid;
        if (!$serviceAreaUuid) {
            return;
        }

        ServiceArea::invalidateApiCacheManually($zone->company_uuid);
        ServiceArea::invalidateApiCacheManually();
    }
}
