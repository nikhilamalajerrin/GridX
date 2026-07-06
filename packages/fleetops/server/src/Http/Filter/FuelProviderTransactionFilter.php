<?php

namespace GridX\FleetOps\Http\Filter;

use GridX\FleetOps\Models\Driver;
use GridX\FleetOps\Models\FuelProviderConnection;
use GridX\FleetOps\Models\FuelReport;
use GridX\FleetOps\Models\Order;
use GridX\FleetOps\Models\Vehicle;
use GridX\FleetOps\Support\Utils;
use GridX\Http\Filter\Filter;
use GridX\Support\Http;
use Illuminate\Support\Str;

class FuelProviderTransactionFilter extends Filter
{
    public function queryForInternal()
    {
        $this->builder->where('company_uuid', $this->session->get('company'));
    }

    public function queryForPublic()
    {
        $this->queryForInternal();
    }

    public function query(?string $searchQuery)
    {
        $this->builder->search($searchQuery);
    }

    public function provider(?string $provider)
    {
        $this->builder->where('provider', $provider);
    }

    public function syncStatus($status)
    {
        if (Str::contains($status, ',')) {
            $status = explode(',', $status);
        }

        if (is_array($status)) {
            $this->builder->whereIn('sync_status', $status);
        } else {
            $this->builder->where('sync_status', $status);
        }
    }

    public function vehicle(?string $vehicle)
    {
        $this->wherePublicRelation('vehicle_uuid', Vehicle::class, $vehicle);
    }

    public function connection(?string $connection)
    {
        $this->wherePublicRelation('fuel_provider_connection_uuid', FuelProviderConnection::class, $connection);
    }

    public function driver(?string $driver)
    {
        $this->wherePublicRelation('driver_uuid', Driver::class, $driver);
    }

    public function order(?string $order)
    {
        $this->wherePublicRelation('order_uuid', Order::class, $order);
    }

    public function fuelReport(?string $fuelReport)
    {
        $this->wherePublicRelation('fuel_report_uuid', FuelReport::class, $fuelReport);
    }

    public function transactionAt($transactionAt)
    {
        $transactionAt = Utils::dateRange($transactionAt);

        if (is_array($transactionAt)) {
            $this->builder->whereBetween('transaction_at', $transactionAt);
        } else {
            $this->builder->whereDate('transaction_at', $transactionAt);
        }
    }

    protected function wherePublicRelation(string $column, string $modelClass, ?string $identifier): void
    {
        if (!$identifier) {
            return;
        }

        $this->builder->whereIn($column, $this->resolvePublicRelationUuids($modelClass, $identifier, Http::isInternalRequest($this->request)));
    }

    protected function resolvePublicRelationUuids(string $modelClass, string $identifier, bool $allowUuid = false)
    {
        $instance = new $modelClass();

        return $modelClass::query()
            ->where('company_uuid', $this->session->get('company'))
            ->where(function ($query) use ($identifier, $instance, $allowUuid) {
                $query->where('public_id', $identifier);

                if (in_array('internal_id', $instance->getFillable())) {
                    $query->orWhere('internal_id', $identifier);
                }

                if ($allowUuid) {
                    $query->orWhere('uuid', $identifier);
                }
            })
            ->pluck('uuid');
    }
}
