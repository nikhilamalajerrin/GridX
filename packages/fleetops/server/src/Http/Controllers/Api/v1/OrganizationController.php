<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\Http\Controllers\Controller;
use GridX\Http\Resources\Organization;
use GridX\Models\Company;
use GridX\Models\Setting;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * Return List organizations.
     *
     * @return Organization
     */
    public function listOrganizations(Request $request)
    {
        $limit                    = $request->input('limit', 10);
        $withDriverOnboardEnabled = $request->boolean('with_driver_onboard');

        $companies = Company::whereHas('users')->get()->map(function ($company) {
            return [
                'name'   => $company->name,
                'id'     => $company->public_id,
            ];
        });

        if ($withDriverOnboardEnabled) {
            $driverOnboardSettings  = Setting::where('key', 'fleet-ops.driver-onboard-settings')->value('value');

            $companies = $companies->filter(function ($company) use ($driverOnboardSettings) {
                return $driverOnboardSettings && isset($driverOnboardSettings[$company['uuid']]) && data_get($driverOnboardSettings[$company['uuid']], 'enableDriverOnboardFromApp') === true;
            });
        }

        // limit
        $companies = $companies->take($limit);

        $companiesArray = $companies->values()->toArray();

        return response()->json($companiesArray);
    }
}
