<?php

namespace GridX\FleetOps\Observers;

use GridX\FleetOps\Support\FleetOps;
use GridX\Models\Company;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     *
     * @return void
     */
    public function created(Company $company)
    {
        // Add the default transport order config
        FleetOps::createTransportConfig($company);
    }
}
