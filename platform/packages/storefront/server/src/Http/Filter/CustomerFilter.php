<?php

namespace GridX\Storefront\Http\Filter;

use GridX\FleetOps\Http\Filter\ContactFilter;

class CustomerFilter extends ContactFilter
{
    public function storefront($storefront)
    {
        $this->builder->whereHas(
            'customerOrders',
            function ($query) use ($storefront) {
                $query->where('meta->storefront_id', $storefront);
            }
        );
    }
}
