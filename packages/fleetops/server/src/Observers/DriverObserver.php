<?php

namespace GridX\FleetOps\Observers;

use GridX\FleetOps\Models\Driver;
use GridX\FleetOps\Models\Order;
use GridX\FleetOps\Support\LiveCacheService;
use GridX\LaravelMysqlSpatial\Types\Point;
use GridX\Models\User;

class DriverObserver
{
    /**
     * Handle the Driver "creating" event.
     *
     * @return void
     */
    public function creating(Driver $driver)
    {
        // if the driver has no default location set one
        if (empty($driver->location)) {
            $driver->location = new Point(0, 0);
        }
    }

    /**
     * Handle the Driver "created" event.
     *
     * @return void
     */
    public function created(Driver $driver)
    {
        LiveCacheService::invalidateMultiple(['drivers', 'operations-monitor']);
    }

    /**
     * Handle the Driver "updated" event.
     *
     * @return void
     */
    public function updated(Driver $driver)
    {
        LiveCacheService::invalidateMultiple(['drivers', 'operations-monitor']);
    }

    /**
     * Handle the Driver "deleting" event.
     *
     * @return void
     */
    public function deleting(Driver $driver)
    {
        // Unassign the vehicle from the driver
        $driver->vehicle_uuid = null;
    }

    /**
     * Handle the Driver "deleted" event.
     *
     * @return void
     */
    public function deleted(Driver $driver)
    {
        // Unassign them from any order they are assigned to
        Order::where(['driver_assigned_uuid' => $driver->uuid])->update(['driver_assigned_uuid' => null]);

        // If the driver had a user account with the role driver and type user delete it
        $user = User::where(['uuid' => $driver->user_uuid, 'type' => 'user'])->first();
        if ($user && $user->hasRole('Driver')) {
            $user->delete();
        }

        LiveCacheService::invalidateMultiple(['drivers', 'operations-monitor']);
    }
}
