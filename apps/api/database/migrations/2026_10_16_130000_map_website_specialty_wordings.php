<?php

use App\Models\ImportReviewItem;
use App\Support\Import\Fzom\FzomSpecialtyCatalog;
use App\Support\Import\Website\SpecialtyText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data: maps the website specialty wordings the first real import left
 * unmapped (specialty_aliases, source „website“) to our catalogue, where the
 * meaning is certain (SpecialtyText::WEBSITE_WORDINGS; new imports map them
 * on first sight). Idempotent, and never overrides staff: only aliases still
 * unmapped, not excluded and never edited (updated_at = created_at) change.
 * Their open „Unmapped specialty wording“ review items are resolved. Doctors
 * are re-linked the next time the website slices are imported.
 */
return new class extends Migration
{
    public function up(): void
    {
        $aliases = SpecialtyText::extraAliases();
        $now = now();

        DB::table('specialty_aliases')
            ->where('source', 'website')
            ->whereNull('specialty_id')
            ->where('is_excluded', false)
            ->whereColumn('updated_at', 'created_at')
            ->orderBy('id')
            ->get(['id', 'raw_key'])
            ->each(function (object $alias) use ($aliases, $now): void {
                $slug = $aliases[(string) $alias->raw_key] ?? null;

                if ($slug === null) {
                    return;
                }

                DB::table('specialty_aliases')->where('id', $alias->id)->update(['specialty_id' => $this->specialtyId($slug, $now), 'updated_at' => $now]);
                DB::table('import_review_items')
                    ->where('source', 'website')
                    ->where('kind', 'unmatched')
                    ->where('status', 'open')
                    ->where('item_key', ImportReviewItem::key('specialty:website:'.$alias->raw_key))
                    ->update(['status' => 'resolved', 'resolution' => 'alias_mapped', 'resolved_at' => $now, 'updated_at' => $now]);
            });
    }

    /**
     * The catalogue row, created hidden when an import never needed it yet
     * (as SpecialtyResolver does).
     */
    private function specialtyId(string $slug, mixed $now): int
    {
        $id = DB::table('specialties')->where('slug', $slug)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('specialties')->insertGetId([
            'slug' => $slug,
            'name' => FzomSpecialtyCatalog::SPECIALTIES[$slug] ?? $slug,
            'is_published' => false,
            'created_by_import' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Data only: a mapping is kept (staff can change it in Specialty aliases).
    }
};
