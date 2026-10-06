<?php

namespace App\Models\Concerns;

use App\Jobs\ReindexTaxonomyMembers;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues ReindexTaxonomyMembers when a change to this taxonomy row alters
 * what its members' search documents say: a rename, (un)publishing, a delete
 * or a restore. Other edits (sort order, description) do not touch the index.
 */
trait ReindexesSearchMembers
{
    public static function bootReindexesSearchMembers(): void
    {
        $reindex = static function (Model $model): void {
            ReindexTaxonomyMembers::dispatch($model::class, (int) $model->getKey())->afterCommit();
        };

        static::saved(static function (Model $model) use ($reindex): void {
            if ($model->wasChanged(['name', 'is_published'])) {
                $reindex($model);
            }
        });
        static::deleted($reindex);
        static::restored($reindex);
    }
}
