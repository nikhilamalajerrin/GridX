<?php

namespace GridX\CustomerPortal\Http\Controllers\Internal\v1;

use GridX\CustomerPortal\Services\PortalAccountResolver;
use GridX\CustomerPortal\Services\PortalOrderService;
use GridX\Http\Controllers\Controller;
use GridX\Models\File;

class DocumentController extends Controller
{
    public function __construct(
        protected PortalAccountResolver $accountResolver,
        protected PortalOrderService $orderService,
    ) {
    }

    public function documents()
    {
        $context      = $this->accountResolver->resolve();
        $orders       = $this->orderService->queryForAccount($context)->with('trackingNumber')->get();
        $ordersByUuid = $orders->keyBy('uuid');
        $orderIds     = $ordersByUuid->keys()->toArray();

        $documents = File::where('company_uuid', session('company'))
            ->where(function ($query) use ($context, $orderIds) {
                $query->where('subject_uuid', $context['account']->uuid);
                if ($orderIds) {
                    $query->orWhereIn('subject_uuid', $orderIds);
                }
            })
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (File $document) use ($ordersByUuid) {
                $data  = $document->toArray();
                $order = $ordersByUuid->get($document->subject_uuid);

                if ($order) {
                    $data['order_uuid']            = $order->uuid;
                    $data['order_public_id']       = $order->public_id;
                    $data['order_tracking_number'] = $order->trackingNumber?->tracking_number;
                }

                return $data;
            });

        return response()->json(['documents' => $documents]);
    }
}
