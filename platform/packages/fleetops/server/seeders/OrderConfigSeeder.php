<?php

namespace GridX\FleetOps\Seeders;

use GridX\FleetOps\Support\FleetOps;
use GridX\Models\Company;
use Illuminate\Database\Seeder;

class OrderConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $companies = Company::all();
        foreach ($companies as $company) {
            FleetOps::createTransportConfig($company);
        }
    }
}
