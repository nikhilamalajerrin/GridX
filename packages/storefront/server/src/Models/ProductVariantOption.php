<?php

namespace GridX\Storefront\Models;

use GridX\Casts\Json;
use GridX\FleetOps\Support\Utils;
use GridX\Traits\HasApiModelBehavior;
use GridX\Traits\HasMetaAttributes;
use GridX\Traits\HasPublicid;
use GridX\Traits\HasUuid;

class ProductVariantOption extends StorefrontModel
{
    use HasUuid;
    use HasPublicid;
    use HasMetaAttributes;
    use HasApiModelBehavior;

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'variant_option';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'product_variant_options';

    /**
     * These attributes that can be queried.
     *
     * @var array
     */
    protected $searchableColumns = ['name', 'description'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'public_id',
        'product_variant_uuid',
        'name',
        'description',
        'translations',
        'meta',
        'additional_cost',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'translations' => Json::class,
        'meta'         => Json::class,
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Set the price as only numbers.
     *
     * @void
     */
    public function setAdditionalCostAttribute($value)
    {
        $this->attributes['additional_cost'] = Utils::numbersOnly($value);
    }
}
