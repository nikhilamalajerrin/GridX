<?php

namespace GridX\Models;

use GridX\Casts\Json;
use GridX\Traits\Filterable;
use GridX\Traits\HasApiModelBehavior;
use GridX\Traits\HasUuid;
use GridX\Traits\Searchable;

class DashboardWidget extends Model
{
    use HasUuid;
    use HasApiModelBehavior;
    use Searchable;
    use Filterable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'dashboard_widgets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'dashboard_uuid',
        'name',
        'component',
        'grid_options',
        'options',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'grid_options' => Json::class,
        'options'      => Json::class,
    ];
}
