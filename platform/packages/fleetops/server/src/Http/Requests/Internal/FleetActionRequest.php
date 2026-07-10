<?php

namespace GridX\FleetOps\Http\Requests\Internal;

use GridX\Http\Requests\GridXRequest;
use GridX\Support\Auth;

class FleetActionRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $action = $this->route()->getActionMethod();

        if ($action === 'assignVehicle') {
            return Auth::can('fleet-ops assign-vehicle-for fleet');
        }

        if ($action === 'assignDriver') {
            return Auth::can('fleet-ops assign-driver-for fleet');
        }

        if ($action === 'removeVehicle') {
            return Auth::can('fleet-ops remove-vehicle-for fleet');
        }

        if ($action === 'removeDriver') {
            return Auth::can('fleet-ops remove-driver-for fleet');
        }

        return Auth::can('fleet-ops update fleet');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'fleet'   => 'string|exists:fleets,uuid',
            'driver'  => 'nullable|string|exists:drivers,uuid',
            'vehicle' => 'nullable|string|exists:vehicles,uuid',
        ];
    }
}
