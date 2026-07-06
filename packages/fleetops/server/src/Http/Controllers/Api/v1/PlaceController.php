<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\FleetOps\Http\Requests\CreatePlaceRequest;
use GridX\FleetOps\Http\Requests\UpdatePlaceRequest;
use GridX\FleetOps\Http\Resources\v1\DeletedResource;
use GridX\FleetOps\Http\Resources\v1\Place as PlaceResource;
use GridX\FleetOps\Models\Place;
use GridX\FleetOps\Support\PlaceSearch;
use GridX\FleetOps\Support\Utils;
use GridX\Http\Controllers\Controller;
use GridX\LaravelMysqlSpatial\Types\Point;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlaceController extends Controller
{
    /**
     * Creates a new GridX Place resource.
     *
     * @param \GridX\Http\Requests\CreatePlaceRequest $request
     *
     * @return \GridX\Http\Resources\Place
     */
    public function create(CreatePlaceRequest $request)
    {
        // get request input
        $input = $request->only([
            'name',
            'street1',
            'street2',
            'city',
            'location',
            'province',
            'postal_code',
            'neighborhood',
            'district',
            'building',
            'security_access_code',
            'country',
            'phone',
            'type',
            'meta',
        ]);

        // Check is missing key address attributes
        $isNotAddressObject = $request->isNotFilled(['name', 'location', 'latititude', 'longitude', 'city', 'province', 'postal_code']);

        // if address param is sent create from mixed
        if ($isNotAddressObject && $request->isString('address')) {
            $place = Place::createFromGeocodingLookup($request->input('address'));

            if ($place instanceof Place) {
                $input = $place->toArray();
            }
        }

        // if street1 is the only param
        if ($isNotAddressObject && $request->isString('street1')) {
            $place = Place::createFromGeocodingLookup($request->input('street1'));

            if ($place instanceof Place) {
                $input = $place->toArray();
            }
        }

        // if we have only latitude/longitude or location BUT no street1 do a reverse lookup
        $requestHasCoordinates = $request->filled(['latitude', 'longitude']);
        $requestHasLocation    = $request->filled(['location']);
        $requestMissingStreet  = $request->missing('street1');
        if ($requestMissingStreet && ($requestHasCoordinates || $requestHasLocation)) {
            if ($requestHasLocation) {
                $point = Utils::getPointFromMixed($request->input('location'));
            }

            if ($requestHasCoordinates) {
                $point = Utils::getPointFromMixed($request->only(['latitude', 'longitude']));
            }

            if ($point instanceof Point) {
                $place = Place::createFromReverseGeocodingLookup($point);

                if ($place instanceof Place) {
                    $input = $place->toArray();
                }
            }
        }

        // latitude / longitude
        if ($requestHasCoordinates) {
            $input['location'] = Utils::getPointFromCoordinates($request->only(['latitude', 'longitude']));
        } elseif ($requestHasLocation) {
            $input['location'] = Utils::getPointFromMixed($request->input('location'));
        }

        // make sure company is set
        $input['company_uuid'] = session('company');

        // owner assignment
        if ($request->has('owner')) {
            $id = $request->input('owner');

            // check if customer_ based contact
            if (Str::startsWith($id, 'customer')) {
                $id = Str::replaceFirst('customer', 'contact', $id);
            }

            $owner = Utils::getUuid(
                ['contacts', 'vendors'],
                [
                    'public_id'    => $id,
                    'company_uuid' => session('company'),
                ],
                [
                    'with_table' => true,
                ]
            );

            if (is_array($owner)) {
                $input['owner_uuid'] = Utils::get($owner, 'uuid');
                $input['owner_type'] = Utils::getModelClassName(Utils::get($owner, 'table'));
            }
        }

        /** @var \GridX\Models\Place */
        $place = Place::firstOrNew([
            'company_uuid' => session('company'),
            'owner_uuid'   => data_get($input, 'owner_uuid'),
            'name'         => strtoupper(Utils::or($input, ['name', 'street1'])),
            'street1'      => strtoupper($input['street1']),
        ]);

        // check if missing location
        // set a default location for creation
        $isMissingLocation = empty($input['location']);
        if ($isMissingLocation) {
            $input['location'] = new Point(0, 0);
        }

        // fill place with attributes
        $place->fill($input);

        // attempt to find and set latitude and longitude
        if ($isMissingLocation && $request->missing(['latitude', 'longitude', 'location'])) {
            $geocoded = Geocoder::geocode($place->toAddressString(['name']))
                ->get()
                ->first();

            if ($geocoded) {
                $place->fillWithGoogleAddress($geocoded);
            } elseif (isset($place->location)) {
                $place->location = new Point(0, 0);
            }
        }

        // Save place
        $place->save();

        return new PlaceResource($place);
    }

    /**
     * Updates a GridX Place resource.
     *
     * @param string                                      $id
     * @param \GridX\Http\Requests\UpdatePlaceRequest $request
     *
     * @return \GridX\Http\Resources\Place
     */
    public function update($id, UpdatePlaceRequest $request)
    {
        try {
            $place = Place::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->apiError('Place resource not found.');
        }

        // get request input
        $input = $request->only([
            'name',
            'street1',
            'street2',
            'city',
            'location',
            'province',
            'postal_code',
            'neighborhood',
            'district',
            'building',
            'security_access_code',
            'country',
            'phone',
            'type',
            'meta',
        ]);

        // latitude / longitude
        if ($request->has(['latitude', 'longitude'])) {
            $input['location'] = Utils::getPointFromCoordinates($request->only(['latitude', 'longitude']));
        }

        // owner assignment
        if ($request->has('owner')) {
            $owner = $request->input('owner');

            // Handle if owner is an object
            if (is_array($owner) || is_object($owner)) {
                $id = data_get($owner, 'id', data_get($owner, 'customer_id'));
            } elseif (is_string($owner)) {
                $id = $owner;
            }

            if ($id) {
                // check if customer_ based contact
                if (Str::startsWith($id, 'customer')) {
                    $id = Str::replaceFirst('customer', 'contact', $id);
                }

                $owner = Utils::getUuid(
                    ['contacts', 'vendors'],
                    [
                        'public_id'    => $id,
                        'company_uuid' => session('company'),
                    ],
                    [
                        'with_table' => true,
                    ]
                );

                if (is_array($owner)) {
                    $input['owner_uuid'] = Utils::get($owner, 'uuid');
                    $input['owner_type'] = Utils::getModelClassName(Utils::get($owner, 'table'));
                }
            }
        }

        // vendor assignment
        if ($request->has('vendor')) {
            $input['vendor_uuid'] = Utils::getUuid('vendors', [
                'public_id'    => $request->input('vendor'),
                'company_uuid' => session('company'),
            ]);
        }

        // update the place
        $place->update($input);
        $place->flushAttributesCache();

        return new PlaceResource($place);
    }

    /**
     * Query for GridX Place resources.
     *
     * @return \GridX\Http\Resources\PlaceCollection
     */
    public function query(Request $request)
    {
        $results = Place::queryWithRequest($request, function (&$query, $request) {
            if ($request->has('vendor')) {
                $query->whereHas('vendor', function ($q) use ($request) {
                    $q->where('public_id', $request->input('vendor'));
                });
            }
        });

        $results = $results->all();

        return PlaceResource::collection($results);
    }

    /**
     * Search for GridX Place resources.
     *
     * @return \GridX\Http\Resources\PlaceCollection
     */
    public function search(Request $request)
    {
        $searchQuery = strtolower($request->input('query'));
        $limit       = $request->input('limit', 10);
        $geo         = $request->boolean('geo');
        $latitude    = $request->input('latitude', false);
        $longitude   = $request->input('longitude', false);

        $query = Place::where('company_uuid', session('company'))->whereNull('deleted_at');

        $results = PlaceSearch::search($query, $searchQuery, [
            'limit'          => $limit,
            'geo'            => $geo,
            'latitude'       => $latitude,
            'longitude'      => $longitude,
            'no_query_order' => 'name_desc',
        ]);

        return PlaceResource::collection($results);
    }

    /**
     * Finds a single GridX Place resources.
     *
     * @return \GridX\Http\Resources\Place
     */
    public function find($id, Request $request)
    {
        // find for the place
        try {
            $place = Place::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->apiError('Place resource not found.');
        }

        return new PlaceResource($place);
    }

    /**
     * Deletes a GridX Place resources.
     *
     * @return \GridX\Http\Resources\Place
     */
    public function delete($id, Request $request)
    {
        try {
            $place = Place::findRecordOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->apiError('Place resource not found.');
        }

        $place->delete();

        return new DeletedResource($place);
    }
}
