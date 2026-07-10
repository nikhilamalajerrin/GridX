<?php

namespace App\Http\Controllers;

use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\FuelReport;
use Fleetbase\FleetOps\Models\Issue;
use Fleetbase\FleetOps\Models\Maintenance;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Part;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\FleetOps\Models\Vendor;
use Fleetbase\FleetOps\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Natural-language Q&A over the company's live fleet data. Read-only: builds
 * a compact snapshot of the company's current state and lets an LLM (via
 * OpenRouter, same provider the WhatsApp agent uses) answer in plain English.
 * No write access — this never creates/edits records.
 */
class CopilotController extends Controller
{
    public function ask(Request $request)
    {
        $request->validate(['question' => 'required|string|max:1000']);

        $companyUuid = Auth::user()?->company_uuid ?? session('company');
        if (!$companyUuid) {
            return response()->json(['error' => 'No company context for this session.'], 422);
        }

        $snapshot = $this->buildSnapshot($companyUuid);
        $answer   = $this->askLlm($request->input('question'), $snapshot);

        return response()->json(['answer' => $answer]);
    }

    protected function buildSnapshot(string $companyUuid): array
    {
        $drivers  = Driver::where('company_uuid', $companyUuid)->get();
        $vehicles = Vehicle::where('company_uuid', $companyUuid)->get();

        return [
            'drivers' => $drivers->map(fn ($d) => [
                'name' => $d->name,
                'vehicle' => optional($vehicles->firstWhere('uuid', $d->vehicle_uuid))->display_name,
                'online' => (bool) $d->online,
                'location' => $d->location ? [$d->location->getLat(), $d->location->getLng()] : null,
            ])->all(),
            'vehicles' => $vehicles->map(fn ($v) => [
                'name' => $v->display_name,
                'status' => $v->status,
            ])->all(),
            'open_orders_count'      => Order::where('company_uuid', $companyUuid)->whereNotIn('status', ['completed', 'canceled'])->count(),
            'open_issues'            => Issue::where('company_uuid', $companyUuid)->where('status', '!=', 'resolved')
                ->get(['title', 'priority', 'status', 'vehicle_uuid'])
                ->map(fn ($i) => ['title' => $i->title, 'priority' => $i->priority, 'status' => $i->status, 'vehicle' => optional($vehicles->firstWhere('uuid', $i->vehicle_uuid))->display_name])
                ->all(),
            'open_work_orders'       => WorkOrder::where('company_uuid', $companyUuid)->whereIn('status', ['open', 'in_progress'])
                ->get(['subject', 'status', 'priority', 'due_at', 'estimated_cost'])->toArray(),
            'overdue_maintenance'    => Maintenance::where('company_uuid', $companyUuid)->where('status', 'scheduled')
                ->where('scheduled_at', '<', now())->get(['summary', 'scheduled_at', 'priority'])->toArray(),
            'low_stock_parts'        => Part::where('company_uuid', $companyUuid)->where('status', 'low_stock')
                ->get(['name', 'quantity_on_hand', 'reorder_point'])->toArray(),
            'vendors'                => Vendor::where('company_uuid', $companyUuid)->get(['name', 'type'])->toArray(),
            'recent_fuel_reports'    => FuelReport::where('company_uuid', $companyUuid)->orderByDesc('created_at')
                ->limit(10)->get(['vehicle_uuid', 'volume', 'amount', 'odometer', 'created_at'])
                ->map(fn ($f) => ['vehicle' => optional($vehicles->firstWhere('uuid', $f->vehicle_uuid))->display_name, 'volume' => $f->volume, 'amount' => $f->amount, 'odometer' => $f->odometer, 'created_at' => $f->created_at])
                ->all(),
        ];
    }

    protected function askLlm(string $question, array $snapshot): string
    {
        $apiKey = env('OPENROUTER_API_KEY');
        if (!$apiKey) {
            return 'Copilot is not configured yet (missing OPENROUTER_API_KEY).';
        }

        $systemPrompt = "You are GridX Copilot, an assistant embedded in a fleet management dashboard for a trucking company in Saudi Arabia/GCC.\n"
            . "Answer the user's question using ONLY the JSON fleet data snapshot below. Refer to drivers and vehicles by their name field — "
            . "never mention UUIDs or internal IDs in your answer, they're not meaningful to the dispatcher reading this. "
            . "Be concise (2-4 sentences unless a list is clearly needed). "
            . "If the data doesn't contain the answer, say so plainly instead of guessing.\n\n"
            . "FLEET DATA SNAPSHOT:\n" . json_encode($snapshot, JSON_PRETTY_PRINT);

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post(rtrim(env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'), '/') . '/chat/completions', [
                'model'    => env('OPENROUTER_MODEL', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $question],
                ],
                'max_tokens' => 500,
            ]);

        if ($response->failed()) {
            return 'Copilot request failed (' . $response->status() . '). Please try again.';
        }

        return $response->json('choices.0.message.content') ?? 'Copilot returned an empty response.';
    }
}
