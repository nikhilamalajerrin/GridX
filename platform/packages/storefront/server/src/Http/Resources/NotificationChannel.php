<?php

namespace GridX\Storefront\Http\Resources;

use GridX\Http\Resources\GridXResource;
use GridX\Support\Http;

class NotificationChannel extends GridXResource
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
            'id'                    => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'                  => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'             => $this->when(Http::isInternalRequest(), $this->public_id),
            'company_uuid'          => $this->when(Http::isInternalRequest(), $this->company_uuid),
            'created_by_uuid'       => $this->when(Http::isInternalRequest(), $this->created_by_uuid),
            'certificate_uuid'      => $this->when(Http::isInternalRequest(), $this->certificate_uuid),
            'owner_uuid'            => $this->when(Http::isInternalRequest(), $this->owner_uuid),
            'owner_type'            => $this->when(Http::isInternalRequest(), $this->owner_type),
            'name'                  => $this->name,
            'scheme'                => $this->scheme,
            'options'               => $this->options,
            'config'                => $this->config,
            'app_key'               => $this->app_key,
            'is_apn_gateway'        => $this->is_apn_gateway,
            'is_fcm_gateway'        => $this->is_fcm_gateway,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
