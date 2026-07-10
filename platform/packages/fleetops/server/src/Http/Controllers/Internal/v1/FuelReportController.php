<?php

namespace GridX\FleetOps\Http\Controllers\Internal\v1;

use GridX\FleetOps\Exports\FuelReportExport;
use GridX\FleetOps\Http\Controllers\FleetOpsController;
use GridX\FleetOps\Imports\FuelReportImport;
use GridX\FleetOps\Models\FuelReport;
use GridX\Http\Requests\ExportRequest;
use GridX\Http\Requests\ImportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class FuelReportController extends FleetOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'fuel_report';

    /**
     * Handle post save transactions.
     */
    public function afterSave(Request $request, FuelReport $fuelReport)
    {
        $customFieldValues = $request->array('fuel_report.custom_field_values');
        if ($customFieldValues) {
            $fuelReport->syncCustomFieldValues($customFieldValues);
        }
    }

    /**
     * Export the fleets to excel or csv.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(ExportRequest $request)
    {
        $format       = $request->input('format', 'xlsx');
        $selections   = $request->array('selections');
        $fileName     = trim(Str::slug('fuel_report-' . date('Y-m-d-H:i')) . '.' . $format);

        return Excel::download(new FuelReportExport($selections), $fileName);
    }

    public function import(ImportRequest $request)
    {
        $disk           = $request->input('disk', config('filesystems.default'));
        $files          = $request->resolveFilesFromIds();
        $importedCount  = 0;

        foreach ($files as $file) {
            try {
                $import = new FuelReportImport();
                Excel::import($import, $file->path, $disk);
                $importedCount += $import->imported;
            } catch (\Throwable $e) {
                return response()->error('Invalid file, unable to proccess.');
            }
        }

        return response()->json(['status' => 'ok', 'message' => 'Import completed', 'imported' => $importedCount]);
    }
}
