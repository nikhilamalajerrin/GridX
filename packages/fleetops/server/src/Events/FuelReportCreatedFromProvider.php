<?php

namespace GridX\FleetOps\Events;

use GridX\FleetOps\Models\FuelProviderTransaction;
use GridX\FleetOps\Models\FuelReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FuelReportCreatedFromProvider
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public FuelProviderTransaction $transaction, public FuelReport $fuelReport)
    {
    }
}
