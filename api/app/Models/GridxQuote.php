<?php

namespace App\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;

class GridxQuote extends Model
{
    use HasUuid;
    use HasPublicId;

    protected $table = 'gridx_quotes';
    protected $publicIdType = 'quote';

    protected $guarded = [];

    protected $casts = [
        'pickup_lat'        => 'float',
        'pickup_lng'        => 'float',
        'dropoff_lat'       => 'float',
        'dropoff_lng'       => 'float',
        'distance_km'       => 'float',
        'cargo_weight_kg'   => 'float',
        'base_fee'          => 'float',
        'distance_cost'     => 'float',
        'fuel_surcharge'    => 'float',
        'cross_border_surcharge' => 'float',
        'subtotal'          => 'float',
        'vat'               => 'float',
        'total'             => 'float',
        'diesel_price_used' => 'float',
        'approved_at'       => 'datetime',
        'paid_at'           => 'datetime',
        'dispatched_at'     => 'datetime',
    ];
}
