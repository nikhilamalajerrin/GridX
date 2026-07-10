<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\FleetOps\Http\Requests\CreateFleetRequest;
use GridX\FleetOps\Http\Requests\UpdateFleetRequest;
use GridX\FleetOps\Http\Resources\v1\DeletedResource;
use GridX\FleetOps\Http\Resources\v1\Fleet as FleetResource;
use GridX\FleetOps\Models\Fleet;
use GridX\FleetOps\Support\Utils;
use GridX\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    /**
     * Creates a new GridX Fleet resource.
     *
     * @param \GridX\Http\Requests\CreateFleetRequest $request
     *
     * @return \GridX\Http\Resources\Fleet
     */
    public function create(CreateFleetRequest $request)
    {
        // get request input
        $input = $request->only(['name']);

        // make sure company is set
        $input['company_uuid'] = session('company');

        // service area assignment
        if ($request->has('service_area')) {
            $input['service_area_uuid'] = Utils::getUuid('service_areas', [
                'public_id'    => $request->input('service_area'),
                'company_uuid' => session('company'),
            ]);
        }

        // create the fleet
        $fleet = Fleet::create($input);

        // response the driver resource
        return new FleetResource($fleet);
    }

    /**
     * Updates a GridX Fleet resource.
     *
     * @param string                                      $id
     * @param \GridX\Http\Requests\UpdateFleetRequest $request
     *
     * @return \GridX\Http\Resources\Fleet
     */
    public function update($id, UpdateFleetRequest $request)
    {
        // find for the fleet
        try {
            $fleet = Fleet::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Fleet resource not found.',
                ],
                404
            );
        }

        // get request input
        $input = $request->only(['name']);

        // service area assignment
        if ($request->has('service_area')) {
            $input['service_area_uuid'] = Utils::getUuid('service_areas', [
                'public_id'    => $request->input('service_area'),
                'company_uuid' => session('company'),
            ]);
        }

        // update the fleet
        $fleet->update($input);

        // response the fleet resource
        return new FleetResource($fleet);
    }

    /**
     * Query for GridX Fleet resources.
     *
     * @return \GridX\Http\Resources\FleetCollection
     */
    public function query(Request $request)
    {
        $results = Fleet::queryWithRequest($request);

        return FleetResource::collection($results);
    }

    /**
     * Finds a single GridX Fleet resources.
     *
     * @return \GridX\Http\Resources\FleetCollection
     */
    public function find($id, Request $request)
    {
        // find for the fleet
        try {
            $fleet = Fleet::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Fleet resource not found.',
                ],
                404
            );
        }

        // response the fleet resource
        return new FleetResource($fleet);
    }

    /**
     * Deletes a GridX Fleet resources.
     *
     * @return \GridX\Http\Resources\FleetCollection
     */
    public function delete($id, Request $request)
    {
        // find for the driver
        try {
            $fleet = Fleet::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Fleet resource not found.',
                ],
                404
            );
        }

        // delete the fleet
        $fleet->delete();

        // response the fleet resource
        return new DeletedResource($fleet);
    }
}
