<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\FleetOps\Http\Requests\CreateTrackingNumberRequest;
use GridX\FleetOps\Http\Requests\DecodeTrackingNumberQR;
use GridX\FleetOps\Http\Resources\v1\DeletedResource;
use GridX\FleetOps\Http\Resources\v1\TrackingNumber as TrackingNumberResource;
use GridX\FleetOps\Models\TrackingNumber;
use GridX\FleetOps\Support\Utils;
use GridX\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrackingNumberController extends Controller
{
    /**
     * Creates a new GridX TrackingNumber resource.
     *
     * @param \GridX\Http\Requests\CreateTrackingNumberRequest $request
     *
     * @return \GridX\Http\Resources\TrackingNumber
     */
    public function create(CreateTrackingNumberRequest $request)
    {
        // get request input
        $input = $request->only(['region', 'type']);

        // make sure company is set
        $input['company_uuid'] = session('company');

        // owner assignment
        if ($request->has('owner')) {
            $owner = Utils::getUuid(
                ['orders', 'entities'],
                [
                    'public_id'    => $request->input('owner'),
                    'company_uuid' => session('company'),
                ],
                [
                    'with_table' => true,
                ]
            );

            if (is_array($owner)) {
                $input['owner_uuid']       = Utils::get($owner, 'uuid');
                $input['owner_type']       = Utils::getModelClassName(Utils::get($owner, 'table'));
            }
        }

        // create the trackingNumber
        $trackingNumber = TrackingNumber::create($input);

        // response the driver resource
        return new TrackingNumberResource($trackingNumber);
    }

    /**
     * Query for GridX TrackingNumber resources.
     *
     * @return \GridX\Http\Resources\TrackingNumberCollection
     */
    public function query(Request $request)
    {
        $results = TrackingNumber::queryWithRequest($request);

        return TrackingNumberResource::collection($results);
    }

    /**
     * Finds a single GridX TrackingNumber resources.
     *
     * @return \GridX\Http\Resources\TrackingNumberCollection
     */
    public function find($id)
    {
        // find for the trackingNumber
        try {
            $trackingNumber = TrackingNumber::findTrackingOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'TrackingNumber resource not found.',
                ],
                404
            );
        }

        // response the trackingNumber resource
        return new TrackingNumberResource($trackingNumber);
    }

    /**
     * Deletes a GridX TrackingNumber resources.
     *
     * @return \GridX\Http\Resources\TrackingNumberCollection
     */
    public function delete($id, Request $request)
    {
        // find for the driver
        try {
            $trackingNumber = TrackingNumber::findTrackingOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'TrackingNumber resource not found.',
                ],
                404
            );
        }

        // delete the trackingNumber
        $trackingNumber->delete();

        // response the trackingNumber resource
        return new DeletedResource($trackingNumber);
    }

    /**
     * Take the uuid value of an entity QR code and return the object.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function fromQR(DecodeTrackingNumberQR $request)
    {
        // validate request inputs
        $code = $request->input('code');

        // get the model of from the code
        $model = Utils::findModel(['entities', 'orders'], ['uuid' => $code]);

        // if no model response with error
        if (!$model) {
            return response()->json(
                [
                    'error' => 'Unable to find QR code value',
                ],
                400
            );
        }

        // get the model class name
        $modelType         = class_basename($model);
        $resourceNamespace = '\\GridX\\Http\\Resources\\v1\\' . $modelType;

        return new $resourceNamespace($model);
    }
}
