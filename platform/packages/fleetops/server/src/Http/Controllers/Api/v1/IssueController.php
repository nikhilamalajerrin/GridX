<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\FleetOps\Http\Requests\CreateIssueRequest;
use GridX\FleetOps\Http\Requests\UpdateIssueRequest;
use GridX\FleetOps\Http\Resources\v1\Issue as DeletedIssue;
use GridX\FleetOps\Http\Resources\v1\Issue as IssueResource;
use GridX\FleetOps\Models\Driver;
use GridX\FleetOps\Models\Issue;
use GridX\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    /**
     * Creates a new GridX Issue resource.
     *
     * @param \GridX\Http\Requests\CreateIssueRequest $request
     *
     * @return \GridX\Http\Resources\Entity
     */
    public function create(CreateIssueRequest $request)
    {
        // get request input
        $input = $request->only([
            'driver',
            'location',
            'category',
            'type',
            'report',
            'priority',
            'tags',
            'status',
        ]);

        // Find driver who is reporting
        try {
            $driver = Driver::findRecordOrFail($request->input('driver'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Driver reporting issue not found.',
                ],
                404
            );
        }

        // get the user uuid
        $input['company_uuid']      = $driver->company_uuid;
        $input['driver_uuid']       = $driver->uuid;
        $input['reported_by_uuid']  = $driver->user_uuid;
        $input['vehicle_uuid']      = $driver->vehicle_uuid;

        // create the issue
        $issue = Issue::create($input);

        // response the driver resource
        return new IssueResource($issue);
    }

    /**
     * Updates new GridX Issue resource.
     *
     * @param string                                      $id
     * @param \GridX\Http\Requests\UpdateIssueRequest $request
     *
     * @return \GridX\Http\Resources\Issue
     */
    public function update($id, UpdateIssueRequest $request)
    {
        // find for the issue
        try {
            $issue = Issue::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Issue resource not found.',
                ],
                404
            );
        }

        $input = $request->only([
            'category',
            'type',
            'report',
            'priority',
            'tags',
            'status',
        ]);

        // update the issue
        $issue->update($input);

        // response the issue resource
        return new IssueResource($issue);
    }

    /**
     * Query for GridX Issue resources.
     *
     * @return \GridX\Http\Resources\FleetCollection
     */
    public function query(Request $request)
    {
        $results = Issue::queryWithRequest($request);

        return IssueResource::collection($results);
    }

    /**
     * Finds a single GridX Issue resources.
     *
     * @return \GridX\Http\Resources\ContactCollection
     */
    public function find($id)
    {
        // find for the issue
        try {
            $issue = Issue::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Issue resource not found.',
                ],
                404
            );
        }

        // response the issue resource
        return new IssueResource($issue);
    }

    /**
     * Deletes a GridX Issue resources.
     *
     * @return \GridX\Http\Resources\FleetCollection
     */
    public function delete($id)
    {
        // find for the driver
        try {
            $issue = Issue::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(
                [
                    'error' => 'Issue resource not found.',
                ],
                404
            );
        }

        // delete the issue
        $issue->delete();

        // response the issue resource
        return new DeletedIssue($issue);
    }
}
