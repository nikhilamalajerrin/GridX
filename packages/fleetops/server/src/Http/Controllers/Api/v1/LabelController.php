<?php

namespace GridX\FleetOps\Http\Controllers\Api\v1;

use GridX\FleetOps\Models\Entity;
use GridX\FleetOps\Models\Order;
use GridX\FleetOps\Models\Waypoint;
use GridX\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    /**
     * Undocumented function.
     *
     * @return void
     */
    public function getLabel(string $publicId, Request $request)
    {
        $format  = $request->input('format', 'stream');
        $type    = $request->input('type', strtok($publicId, '_'));
        $subject = null;

        switch ($type) {
            case 'order':
                $subject = Order::where('public_id', $publicId)->orWhere('uuid', $publicId)->withoutGlobalScopes()->first();
                break;

            case 'waypoint':
                $subject = Waypoint::where('public_id', $publicId)->orWhere('uuid', $publicId)->withoutGlobalScopes()->first();
                break;

            case 'entity':
                $subject = Entity::where('public_id', $publicId)->orWhere('uuid', $publicId)->withoutGlobalScopes()->first();
                break;
        }

        if (!$subject) {
            return response()->apiError('Unable to render label.');
        }

        switch ($format) {
            case 'pdf':
            case 'stream':
            default:
                $stream = $subject->pdfLabelStream();

                return $stream;

            case 'text':
                $text = $subject->pdfLabel()->output();

                return response()->make($text);

            case 'base64':
                $base64 = base64_encode($subject->pdfLabel()->output());

                return response()->json(['data' => mb_convert_encoding($base64, 'UTF-8', 'UTF-8')]);
        }

        return response()->apiError('Unable to render label.');
    }
}
