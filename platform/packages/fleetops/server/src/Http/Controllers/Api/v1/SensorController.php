<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\FleetOps\Http\Controllers\Api\v1\Concerns\ResolvesFleetOpsApiResources;
use GridX\FleetOps\Http\Requests\CreateSensorRequest;
use GridX\FleetOps\Http\Requests\UpdateSensorRequest;
use GridX\FleetOps\Http\Resources\v1\DeletedResource;
use GridX\FleetOps\Http\Resources\v1\Sensor as SensorResource;
use GridX\FleetOps\Models\Device;
use GridX\FleetOps\Models\Sensor;
use GridX\FleetOps\Models\Telematic;
use GridX\FleetOps\Models\Warranty;
use GridX\FleetOps\Support\Utils;
use GridX\Http\Controllers\Controller;
use GridX\LaravelMysqlSpatial\Types\Point;
use GridX\Models\File;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    use ResolvesFleetOpsApiResources;

    public function create(CreateSensorRequest $request)
    {
        $this->rejectUuidIdentifiers($request);

        $input                 = $this->input($request);
        $input['company_uuid'] = session('company');

        $sensor = Sensor::create($input)->load(['telematic', 'device', 'warranty', 'photo', 'sensorable']);

        return new SensorResource($sensor);
    }

    public function update(string $id, UpdateSensorRequest $request)
    {
        $this->rejectUuidIdentifiers($request);

        try {
            $sensor = $this->resolveModel(Sensor::class, $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(['error' => 'Sensor resource not found.'], 404);
        }

        $sensor->update($this->input($request));

        return new SensorResource($sensor->refresh()->load(['telematic', 'device', 'warranty', 'photo', 'sensorable']));
    }

    public function query(Request $request)
    {
        $this->rejectUuidIdentifiers($request);

        $results = Sensor::queryWithRequest($request, function (&$query) {
            $query->with(['telematic', 'device', 'warranty', 'photo', 'sensorable']);
        });

        return SensorResource::collection($results);
    }

    public function find(string $id)
    {
        try {
            $sensor = $this->resolveModel(Sensor::class, $id)->load(['telematic', 'device', 'warranty', 'photo', 'sensorable']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(['error' => 'Sensor resource not found.'], 404);
        }

        return new SensorResource($sensor);
    }

    public function delete(string $id)
    {
        try {
            $sensor = $this->resolveModel(Sensor::class, $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json(['error' => 'Sensor resource not found.'], 404);
        }

        $sensor->delete();

        return new DeletedResource($sensor);
    }

    protected function input(Request $request): array
    {
        $input = $request->only([
            'name',
            'type',
            'internal_id',
            'imei',
            'imsi',
            'firmware_version',
            'serial_number',
            'unit',
            'min_threshold',
            'max_threshold',
            'threshold_inclusive',
            'last_reading_at',
            'last_value',
            'calibration',
            'report_frequency_sec',
            'status',
            'meta',
        ]);

        if ($request->filled(['latitude', 'longitude'])) {
            $input['last_position'] = new Point($request->input('latitude'), $request->input('longitude'));
        } elseif ($request->filled('last_position')) {
            $input['last_position'] = Utils::getPointFromCoordinates($request->input('last_position'));
        }

        $this->applyPublicIdRelation($input, 'device', 'device_uuid', Device::class, $request);
        $this->applyPublicIdRelation($input, 'telematic', 'telematic_uuid', Telematic::class, $request);
        $this->applyPublicIdRelation($input, 'warranty', 'warranty_uuid', Warranty::class, $request);
        $this->applyPublicIdRelation($input, 'photo', 'photo_uuid', File::class, $request);

        if ($request->exists('sensorable')) {
            if (blank($request->input('sensorable'))) {
                $input['sensorable_type'] = null;
                $input['sensorable_uuid'] = null;
            } else {
                [$type, $uuid]            = $this->resolveMorph($request->input('sensorable_type'), $request->input('sensorable'));
                $input['sensorable_type'] = $type;
                $input['sensorable_uuid'] = $uuid;
            }
        }

        return $input;
    }
}
