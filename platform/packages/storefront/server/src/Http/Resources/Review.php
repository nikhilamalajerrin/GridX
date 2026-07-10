<?php

namespace GridX\Storefront\Http\Resources;

use GridX\Http\Resources\GridXResource;
use GridX\Support\Http;

class Review extends GridXResource
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
            'id'         => $this->when(Http::isInternalRequest(), $this->id, $this->public_id),
            'uuid'       => $this->when(Http::isInternalRequest(), $this->uuid),
            'public_id'  => $this->when(Http::isInternalRequest(), $this->public_id),
            'subject_id' => $this->subject->id,
            'rating'     => $this->rating,
            'content'    => $this->content,
            'customer'   => new ReviewCustomer($this->customer),
            'slug'       => $this->slug,
            'photos'     => $this->mapPhotos($this->photos),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function mapPhotos($photos = [])
    {
        return collect($photos)->map(function ($photo) {
            return [
                'id'       => $photo->public_id,
                'filename' => $photo->original_filename,
                'type'     => $photo->content_type,
                'caption'  => $photo->caption,
                'url'      => $photo->url,
            ];
        });
    }
}
