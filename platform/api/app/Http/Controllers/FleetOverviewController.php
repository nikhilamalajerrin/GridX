<?php

namespace App\Http\Controllers;

use App\Models\GridxQuote;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Issue;
use Fleetbase\FleetOps\Models\Maintenance;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\FleetOps\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Single snapshot endpoint for the Home dashboard's Fleet Overview panel —
 * one query set instead of the six+ separate widget calls the vendor
 * dashboard normally makes, all real counts (no placeholder data).
 */
class FleetOverviewController extends Controller
{
    public function index(Request $request)
    {
        $companyUuid = Auth::user()?->company_uuid ?? session('company');
        if (!$companyUuid) {
            return response()->json(['error' => 'No company context for this session.'], 422);
        }

        $workOrderStatuses = WorkOrder::where('company_uuid', $companyUuid)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $ordersByDay  = Order::where('company_uuid', $companyUuid)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as day, count(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $activity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date               = now()->subDays($i)->format('Y-m-d');
            $activity[]         = ['date' => $date, 'orders' => (int) ($ordersByDay[$date] ?? 0)];
        }

        $alerts = collect();
        Issue::where('company_uuid', $companyUuid)->where('status', '!=', 'resolved')
            ->orderByDesc('created_at')->limit(5)
            ->get(['uuid', 'title', 'priority', 'created_at'])
            ->each(function ($issue) use ($alerts) {
                $alerts->push([
                    'type'     => 'issue',
                    'title'    => $issue->title,
                    'severity' => $issue->priority ?? 'normal',
                    'created_at' => $issue->created_at,
                ]);
            });
        Maintenance::where('company_uuid', $companyUuid)->where('status', 'scheduled')
            ->where('scheduled_at', '<', now())
            ->orderByDesc('scheduled_at')->limit(5)
            ->get(['uuid', 'summary', 'scheduled_at'])
            ->each(function ($m) use ($alerts) {
                $alerts->push([
                    'type'       => 'maintenance_overdue',
                    'title'      => $m->summary ?? 'Maintenance overdue',
                    'severity'   => 'high',
                    'created_at' => $m->scheduled_at,
                ]);
            });

        $topDriverCounts = Order::where('company_uuid', $companyUuid)
            ->whereNotNull('driver_assigned_uuid')
            ->where('status', 'completed')
            ->selectRaw('driver_assigned_uuid, count(*) as completed_orders')
            ->groupBy('driver_assigned_uuid')
            ->orderByDesc('completed_orders')
            ->limit(5)
            ->get();
        $topDriverUuids = $topDriverCounts->pluck('driver_assigned_uuid');
        $driversByUuid  = Driver::whereIn('uuid', $topDriverUuids)->get()->keyBy('uuid');
        $topDrivers     = $topDriverCounts->map(fn ($row) => [
            'name'             => optional($driversByUuid->get($row->driver_assigned_uuid))->name ?? 'Unknown driver',
            'completed_orders' => (int) $row->completed_orders,
        ])->values();

        $recentActivity = Order::where('company_uuid', $companyUuid)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['public_id', 'status', 'type', 'created_at'])
            ->map(fn ($o) => [
                'label'      => "Order {$o->public_id}",
                'detail'     => $o->status,
                'created_at' => $o->created_at,
            ]);

        return response()->json([
            'total_trucks'      => Vehicle::where('company_uuid', $companyUuid)->count(),
            'active_drivers'    => Driver::where('company_uuid', $companyUuid)->where('online', true)->count(),
            'pending_quotes'    => GridxQuote::where('company_uuid', $companyUuid)->where('status', 'quote_pending')->count(),
            'active_orders'     => Order::where('company_uuid', $companyUuid)->whereNotIn('status', ['completed', 'canceled'])->count(),
            'open_work_orders'  => WorkOrder::where('company_uuid', $companyUuid)->whereIn('status', ['open', 'in_progress'])->count(),
            'work_orders_by_status' => $workOrderStatuses,
            'fleet_activity_7d' => $activity,
            'alerts'            => $alerts->sortByDesc('created_at')->take(6)->values(),
            'top_drivers'       => $topDrivers,
            'recent_activity'   => $recentActivity,
        ]);
    }
}
