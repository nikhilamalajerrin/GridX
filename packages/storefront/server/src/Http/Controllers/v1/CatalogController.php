<?php

namespace GridX\Storefront\Http\Controllers\v1;

use GridX\Http\Controllers\Controller;
use GridX\Storefront\Models\Catalog;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Query for Food Truck resources.
     *
     * @return \Illuminate\Http\Response
     */
    public function query(Request $request)
    {
        $limit   = $request->input('limit', false);
        $offset  = $request->input('offset', false);
        $results = [];

        if (session('storefront_store')) {
            $results = Catalog::queryWithRequestCached($request, function (&$query) use ($limit, $offset) {
                $query->where('subject_uuid', session('storefront_store'));

                if ($limit) {
                    $query->limit($limit);
                }

                if ($offset) {
                    $query->offset($offset);
                }
            });
        }

        return $results;
    }
}
