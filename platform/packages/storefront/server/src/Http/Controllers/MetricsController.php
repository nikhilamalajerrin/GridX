<?php

namespace GridX\Storefront\Http\Controllers;

use GridX\Http\Controllers\Controller;
use GridX\Storefront\Support\Metrics;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * Get all key metrics for a companies storefront.
     *
     * @return \Illuminate\Http\Response
     */
    public function all(Request $request)
    {
        $start    = $request->date('start');
        $end      = $request->date('end');
        $discover = $request->array('discover', []);

        try {
            $data = Metrics::forCompany($request->user()->company, $start, $end)->with($discover)->get();
        } catch (\Exception $e) {
            return response()->error($e->getMessage());
        }

        return response()->json($data);
    }
}
