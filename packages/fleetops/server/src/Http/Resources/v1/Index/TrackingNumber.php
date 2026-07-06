<?php

namespace GridX\FleetOps\Http\Resources\v1\Index;

use GridX\Http\Resources\GridXResource;

class TrackingNumber extends GridXResource
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
            'id'              => $this->when($this->id, $this->id),
            'uuid'            => $this->when($this->uuid, $this->uuid),
            'tracking_number' => $this->when($this->tracking_number, $this->tracking_number),
            'qr_code'         => $this->when($this->qr_code, $this->qr_code),
        ];
    }
}
