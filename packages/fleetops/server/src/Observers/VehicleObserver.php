<?php

namespace GridX\FleetOps\Observers;

use GridX\FleetOps\Models\Driver;
use GridX\FleetOps\Models\Vehicle;
use GridX\FleetOps\Support\LiveCacheService;

class VehicleObserver
{
    /**
     * Handle the Vehicle "created" event.
     *
     * @return void
     */
    public function created(Vehicle $vehicle)
    {
        // assign this vehicle to a driver if the driver has been set
        $identifier = request()->or(['driver_uuid', 'vehicle.driver_uuid', 'vehicle.driver.uuid']);

        if ($identifier) {
            $driver = Driver::where('uuid', $identifier)->whereNull('deleted_at')->withoutGlobalScopes()->first();

            if ($driver) {
                // assign this vehicle to driver
                $driver->assignVehicle($vehicle);

                // set driver to vehicle
                $vehicle->setRelation('driver', $driver);
            }
        }

        LiveCacheService::invalidateMultiple(['vehicles', 'operations-monitor']);
    }

    /**
     * Handle the Vehicle "updated" event.
     *
     * @return void
     */
    public function updating(Vehicle $vehicle)
    {
        // assign this vehicle to a driver if the driver has been set
        $identifier = request()->or(['driver_uuid', 'vehicle.driver_uuid', 'vehicle.driver.uuid']);

        if ($identifier) {
            $driver = Driver::where('uuid', $identifier)->whereNull('deleted_at')->withoutGlobalScopes()->first();

            if ($driver) {
                // assign this vehicle to driver
                $driver->assignVehicle($vehicle, false);

                // set driver to vehicle
                $vehicle->setRelation('driver', $driver);
            }
        }

        LiveCacheService::invalidateMultiple(['vehicles', 'operations-monitor']);
    }

    /**
     * Handle the Vehicle "deleted" event.
     *
     * @return void
     */
    public function deleted(Vehicle $vehicle)
    {
        // Unassign the deleted vehicle from matching driver/(s)
        Driver::where(['vehicle_uuid' => $vehicle->uuid])->delete();

        LiveCacheService::invalidateMultiple(['vehicles', 'operations-monitor']);
    }
}
