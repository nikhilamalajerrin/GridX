<?php

namespace GridX\FleetOps\Http\Controllers\Internal\v1;

use GridX\FleetOps\Exports\EquipmentExport;
use GridX\FleetOps\Http\Controllers\FleetOpsController;
use GridX\FleetOps\Imports\EquipmentImport;
use GridX\Http\Requests\ExportRequest;
use GridX\Http\Requests\ImportRequest;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class EquipmentController extends FleetOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'equipment';

    /**
     * Export equipment to excel or csv.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(ExportRequest $request)
    {
        $format     = $request->input('format', 'xlsx');
        $selections = $request->array('selections');
        $fileName   = trim(Str::slug('equipment-' . date('Y-m-d-H:i')) . '.' . $format);

        return Excel::download(new EquipmentExport($selections), $fileName);
    }

    /**
     * Process import files (excel, csv) into Equipment records.
     *
     * @return \Illuminate\Http\Response
     */
    public function import(ImportRequest $request)
    {
        $disk          = $request->input('disk', config('filesystems.default'));
        $files         = $request->resolveFilesFromIds();
        $importedCount = 0;

        foreach ($files as $file) {
            try {
                $import = new EquipmentImport();
                Excel::import($import, $file->path, $disk);
                $importedCount += $import->imported;
            } catch (\Throwable $e) {
                return response()->error('Invalid file, unable to process.');
            }
        }

        return response()->json(['status' => 'ok', 'message' => 'Import completed', 'imported' => $importedCount]);
    }
}
