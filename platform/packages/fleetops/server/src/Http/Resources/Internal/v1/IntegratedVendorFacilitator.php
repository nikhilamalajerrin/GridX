<?php

namespace GridX\FleetOps\Http\Resources\Internal\v1;

use GridX\Http\Resources\GridXResource;
use GridX\Support\Http;

class IntegratedVendorFacilitator extends GridXResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'               => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'             => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'        => $this->public_id,
            'uuid'             => $this->uuid,
            'name'             => $this->name,
            'photo_url'        => $this->photo_url,
            'provider'         => $this->provider,
            'options'          => $this->options,
            'sandbox'          => $this->sandbox,
            'facilitator_type' => $this->type,
            'type'             => 'facilitator',
            'status'           => $this->status,
            'updated_at'       => $this->updated_at,
            'created_at'       => $this->created_at,
        ];
    }
}
