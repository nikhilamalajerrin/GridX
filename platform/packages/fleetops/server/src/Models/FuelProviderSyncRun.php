<?php

namespace GridX\FleetOps\Models;

use GridX\Casts\Json;
use GridX\Models\Model;
use GridX\Traits\HasApiModelBehavior;
use GridX\Traits\HasPublicId;
use GridX\Traits\HasUuid;
use GridX\Traits\Searchable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelProviderSyncRun extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use Searchable;

    protected $table             = 'fuel_provider_sync_runs';
    protected $publicIdType      = 'fuel_provider_sync_run';
    protected $searchableColumns = ['public_id', 'provider', 'status'];
    protected $filterParams      = ['provider', 'status', 'connection'];

    protected $fillable = [
        'company_uuid',
        'fuel_provider_connection_uuid',
        'provider',
        'status',
        'from',
        'to',
        'imported',
        'matched',
        'unmatched',
        'fuel_reports_created',
        'liters',
        'amount',
        'started_at',
        'finished_at',
        'error',
        'summary',
        'meta',
    ];

    protected $casts = [
        'from'        => 'datetime',
        'to'          => 'datetime',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'summary'     => Json::class,
        'meta'        => Json::class,
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FuelProviderConnection::class, 'fuel_provider_connection_uuid', 'uuid');
    }
}
