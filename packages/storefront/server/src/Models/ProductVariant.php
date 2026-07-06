<?php

namespace GridX\Storefront\Models;

use GridX\Casts\Json;
use GridX\Models\Category;
use GridX\Models\User;
use GridX\Traits\HasApiModelBehavior;
use GridX\Traits\HasMetaAttributes;
use GridX\Traits\HasPublicid;
use GridX\Traits\HasUuid;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class ProductVariant extends StorefrontModel
{
    use HasUuid;
    use HasPublicid;
    use HasApiModelBehavior;
    use HasMetaAttributes;
    use HasSlug;

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'variant';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'product_variants';

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
        'product_uuid',
        'name',
        'description',
        'translations',
        'meta',
        'is_multiselect',
        'is_required',
        'min',
        'max',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_multiselect' => 'boolean',
        'is_required'    => 'boolean',
        'meta'           => Json::class,
        'translations'   => Json::class,
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * Relationships to auto load with driver.
     *
     * @var array
     */
    protected $with = [];

    /**
     * @var SlugOptions
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->setConnection(config('gridx.connection.db'))->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->setConnection(config('gridx.connection.db'))->belongsTo(Category::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function options()
    {
        return $this->hasMany(ProductVariantOption::class);
    }
}
