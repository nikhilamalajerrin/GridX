<?php

namespace GridX\Storefront\Observers;

use GridX\Models\Company;
use GridX\Storefront\Support\Storefront;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     *
     * @return void
     */
    public function created(Company $company)
    {
        // Add the default storefront order config
        Storefront::createStorefrontConfig($company);
    }
}
