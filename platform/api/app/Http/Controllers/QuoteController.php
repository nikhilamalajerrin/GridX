<?php

namespace App\Http\Controllers;

use App\Models\GridxAgentSession;
use App\Models\GridxQuote;
use Dompdf\Dompdf;
use Fleetbase\FleetOps\Casts\Point;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Booking -> quote -> customer approval -> sales order -> approval PDF ->
 * payment -> dispatch. Orders are never created directly from a booking
 * request anymore — a GridxQuote must reach status=paid before dispatch()
 * will create the live, dispatchable Order.
 *
 * Pricing is dynamic: base fee + per-km rate (by truck type) + a fuel
 * surcharge computed from (current diesel price - base diesel price) /
 * truck km-per-liter * distance, the standard industry fuel-surcharge
 * formula (same one the U.S. DOE/EIA-based carrier calculators use).
 */
class QuoteController extends Controller
{
    protected const RATE_CARD = [
        'base_fee'   => 250.0,
        'min_charge' => 550.0,
        'per_km'     => [
            'flatbed'     => 3.5,
            'curtainside' => 3.8,
            'box'         => 3.2,
            'reefer'      => 4.6,
            'tanker'      => 5.0,
            'lowbed'      => 6.0,
            'default'     => 3.5,
        ],
        'cross_border_surcharge' => 800.0,
        'vat_rate'               => 0.15,
    ];

    public function index(Request $request)
    {
        $companyUuid = Auth::user()?->company_uuid ?? session('company');
        $quotes      = GridxQuote::where('company_uuid', $companyUuid)->orderByDesc('created_at')->get();

        return response()->json(['quotes' => $quotes]);
    }

