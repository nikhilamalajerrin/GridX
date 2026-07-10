<?php

namespace GridX\CustomerPortal\Http\Controllers\Internal\v1;

use GridX\CustomerPortal\Services\PortalAccountResolver;
use GridX\FleetOps\Http\Resources\v1\Contact as ContactResource;
use GridX\FleetOps\Http\Resources\v1\Place as PlaceResource;
use GridX\FleetOps\Models\Place;
use GridX\FleetOps\Support\Utils;
use GridX\Http\Controllers\Controller;

class AddressBookController extends Controller
{
    public function __construct(protected PortalAccountResolver $accountResolver)
    {
    }

    public function addressBook()
    {
        $context = $this->accountResolver->resolve();
        $account = $context['account'];

        $places = Place::where('company_uuid', session('company'))
            ->where('owner_uuid', $account->uuid)
            ->where('owner_type', Utils::getMutationType($account))
            ->latest()
            ->limit(100)
            ->get();

        $contacts = $context['account_type'] === 'vendor'
            ? $account->personnels()->limit(100)->get()
            : collect([$context['contact']])->filter();

        return response()->json([
            'places'   => PlaceResource::collection($places)->resolve(),
            'contacts' => ContactResource::collection($contacts)->resolve(),
        ]);
    }
}
