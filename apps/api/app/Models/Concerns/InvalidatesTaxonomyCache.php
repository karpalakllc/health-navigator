<?php

namespace App\Models\Concerns;

use App\Support\TaxonomyCache;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Flushes the TaxonomyCache groups a model feeds whenever a row is saved,
 * deleted or restored. The model lists its groups in taxonomyCacheGroups().
 */
trait InvalidatesTaxonomyCache
{
    /**
     * @return list<string>
     */
    abstract public static function taxonomyCacheGroups(): array;

    public static function bootInvalidatesTaxonomyCache(): void
    {
        $flush = static fn () => TaxonomyCache::flush(...static::taxonomyCacheGroups());

        static::saved($flush);
        static::deleted($flush);

        // registerModelEvent() rather than restored(): only soft-deleting
        // models define the latter, and this trait is used by models without it.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', $flush);
        }
    }
}
