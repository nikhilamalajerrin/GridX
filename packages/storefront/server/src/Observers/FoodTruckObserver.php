<?php

namespace GridX\Storefront\Observers;

use GridX\Storefront\Models\FoodTruck;
use Illuminate\Support\Facades\Request;

class FoodTruckObserver
{
    /**
     * Handle the FoodTruck "saved" event.
     *
     * @param FoodTruck $foodTruck the FoodTruck that was saved
     */
    public function saved(FoodTruck $foodTruck): void
    {
        try {
            $catalogs = Request::input('foodTruck.catalogs', []);
            $foodTruck->setCatalogs($catalogs);
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
