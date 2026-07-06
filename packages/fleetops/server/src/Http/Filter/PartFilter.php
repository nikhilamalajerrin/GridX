<?php

namespace GridX\FleetOps\Http\Filter;

use GridX\FleetOps\Models\Vendor;
use GridX\Http\Filter\Filter;
use GridX\Support\Http;

class PartFilter extends Filter
{
    public function queryForInternal()
    {
        $this->builder->where('company_uuid', $this->session->get('company'));
    }

    public function queryForPublic()
    {
        $this->queryForInternal();
    }

    public function query(?string $searchQuery)
    {
        $this->builder->search($searchQuery);
    }

    public function vendor(?string $vendor)
    {
        if (!$vendor) {
            return;
        }

        $this->builder->whereIn('vendor_uuid', Vendor::query()
            ->where('company_uuid', $this->session->get('company'))
            ->where(function ($query) use ($vendor) {
                $query->where('public_id', $vendor);

                if (in_array('internal_id', (new Vendor())->getFillable())) {
                    $query->orWhere('internal_id', $vendor);
                }

                if (Http::isInternalRequest($this->request)) {
                    $query->orWhere('uuid', $vendor);
                }
            })
            ->pluck('uuid'));
    }
}
