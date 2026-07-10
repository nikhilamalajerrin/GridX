<?php

namespace GridX\FleetOps\Http\Controllers\Internal\v1;

use GridX\FleetOps\Exports\SensorExport;
use GridX\FleetOps\Http\Controllers\FleetOpsController;
use GridX\Http\Requests\ExportRequest;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SensorController extends FleetOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'sensor';

    /**
     * Export sensors to excel or csv.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(ExportRequest $request)
    {
        $format     = $request->input('format', 'xlsx');
        $selections = $request->array('selections');
        $fileName   = trim(Str::slug('sensors-' . date('Y-m-d-H:i')) . '.' . $format);

        return Excel::download(new SensorExport($selections), $fileName);
    }

    /**
     * Query callback when querying record.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param Request                            $request
     */
    public static function onQueryRecord($query, $request): void
    {
        $query->with(['telematic', 'device', 'warranty']);
    }
}
