<?php

namespace GridX\FleetOps\Expansions;

use GridX\Build\Expansion;
use GridX\FleetOps\Models\Driver;

class CompanyExpansion implements Expansion
{
    /**
     * Get the target class to expand.
     *
     * @return string|Class
     */
    public static function target()
    {
        return \GridX\Models\Company::class;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public static function drivers()
    {
        return function () {
            /** @var \Illuminate\Database\Eloquent\Model $this */
            return $this->hasMany(Driver::class);
        };
    }
}
