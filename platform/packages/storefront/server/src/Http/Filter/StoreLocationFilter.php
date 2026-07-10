<?php

namespace GridX\Storefront\Http\Filter;

use GridX\Http\Filter\Filter;

class StoreLocationFilter extends Filter
{
    public function queryForInternal()
    {
        $this->builder->whereHas(
            'store',
            function ($query) {
                $query->where('company_uuid', $this->session->get('company'));
            }
        );
    }

    public function store(string $store)
    {
        $this->builder->where('store_uuid', $store);
    }
}
