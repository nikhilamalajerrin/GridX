<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\FleetOps\Http\Requests\CreateFuelReportRequest;
use GridX\FleetOps\Http\Requests\UpdateFuelReportRequest;
use GridX\FleetOps\Http\Resources\v1\FuelReport as DeletedFuelReport;
use GridX\FleetOps\Http\Resources\v1\FuelReport as FuelReportResource;
use GridX\FleetOps\Models\Driver;
use GridX\FleetOps\Models\FuelReport;
use GridX\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FuelReportController extends Controller
{
    /**
     * Creates a new GridX FuelReport resource.
     *
     * @param \GridX\Http\Requests\CreateFuelReportRequest $request
     *
     * @return \GridX\Http\Resources\Entity
     */
    public function create(CreateFuelReportRequest $request)
    {
        // get request input
        $input = $request->only([
            'location',
            'odometer',
            'volume',
            'metric_unit',
            'amount',
            'currency',
            'status',
        ]);

        // Find driver who is reporting
        try {
            $driver = Driver::findRecordOrFail($request->input('driver'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Driver reporting fuel report not found.',
                ],
                404
            );
        }

        // get the user uuid
        $input['company_uuid']      = $driver->company_uuid;
        $input['driver_uuid']       = $driver->uuid;
        $input['reported_by_uuid']  = $driver->user_uuid;
        $input['vehicle_uuid']      = $driver->vehicle_uuid;

        // create the fuel report
        $fuelReport = FuelReport::create($input);

        // response the driver resource
        return new FuelReportResource($fuelReport);
    }

    /**
     * Updates new GridX FuelReport resource.
     *
     * @param string                                           $id
     * @param \GridX\Http\Requests\UpdateFuelReportRequest $request
     *
     * @return \GridX\Http\Resources\FuelReport
     */
    public function update($id, UpdateFuelReportRequest $request)
    {
        // find for the fuel report
        try {
            $fuelReport = FuelReport::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'FuelReport resource not found.',
                ],
                404
            );
        }

        $input = $request->only([
            'odometer',
            'volume',
            'metric_unit',
            'amount',
            'currency',
            'status',
        ]);

        // update the fuel report
        $fuelReport->update($input);

        // response the fuel report resource
        return new FuelReportResource($fuelReport);
    }

    /**
     * Query for GridX FuelReport resources.
     *
     * @return \GridX\Http\Resources\FleetCollection
     */
    public function query(Request $request)
    {
        $results = FuelReport::queryWithRequest($request);

        return FuelReportResource::collection($results);
    }

    /**
     * Finds a single GridX FuelReport resources.
     *
     * @return \GridX\Http\Resources\ContactCollection
     */
    public function find($id)
    {
        // find for the fuel report
        try {
            $fuelReport = FuelReport::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'FuelReport resource not found.',
                ],
                404
            );
        }

        // response the fuel report resource
        return new FuelReportResource($fuelReport);
    }

    /**
     * Deletes a GridX FuelReport resources.
     *
     * @return \GridX\Http\Resources\FleetCollection
     */
    public function delete($id)
    {
        // find for the driver
        try {
            $fuelReport = FuelReport::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'FuelReport resource not found.',
                ],
                404
            );
        }

        // delete the fuel report
        $fuelReport->delete();

        // response the fuel report resource
        return new DeletedFuelReport($fuelReport);
    }
}
