<?php

namespace GridX\FleetOps\Models;

use GridX\Casts\Json;
use GridX\FleetOps\Casts\Point;
use GridX\FleetOps\Support\Utils;
use GridX\LaravelMysqlSpatial\Eloquent\SpatialTrait;
use GridX\Models\Model;
use GridX\Traits\HasApiModelBehavior;
use GridX\Traits\HasMetaAttributes;
use GridX\Traits\HasPublicId;
use GridX\Traits\HasUuid;
use GridX\Traits\SendsWebhooks;
use GridX\Traits\TracksApiCredential;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TrackingStatus extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use SendsWebhooks;
    use TracksApiCredential;
    use SpatialTrait;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'tracking_statuses';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'status';

    /**
     * These attributes that can be queried.
     *
     * @var array
     */
    protected $searchableColumns = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        '_key',
        'company_uuid',
        'tracking_number_uuid',
        'proof_uuid',
        'status',
        'details',
        'code',
        'complete',
        'city',
        'province',
        'postal_code',
        'country',
        'location',
    ];

    /**
     * The attributes that are spatial columns.
     *
     * @var array
     */
    protected $spatialFields = [
        'location',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'meta'     => Json::class,
        'location' => Point::class,
        'complete' => 'boolean',
    ];

    /**
     * Attributes that is filterable on this model.
     *
     * @var array
     */
    protected $filterParams = [
        'order',
        'entity',
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function trackingNumber()
    {
        return $this->belongsTo(TrackingNumber::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function proof()
    {
        return $this->belongsTo(Proof::class);
    }

    public function setCodeAttribute($code)
    {
        $this->attributes['code'] = static::prepareCode($code);
    }

    public function setStatusAttribute($status)
    {
        $this->attributes['status'] = Str::title($status);
    }

    public static function prepareCode($string)
    {
        $string = str_replace(' ', '_', $string);
        $string = preg_replace('/[^A-Za-z0-9\-_]/', '', $string);
        $string = strtoupper($string);

        return $string;
    }

    public static function insertGetUuid($values = [], ?TrackingNumber $trackingNumber = null)
    {
        $instance   = new static();
        $fillable   = $instance->getFillable();
        $insertKeys = array_keys($values);
        // clean insert data
        foreach ($insertKeys as $key) {
            if (!in_array($key, $fillable)) {
                unset($values[$key]);
            }
        }

        $values['uuid']         = $uuid = (string) Str::uuid();
        $values['public_id']    = static::generatePublicId('status');
        $values['_key']         = session('api_key') ?? 'console';
        $values['created_at']   = Carbon::now()->toDateTimeString();
        $values['company_uuid'] = session('company');

        if ($trackingNumber) {
            $values['tracking_number_uuid'] = $trackingNumber->uuid;
            $values['status']               = Str::title($trackingNumber->type . ' created');
            $values['details']              = 'New ' . Str::lower($trackingNumber->type) . ' created.';
        }

        if (isset($values['meta']) && (is_object($values['meta']) || is_array($values['meta']))) {
            $values['meta'] = json_encode($values['meta']);
        }

        $result = static::insert($values);

        return $result ? $uuid : false;
    }

    public function isComplete(): bool
    {
        return Utils::isTrue($this->complete);
    }
}
