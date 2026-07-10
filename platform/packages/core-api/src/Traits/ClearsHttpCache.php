<?php

namespace GridX\Traits;

use GridX\Observers\HttpCacheObserver;

trait ClearsHttpCache
{
    /**
     * Add observer to clear https cache after updates and creations/ deletions.
     *
     * @return void
     */
    public static function bootClearsHttpCache()
    {
        static::observe(new HttpCacheObserver());
    }
}
