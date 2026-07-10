<?php

namespace App\Console\Commands;

use Fleetbase\FleetOps\Models\Maintenance;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\FleetOps\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Auto-triage: scans scheduled maintenance and opens a WorkOrder before
 * something breaks, instead of waiting for a human to notice the Maintenance
 * Hub counters. Idempotent — skips any maintenance record that already has
 * an open work order targeting the same vehicle for the same maintenance.
 *
 * Priority signal: overdue -> high, due within 3 days -> medium, otherwise
 * left alone (no work order needed yet).
 */
class GridxMaintenanceAutoTriage extends Command
{
    protected $signature = 'gridx:maintenance-auto-triage';
    protected $description = 'Auto-create work orders for overdue or soon-due scheduled maintenance';

    public function handle(): int
    {
        $dueSoonCutoff = Carbon::now()->addDays(3);

        $candidates = Maintenance::where('status', 'scheduled')
            ->where('scheduled_at', '<=', $dueSoonCutoff)
            ->get();

        $created = 0;

        foreach ($candidates as $maintenance) {
            $alreadyTriaged = WorkOrder::where('company_uuid', $maintenance->company_uuid)
                ->where('target_type', $maintenance->maintainable_type)
                ->where('target_uuid', $maintenance->maintainable_uuid)
                ->whereIn('status', ['open', 'in_progress'])
                ->where('meta->maintenance_uuid', $maintenance->uuid)
                ->exists();

            if ($alreadyTriaged) {
                continue;
            }

            $isOverdue = Carbon::parse($maintenance->scheduled_at)->isPast();
            $vehicle   = $maintenance->maintainable_type === Vehicle::class
                ? Vehicle::find($maintenance->maintainable_uuid)
                : null;

            $workOrder = new WorkOrder();
            $workOrder->forceFill([
                'uuid'           => (string) \Illuminate\Support\Str::uuid(),
                'company_uuid'   => $maintenance->company_uuid,
                'subject'        => ($isOverdue ? 'Overdue: ' : 'Upcoming: ') . ($maintenance->summary ?? 'Scheduled maintenance') . ($vehicle ? ' — ' . $vehicle->display_name : ''),
                'category'       => 'maintenance',
                'status'         => 'open',
                'priority'       => $isOverdue ? 'high' : 'medium',
                'target_type'    => $maintenance->maintainable_type,
                'target_uuid'    => $maintenance->maintainable_uuid,
                'opened_at'      => Carbon::now(),
                'due_at'         => $maintenance->scheduled_at,
                'currency'       => $maintenance->currency ?? 'SAR',
                'instructions'   => 'Auto-created by GridX maintenance auto-triage from scheduled maintenance record.',
                'meta'           => [
                    'seed'             => 'gridx-auto-triage',
                    'maintenance_uuid' => $maintenance->uuid,
                ],
            ]);
            $workOrder->save();

            $created++;
        }

        $this->info("Auto-triage complete: {$created} work order(s) created.");

        return self::SUCCESS;
    }
}
