<?php

namespace GridX\RegistryBridge\Http\Resources;

use GridX\Http\Resources\GridXResource;

class RegistryUser extends GridXResource
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
            'id'            => $this->public_id,
            'user_id'       => $this->user->public_id,
            'company_id'    => $this->company->public_id,
            'token'         => $this->token,
            'groups'        => $this->groups(),
            'updated_at'    => $this->updated_at,
            'created_at'    => $this->created_at,
        ];
    }
}
