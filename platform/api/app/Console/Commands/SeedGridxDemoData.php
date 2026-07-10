<?php

namespace App\Console\Commands;

use Fleetbase\FleetOps\Casts\Point;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\FuelReport;
use Fleetbase\FleetOps\Models\Issue;
use Fleetbase\FleetOps\Models\Maintenance;
use Fleetbase\FleetOps\Models\Part;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\FleetOps\Models\Vendor;
use Fleetbase\FleetOps\Models\WorkOrder;
use Fleetbase\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds realistic demo data (vendors, fuel reports, parts, maintenance,
 * work orders, issues) against the company's REAL existing drivers/vehicles,
 * so the Resources/Maintenance hubs and the copilot have something to show.
 *
 * Idempotent: tags every record with meta.seed = 'gridx-demo' and purges
 * previously-seeded rows before re-seeding, so it's safe to re-run.
 */
class SeedGridxDemoData extends Command
{
    protected $signature = 'gridx:seed-demo-data';
    protected $description = 'Seed realistic demo data for the existing GridX company (vendors, fuel, parts, maintenance, work orders, issues)';

    protected const SEED_TAG = 'gridx-demo';

    public function handle(): int
    {
        $company = Company::query()->orderBy('created_at')->first();
        if (!$company) {
            $this->error('No company found — cannot seed demo data.');

            return self::FAILURE;
        }

        $vehicles = Vehicle::where('company_uuid', $company->uuid)->get();
        $drivers  = Driver::where('company_uuid', $company->uuid)->get();

        if ($vehicles->isEmpty()) {
            $this->error('Company has no vehicles — cannot seed demo data against them.');

            return self::FAILURE;
        }

        $this->info("Seeding demo data for company {$company->uuid} ({$vehicles->count()} vehicles, {$drivers->count()} drivers)");

        DB::transaction(function () use ($company, $vehicles, $drivers) {
            $this->purge();

            $vendors = $this->seedVendors($company);
            $this->seedFuelReports($company, $vehicles, $drivers);
            $this->seedParts($company, $vendors['parts']);
            $this->seedMaintenance($company, $vehicles, $vendors['garage']);
            $this->seedWorkOrders($company, $vehicles);
            $this->seedIssues($company, $vehicles, $drivers);
        });

        $this->info('Done.');

        return self::SUCCESS;
    }

    protected function purge(): void
    {
        foreach ([Vendor::class, FuelReport::class, Part::class, Maintenance::class, WorkOrder::class, Issue::class] as $modelClass) {
            $modelClass::where('meta->seed', self::SEED_TAG)->forceDelete();
        }
    }

    protected function meta(array $extra = []): array
    {
        return array_merge(['seed' => self::SEED_TAG], $extra);
    }

    protected function create(string $modelClass, array $attributes): object
    {
        $model = new $modelClass();
        $model->forceFill(array_merge(['uuid' => (string) Str::uuid()], $attributes));
        $model->save();

        return $model;
    }

    protected function seedVendors(Company $company): array
    {
        $garage = $this->create(Vendor::class, [
            'company_uuid' => $company->uuid,
            'name'         => 'Al Rashid Truck Service Center',
            'type'         => 'maintenance',
            'email'        => 'service@alrashidtrucks.example',
            'phone'        => '+966501234567',
            'country'      => 'SA',
            'status'       => 'active',
            'meta'         => $this->meta(),
        ]);

        $parts = $this->create(Vendor::class, [
            'company_uuid' => $company->uuid,
            'name'         => 'Gulf Fleet Parts Supply Co.',
            'type'         => 'parts_supplier',
            'email'        => 'orders@gulffleetparts.example',
            'phone'        => '+971501112233',
            'country'      => 'AE',
            'status'       => 'active',
            'meta'         => $this->meta(),
        ]);

        $fuel = $this->create(Vendor::class, [
            'company_uuid' => $company->uuid,
            'name'         => 'ADNOC Fleet Fuel Card',
            'type'         => 'fuel_supplier',
            'email'        => 'fleet@adnocdistribution.example',
            'phone'        => '+971509998877',
            'country'      => 'AE',
            'status'       => 'active',
            'meta'         => $this->meta(),
        ]);

        return ['garage' => $garage, 'parts' => $parts, 'fuel' => $fuel];
    }

