<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Specialty;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Re-sends the search documents of every doctor in a specialty, or every
 * facility in a department, after the taxonomy row changed.
 *
 * Doctor and facility documents embed their published specialty/department
 * names (toSearchableArray), and Scout only re-indexes a model when that model
 * itself is saved — so renaming, unpublishing or deleting a specialty would
 * otherwise leave Meilisearch matching the old name until the next full
 * search:reindex. Queued, and chunked by Scout's searchable() macro, because a
 * specialty can hold thousands of doctors.
 *
 * Members are found through the pivot table directly: a soft-deleted taxonomy
 * row no longer appears through the relation's SoftDeletes scope, but its
 * members still carry its name in the index.
 */
final class ReindexTaxonomyMembers implements ShouldQueue
{
    use Queueable;

    public const CHUNK = 500;

    /**
     * @param  class-string<Specialty|Department>  $taxonomy
     */
    public function __construct(
        public readonly string $taxonomy,
        public readonly int $taxonomyId,
    ) {}

    public function handle(): void
    {
        [$model, $pivot, $memberKey, $taxonomyKey] = $this->taxonomy === Specialty::class
            ? [Doctor::class, 'doctor_specialty', 'doctor_id', 'specialty_id']
            : [Facility::class, 'department_facility', 'facility_id', 'department_id'];

        $model::query()
            ->whereIn('id', DB::table($pivot)->select($memberKey)->where($taxonomyKey, $this->taxonomyId))
            ->searchable(self::CHUNK);
    }
}
