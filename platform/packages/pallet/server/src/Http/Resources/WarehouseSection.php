<?php

namespace GridX\Pallet\Http\Resources;

use GridX\Http\Resources\GridXResource;
use GridX\Support\Http;

class WarehouseSection extends GridXResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                   => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'                 => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'            => $this->when(Http::isInternalRequest(), $this->public_id),
            'name'                 => $this->name,
            'description'          => $this->description,
            'aisles'               => WarehouseAisle::collection($this->aisles),
            'updated_at'           => $this->updated_at,
            'created_at'           => $this->created_at,
        ];
    }
}