    protected function seedFuelReports(Company $company, $vehicles, $drivers): void
    {
        $driversByVehicle = $drivers->keyBy('vehicle_uuid');

        foreach ($vehicles as $i => $vehicle) {
            $driver = $driversByVehicle->get($vehicle->uuid);
            // 4 fill-ups per vehicle over the last ~3 weeks, gently increasing odometer.
            for ($j = 0; $j < 4; $j++) {
                $daysAgo   = 21 - ($j * 6) - $i;
                $odometer  = 80000 + ($i * 15000) + ($j * 850);
    $volume    = 180 + ($j % 2 === 0 ? 20 : -10); // liters, occasional outlier for anomaly-detection demo
                $unitPrice = 2.18; // SAR/AED per liter, approximate GCC diesel price
                // Riyadh-area coordinates, jittered slightly per fill-up.
                $location = new Point(24.7136 + ($i * 0.01) + ($j * 0.002), 46.6753 + ($i * 0.01) - ($j * 0.002));
                $this->create(FuelReport::class, [
                    'company_uuid'  => $company->uuid,
                    'driver_uuid'   => $driver?->uuid,
                    'vehicle_uuid'  => $vehicle->uuid,
                    'odometer'      => $odometer,
                    'amount'        => round($volume * $unitPrice, 2),
                    'currency'      => 'SAR',
                    'volume'        => $volume,
                    'metric_unit'   => 'liter',
                    'location'      => $location,
                    'report'        => 'Diesel fill-up, ' . $volume . 'L @ station',
                    'status'        => 'received',
                    'meta'          => $this->meta(),
                    'created_at'    => Carbon::now()->subDays($daysAgo),
                    'updated_at'    => Carbon::now()->subDays($daysAgo),
                ]);
            }
        }
    }

    protected function seedParts(Company $company, Vendor $vendor): void
    {
        $parts = [
            ['OIL-FILTER-001', 'Donaldson P550 Oil Filter', 'Donaldson', 'P550', 'consumable', 14, 5, 45.00],
            ['BRAKE-PAD-HD-01', 'Bendix CT-3 Heavy Duty Brake Pads', 'Bendix', 'CT-3', 'brake', 2, 6, 320.00],
            ['AIR-FILTER-002', 'Mann-Filter C25860 Air Filter', 'Mann-Filter', 'C25860', 'consumable', 9, 4, 60.00],
            ['TIRE-DRIVE-315', 'Michelin X Multi D 315/80R22.5', 'Michelin', 'X Multi D', 'tire', 4, 10, 1450.00],
            ['BATTERY-HD-12V', 'Bosch T5 HD Truck Battery 225Ah', 'Bosch', 'T5-225', 'electrical', 1, 3, 890.00],
        ];

        foreach ($parts as [$sku, $name, $mfr, $model, $type, $qty, $reorderPoint, $unitCost]) {
            $this->create(Part::class, [
                'company_uuid'      => $company->uuid,
                'vendor_uuid'       => $vendor->uuid,
                'sku'               => $sku,
                'name'              => $name,
                'manufacturer'      => $mfr,
                'model'             => $model,
                'type'              => $type,
                'status'            => $qty <= $reorderPoint ? 'low_stock' : 'active',
                'quantity_on_hand'  => $qty,
                'reorder_point'     => $reorderPoint,
                'reorder_quantity'  => $reorderPoint * 3,
                'unit_cost'         => $unitCost,
                'currency'          => 'SAR',
                'meta'              => $this->meta(),
            ]);
        }
    }