    public function show(string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();

        return response()->json(['quote' => $quote]);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'pickup_lat'          => 'required|numeric',
            'pickup_lng'          => 'required|numeric',
            'dropoff_lat'         => 'required|numeric',
            'dropoff_lng'         => 'required|numeric',
            'pickup_address'      => 'nullable|string',
            'dropoff_address'     => 'nullable|string',
            'truck_type'          => 'nullable|string',
            'cargo_weight_kg'     => 'nullable|numeric',
            'cross_border'        => 'nullable|boolean',
            'customer_contact_uuid' => 'nullable|string',
            'notes'               => 'nullable|string',
            'agent_session_id'    => 'nullable|string',
            'agent_reasoning'     => 'nullable|string',
        ]);

        $companyUuid = Auth::user()?->company_uuid ?? session('company');
        if (!$companyUuid) {
            return response()->json(['error' => 'No company context for this session.'], 422);
        }

        $distanceKm = $this->routeDistanceKm(
            $data['pickup_lat'],
            $data['pickup_lng'],
            $data['dropoff_lat'],
            $data['dropoff_lng']
        );

        $pricing = $this->calculatePricing($distanceKm, $data['truck_type'] ?? 'default', $data['cross_border'] ?? false);

        $agentSessionUuid = null;
        if (!empty($data['agent_session_id'])) {
            $agentSessionUuid = GridxAgentSession::where('public_id', $data['agent_session_id'])
                ->orWhere('uuid', $data['agent_session_id'])
                ->value('uuid');
        }

        $quote = new GridxQuote();
        $quote->forceFill([
            'uuid'                  => (string) Str::uuid(),
            'company_uuid'          => $companyUuid,
            'customer_contact_uuid' => $data['customer_contact_uuid'] ?? null,
            'agent_session_uuid'    => $agentSessionUuid,
            'agent_reasoning'       => $data['agent_reasoning'] ?? null,
            'pickup_address'        => $data['pickup_address'] ?? null,
            'pickup_lat'            => $data['pickup_lat'],
            'pickup_lng'            => $data['pickup_lng'],
            'dropoff_address'       => $data['dropoff_address'] ?? null,
            'dropoff_lat'           => $data['dropoff_lat'],
            'dropoff_lng'           => $data['dropoff_lng'],
            'distance_km'           => $distanceKm,
            'truck_type'            => $data['truck_type'] ?? 'default',
            'cargo_weight_kg'       => $data['cargo_weight_kg'] ?? null,
            'notes'                 => $data['notes'] ?? null,
            'created_by_uuid'       => Auth::user()?->uuid,
            'status'                => 'quote_pending',
            ...$pricing,
        ]);
        $quote->save();

        return response()->json(['quote' => $quote], 201);
    }

    /**
     * Edit a quote's route/cargo details and recompute pricing. Only allowed
     * while status=quote_pending — once approved, the PDF and price are a
     * fixed snapshot the customer has seen, so editing in place would be
     * misleading. Reject the quote and create a new one instead at that point.
     */
    public function update(Request $request, string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();

        if ($quote->status !== 'quote_pending') {
            return response()->json(['error' => 'Only quotes still in quote_pending status can be edited. Reject and create a new quote instead.'], 422);
        }

        $data = $request->validate([
            'pickup_lat'      => 'sometimes|numeric',
            'pickup_lng'      => 'sometimes|numeric',
            'dropoff_lat'     => 'sometimes|numeric',
            'dropoff_lng'     => 'sometimes|numeric',
            'pickup_address'  => 'nullable|string',
            'dropoff_address' => 'nullable|string',
            'truck_type'      => 'nullable|string',
            'cargo_weight_kg' => 'nullable|numeric',
            'cross_border'    => 'nullable|boolean',
            'notes'           => 'nullable|string',
        ]);

        $pickupLat  = $data['pickup_lat'] ?? $quote->pickup_lat;
        $pickupLng  = $data['pickup_lng'] ?? $quote->pickup_lng;
        $dropoffLat = $data['dropoff_lat'] ?? $quote->dropoff_lat;
        $dropoffLng = $data['dropoff_lng'] ?? $quote->dropoff_lng;
        $truckType  = $data['truck_type'] ?? $quote->truck_type;
        $crossBorder = $data['cross_border'] ?? false;

        $distanceKm = $this->routeDistanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $pricing    = $this->calculatePricing($distanceKm, $truckType, $crossBorder);

        $quote->update([
            'pickup_address'  => $data['pickup_address'] ?? $quote->pickup_address,
            'pickup_lat'      => $pickupLat,
            'pickup_lng'      => $pickupLng,
            'dropoff_address' => $data['dropoff_address'] ?? $quote->dropoff_address,
            'dropoff_lat'     => $dropoffLat,
            'dropoff_lng'     => $dropoffLng,
            'truck_type'      => $truckType,
            'cargo_weight_kg' => $data['cargo_weight_kg'] ?? $quote->cargo_weight_kg,
            'notes'           => $data['notes'] ?? $quote->notes,
            'distance_km'     => $distanceKm,
            ...$pricing,
        ]);

        return response()->json(['quote' => $quote->fresh()]);
    }

    public function send(string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();
        $quote->update(['status' => 'sent']);

        return response()->json(['quote' => $quote]);
    }

    public function approve(string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();

        $pdfPath = $this->generatePdf($quote);

        $quote->update([
            'status'      => 'sales_order',
            'approved_at' => now(),
            'pdf_path'    => $pdfPath,
        ]);

        return response()->json(['quote' => $quote->fresh()]);
    }

    public function reject(string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();
        $quote->update(['status' => 'rejected']);

        return response()->json(['quote' => $quote]);
    }

    public function markPaid(string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();

        if ($quote->status !== 'sales_order') {
            return response()->json(['error' => 'Quote must be an approved sales order before it can be marked paid.'], 422);
        }

        $quote->update(['status' => 'paid', 'paid_at' => now()]);

        return response()->json(['quote' => $quote->fresh()]);
    }

    /**
     * The gate: an Order is only ever created here, and only once paid.
     * Nothing upstream (WhatsApp agent, console) creates a dispatchable
     * Order directly anymore.
     */
    public function dispatchOrder(string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();

        if ($quote->status !== 'paid') {
            return response()->json(['error' => 'Quote must be paid before it can be dispatched.'], 422);
        }

        $order = $this->convertQuoteToOrder($quote);

        return response()->json(['quote' => $quote->fresh(), 'order_uuid' => $order->uuid]);
    }

    /**
     * Converts multiple paid quotes into unassigned Orders (status='created',
     * no vehicle/driver yet) in one go, WITHOUT running any route planning.
     * This is the entry point for multi-warehouse consolidated route planning:
     * once several quotes from different pickup locations exist as real Orders
     * with a Payload (pickup/dropoff Places), the existing Orchestrator
     * (operations/orchestrator) can multi-vehicle/multi-depot optimize them
     * together — that solver already exists, this just feeds it real data.
     */
    public function batchDispatch(Request $request)
    {
        $ids = $request->input('quote_ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['error' => 'quote_ids is required.'], 422);
        }

        $quotes  = GridxQuote::whereIn('public_id', $ids)->orWhere(fn ($q) => $q->whereIn('uuid', $ids))->get();
        $results = [];

        foreach ($quotes as $quote) {
            if ($quote->status !== 'paid') {
                $results[] = ['quote' => $quote->public_id, 'error' => 'not paid, skipped'];
                continue;
            }

            $order       = $this->convertQuoteToOrder($quote);
            $results[]   = [
                'quote'         => $quote->public_id,
                'order_uuid'    => $order->uuid,
                'order_id'      => $order->public_id,
                'pickup'        => ['name' => $quote->pickup_address, 'lat' => $quote->pickup_lat, 'lng' => $quote->pickup_lng],
                'dropoff'       => ['name' => $quote->dropoff_address, 'lat' => $quote->dropoff_lat, 'lng' => $quote->dropoff_lng],
            ];
        }

        return response()->json(['results' => $results]);
    }

    protected function convertQuoteToOrder(GridxQuote $quote): Order
    {
        $pickup = $this->findOrCreatePlace($quote->company_uuid, $quote->pickup_address, $quote->pickup_lat, $quote->pickup_lng);
        $dropoff = $this->findOrCreatePlace($quote->company_uuid, $quote->dropoff_address, $quote->dropoff_lat, $quote->dropoff_lng);

        $payload = new Payload();
        $payload->forceFill([
            'uuid'         => (string) Str::uuid(),
            'company_uuid' => $quote->company_uuid,
            'pickup_uuid'  => $pickup->uuid,
            'dropoff_uuid' => $dropoff->uuid,
            'type'         => 'transport',
            'meta'         => ['gridx_quote_uuid' => $quote->uuid],
        ]);
        $payload->save();

        $order = new Order();
        $order->forceFill([
            'uuid'                 => (string) Str::uuid(),
            'company_uuid'         => $quote->company_uuid,
            'payload_uuid'         => $payload->uuid,
            'type'                 => 'transport',
            'status'               => 'created',
            'notes'                => $quote->notes,
            'time_window_start'    => $quote->created_at,
            'meta'                 => ['gridx_quote_uuid' => $quote->uuid, 'gridx_quote_public_id' => $quote->public_id],
        ]);
        $order->save();

        $quote->update([
            'status'        => 'dispatched',
            'order_uuid'    => $order->uuid,
            'dispatched_at' => now(),
        ]);

        return $order;
    }

    protected function findOrCreatePlace(string $companyUuid, ?string $address, ?float $lat, ?float $lng): Place
    {
        $place = new Place();
        $place->forceFill([
            'uuid'         => (string) Str::uuid(),
            'company_uuid' => $companyUuid,
            'name'         => $address ?: 'Unnamed location',
            'street1'      => $address,
            'location'     => new Point($lat ?? 0, $lng ?? 0),
            'latitude'     => $lat,
            'longitude'    => $lng,
        ]);
        $place->save();

        return $place;
    }

    public function pdf(string $id)
    {
        $quote = GridxQuote::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();

        if (!$quote->pdf_path || !Storage::disk('local')->exists($quote->pdf_path)) {
            return response()->json(['error' => 'PDF not generated yet — approve the quote first.'], 404);
        }

        return response(Storage::disk('local')->get($quote->pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $quote->public_id . '.pdf"',
        ]);
    }

    // -- pricing -----------------------------------------------------------

    protected function calculatePricing(float $distanceKm, string $truckType, bool $crossBorder): array
    {
        $rateCard = self::RATE_CARD;
        $perKm    = $rateCard['per_km'][$truckType] ?? $rateCard['per_km']['default'];

        $baseFee      = $rateCard['base_fee'];
        $distanceCost = $distanceKm * $perKm;

        $currentDiesel = (float) env('DIESEL_PRICE_PER_LITER', 2.18); // SAR/liter
        $baseDiesel    = (float) env('DIESEL_BASE_PRICE_PER_LITER', 2.00); // SAR/liter, contract baseline
        $kmPerLiter    = (float) env('TRUCK_KM_PER_LITER', 3.0); // heavy-duty diesel truck average

        // Standard fuel-surcharge formula: (current - base) / efficiency * distance.
        // Floors at 0 — we don't give a discount if diesel got cheaper than baseline,
        // consistent with how carrier FSC tables are typically applied.
        $fuelSurcharge = max(0, ($currentDiesel - $baseDiesel) / $kmPerLiter) * $distanceKm;

        $crossBorderSurcharge = $crossBorder ? $rateCard['cross_border_surcharge'] : 0;

        $subtotal = max($baseFee + $distanceCost + $fuelSurcharge + $crossBorderSurcharge, $rateCard['min_charge']);
        $vat      = $subtotal * $rateCard['vat_rate'];
        $total    = $subtotal + $vat;

        return [
            'base_fee'                => round($baseFee, 2),
            'distance_cost'           => round($distanceCost, 2),
            'fuel_surcharge'          => round($fuelSurcharge, 2),
            'cross_border_surcharge'  => round($crossBorderSurcharge, 2),
            'subtotal'                => round($subtotal, 2),
            'vat'                     => round($vat, 2),
            'total'                   => round($total, 2),
            'currency'                => 'SAR',
            'diesel_price_used'       => $currentDiesel,
        ];
    }

    /**
     * Real road-following route geometry through an ordered list of stops —
     * used by the Route Planning map so truckers see the actual road path
     * (via self-hosted OSRM with real GCC road data), not a straight line
     * between pins. Separate from routeDistanceKm()/quote pricing, which
     * only needs a distance number and can tolerate the public OSRM demo
     * server's rate limits; this wants the more reliable self-hosted one.
     */
    public function routeGeometry(Request $request)
    {
        $points = $request->input('points', []);
        if (!is_array($points) || count($points) < 2) {
            return response()->json(['error' => 'At least 2 points are required.'], 422);
        }

        $osrmHost = env('GRIDX_OSRM_HOST', 'http://osrm:5000');
        $coords   = collect($points)->map(fn ($p) => "{$p[1]},{$p[0]}")->implode(';'); // OSRM wants lng,lat

        try {
            $response = Http::timeout(15)->get("{$osrmHost}/route/v1/driving/{$coords}", [
                'overview'   => 'full',
                'geometries' => 'geojson',
            ]);

            if ($response->ok()) {
                $coordinates = $response->json('routes.0.geometry.coordinates');
                if ($coordinates) {
                    // GeoJSON is [lng, lat] — flip to [lat, lng] for Leaflet.
                    $latLngs = array_map(fn ($c) => [$c[1], $c[0]], $coordinates);

                    return response()->json(['geometry' => $latLngs]);
                }
            }

            return response()->json(['error' => 'OSRM returned no route for these points.'], 502);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Routing service unavailable: ' . $e->getMessage()], 502);
        }
    }

    protected function routeDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $osrmHost = env('OSRM_HOST', 'https://router.project-osrm.org');

        try {
            $response = Http::timeout(10)->get(
                "{$osrmHost}/route/v1/driving/{$lng1},{$lat1};{$lng2},{$lat2}",
                ['overview' => 'false']
            );

            if ($response->ok()) {
                $meters = $response->json('routes.0.distance');
                if ($meters) {
                    return round($meters / 1000, 2);
                }
            }
        } catch (\Throwable $e) {
            // fall through to haversine estimate below
        }

        // Straight-line fallback if OSRM is unreachable, x1.3 for road-vs-straight-line.
        $r = 6371.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a  = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        $straightLineKm = 2 * $r * asin(sqrt($a));

        return round($straightLineKm * 1.3, 2);
    }

    // -- pdf -----------------------------------------------------------

    protected function generatePdf(GridxQuote $quote): string
    {
        $html = $this->pdfHtml($quote);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $path = "gridx-quotes/{$quote->public_id}.pdf";
        Storage::disk('local')->put($path, $dompdf->output());

        return $path;
    }

    protected function pdfHtml(GridxQuote $quote): string
    {
        $row = fn (string $label, string $value) => "<tr><td style='padding:6px 0;color:#667085;'>{$label}</td><td style='padding:6px 0;text-align:right;font-weight:600;'>{$value}</td></tr>";
        $fmt = fn ($amount) => number_format((float) $amount, 2) . ' ' . $quote->currency;

        $lineItems = $row('Base fee', $fmt($quote->base_fee))
            . $row("Distance ({$quote->distance_km} km)", $fmt($quote->distance_cost))
            . $row('Fuel surcharge', $fmt($quote->fuel_surcharge));
        if ($quote->cross_border_surcharge > 0) {
            $lineItems .= $row('Cross-border surcharge', $fmt($quote->cross_border_surcharge));
        }
        $lineItems .= $row('Subtotal', $fmt($quote->subtotal))
            . $row('VAT (15%)', $fmt($quote->vat));

        return <<<HTML
        <html>
        <head><style>
            body { font-family: DejaVu Sans, sans-serif; color: #101828; font-size: 13px; }
            h1 { font-size: 20px; margin-bottom: 2px; }
            .muted { color: #667085; }
            table { width: 100%; border-collapse: collapse; margin-top: 16px; }
            .total-row td { border-top: 2px solid #101828; padding-top: 10px; font-size: 16px; font-weight: 700; }
        </style></head>
        <body>
            <h1>GridX — Freight Quote</h1>
            <div class="muted">Quote #{$quote->public_id}</div>
            <div class="muted">Generated on {$quote->created_at->format('d M Y')}</div>

            <table style="margin-top:24px;">
                <tr><td class="muted">Pickup</td><td style="text-align:right;">{$quote->pickup_address}</td></tr>
                <tr><td class="muted">Dropoff</td><td style="text-align:right;">{$quote->dropoff_address}</td></tr>
                <tr><td class="muted">Truck type</td><td style="text-align:right;">{$quote->truck_type}</td></tr>
            </table>

            <table>
                {$lineItems}
                <tr class="total-row"><td>Total</td><td style="text-align:right;">{$fmt($quote->total)}</td></tr>
            </table>

            <p class="muted" style="margin-top:32px;font-size:11px;">
                This quote is valid for 7 days from generation. Pricing includes a dynamic fuel surcharge
                based on current diesel prices (diesel @ {$quote->diesel_price_used} SAR/L used for this calculation).
            </p>
        </body>
        </html>
        HTML;
    }
}
