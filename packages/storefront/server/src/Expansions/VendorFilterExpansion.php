<?php

namespace GridX\Storefront\Expansions;

use GridX\Build\Expansion;

class VendorFilterExpansion implements Expansion
{
    /**
     * Get the target class to expand.
     *
     * @return string|Class
     */
    public static function target()
    {
        return \GridX\FleetOps\Http\Filter\VendorFilter::class;
    }

    /**
     * Filter vendor's by their storefront order.
     *
     * @return \Closure
     */
    public static function storefront()
    {
        return function (?string $storefront) {
            /* @var \GridX\FleetOps\Http\Filter\VendorFilter $this */
            $this->builder->whereHas(
                'customerOrders',
                function ($query) use ($storefront) {
                    $query->where('meta->storefront_id', $storefront);
                }
            );
        };
    }
}