    protected function seedMaintenance(Company $company, $vehicles, Vendor $garage): void
    {
        // Vehicle 0: overdue oil change. Vehicle 1: due this week. Others: recently completed history.
        $this->create(Maintenance::class, [
            'company_uuid'      => $company->uuid,
            'maintainable_type' => Vehicle::class,
            'maintainable_uuid' => $vehicles[0]->uuid,
            'type'              => 'preventive',
            'status'            => 'scheduled',
            'priority'          => 'high',
            'scheduled_at'      => Carbon::now()->subDays(4),
            'summary'           => 'Overdue: 20,000km service interval (oil, filters, brake inspection)',
            'performed_by_type' => Vendor::class,
            'performed_by_uuid' => $garage->uuid,
            'currency'          => 'SAR',
            'meta'              => $this->meta(),
        ]);

        $this->create(Maintenance::class, [
            'company_uuid'      => $company->uuid,
            'maintainable_type' => Vehicle::class,
            'maintainable_uuid' => $vehicles[1]->uuid,
            'type'              => 'preventive',
            'status'            => 'scheduled',
            'priority'          => 'medium',
            'scheduled_at'      => Carbon::now()->addDays(3),
            'summary'           => 'Scheduled: tire rotation + brake pad inspection',
            'performed_by_type' => Vendor::class,
            'performed_by_uuid' => $garage->uuid,
            'currency'          => 'SAR',
            'meta'              => $this->meta(),
        ]);

        foreach ($vehicles->slice(2) as $vehicle) {
            $this->create(Maintenance::class, [
                'company_uuid'      => $company->uuid,
                'maintainable_type' => Vehicle::class,
                'maintainable_uuid' => $vehicle->uuid,
                'type'              => 'preventive',
                'status'            => 'completed',
                'priority'          => 'medium',
                'scheduled_at'      => Carbon::now()->subDays(35),
                'started_at'        => Carbon::now()->subDays(34),
                'completed_at'      => Carbon::now()->subDays(34),
                'summary'           => 'Routine service completed',
                'performed_by_type' => Vendor::class,
                'performed_by_uuid' => $garage->uuid,
                'labor_cost'        => 350.00,
                'parts_cost'        => 210.00,
                'total_cost'        => 560.00,
                'currency'          => 'SAR',
                'meta'              => $this->meta(),
            ]);
        }
    }

    protected function seedWorkOrders(Company $company, $vehicles): void
    {
        $this->create(WorkOrder::class, [
            'company_uuid'    => $company->uuid,
            'subject'         => 'Overdue oil change — ' . ($vehicles[0]->display_name ?? 'Vehicle'),
            'category'        => 'maintenance',
            'status'          => 'open',
            'priority'        => 'high',
            'target_type'     => Vehicle::class,
            'target_uuid'     => $vehicles[0]->uuid,
            'opened_at'       => Carbon::now()->subDays(4),
            'due_at'          => Carbon::now()->addDay(),
            'estimated_cost'  => 480.00,
            'currency'        => 'SAR',
            'instructions'    => 'Perform 20,000km service: oil + filter change, brake inspection, top off fluids.',
            'meta'            => $this->meta(),
        ]);

        $this->create(WorkOrder::class, [
            'company_uuid'    => $company->uuid,
            'subject'         => 'Tire rotation — ' . ($vehicles[1]->display_name ?? 'Vehicle'),
            'category'        => 'maintenance',
            'status'          => 'in_progress',
            'priority'        => 'medium',
            'target_type'     => Vehicle::class,
            'target_uuid'     => $vehicles[1]->uuid,
            'opened_at'       => Carbon::now()->subDay(),
            'due_at'          => Carbon::now()->addDays(3),
            'estimated_cost'  => 250.00,
            'currency'        => 'SAR',
            'instructions'    => 'Rotate tires front-to-back, inspect brake pad wear.',
            'meta'            => $this->meta(),
        ]);
    }

    protected function seedIssues(Company $company, $vehicles, $drivers): void
    {
        $driver = $drivers->first();
        $this->create(Issue::class, [
            'company_uuid' => $company->uuid,
            'driver_uuid'  => $driver?->uuid,
            'vehicle_uuid' => $vehicles[0]->uuid,
            'location'     => new Point(24.7136, 46.6753),
            'title'        => 'Dashboard warning light — check engine',
            'type'         => 'vehicle',
            'category'     => 'mechanical',
            'priority'     => 'high',
            'report'       => 'Driver reported check-engine light came on during route. Vehicle still operable, recommend inspection before next long haul.',
            'status'       => 'pending',
            'meta'         => $this->meta(),
        ]);
    }
}
