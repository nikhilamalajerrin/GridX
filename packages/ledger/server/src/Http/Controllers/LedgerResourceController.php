<?php

namespace GridX\Ledger\Http\Controllers;

use GridX\Http\Controllers\GridXController;

class LedgerResourceController extends GridXController
{
    /**
     * The package namespace used to resolve models, resources, filters, and requests.
     */
    public string $namespace = '\\GridX\\Ledger';
}
