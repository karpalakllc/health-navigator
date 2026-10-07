<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\ImportReviewItem;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use App\Support\Import\Fzom\FzomSpecialtyCatalog;
use App\Support\Import\Website\SpecialtyText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The website specialty wordings the import could not map on the first real
 * run (2026-10) are mapped to our catalogue where the meaning is certain: on
 * first sight by the importer, and for aliases already stored by the data
 * migration — which never overrides a staff edit and leaves unclear
 * wordings (job titles, degrees, specialisations in progress) unmapped.
 */
class WebsiteSpecialtyWordingsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_16_130000_map_website_specialty_wordings.php';

    private function alias(string $raw, string $source = 'website', ?int $specialtyId = null, bool $touched = false): SpecialtyAlias
    {
        $alias = SpecialtyAlias::query()->create([
            'source' => $source, 'raw' => $raw, 'raw_key' => SpecialtyAlias::keyFor($raw), 'specialty_id' => $specialtyId, 'is_excluded' => false,
        ]);

        if ($touched) {
            DB::table('specialty_aliases')->where('id', $alias->id)->update(['updated_at' => now()->addMinute()]);
        }

        return $alias;
    }

    private function migrate(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->up();
    }

    private function slugOf(SpecialtyAlias $alias): ?string
    {
        $id = $alias->fresh()?->specialty_id;

        return $id === null ? null : Specialty::withTrashed()->find($id)?->slug;
    }

    public function test_the_importer_maps_the_new_wordings_on_first_sight(): void
    {
        $aliases = SpecialtyText::extraAliases();

        foreach ([
            'ИНТЕРНА МЕДИЦИНА - ПНЕВМОФТИЗИОЛОГ' => 'pulmologija',
            'ПЕДИЈАТРИЈА - КАРДИОЛОГ' => 'pedijatrija',
            'ОБСТЕТРИЦИЈА И ГИНЕКОЛОГИЈА' => 'ginekologija',
            'GENERAL DENTIST' => 'stomatologija',
            'ORTHODONTIST' => 'stomatologija-ortodoncija',
            'ОПШТА МЕДИЦИНА - СПЕЦИЈАЛИЗАНТ ПО РАДИОЛОГИЈА' => 'opshta-medicina',
            'ГИНЕКОЛОГИЈА И АКУШЕРСТВО' => 'ginekologija',
        ] as $wording => $slug) {
            $this->assertSame($slug, $aliases[SpecialtyAlias::keyFor($wording)] ?? null, $wording);
        }

        foreach (['ДЕБЕЛИНА', 'FOUNDER', 'МЕДИЦИНСКИ ДИРЕКТОР', 'НА СУПСПЕЦИЈАЛИЗАЦИЈА', 'ТРАНСПЛАНТОЛОГИЈА', 'MJEKE FAMILJARE'] as $unclear) {
            $this->assertArrayNotHasKey(SpecialtyAlias::keyFor($unclear), $aliases, $unclear);
        }

        // Every target is in our catalogue.
        $this->assertSame([], array_values(array_diff(array_unique(array_values($aliases)), array_keys(FzomSpecialtyCatalog::SPECIALTIES))));
    }

    public function test_the_migration_maps_stored_unmapped_wordings_without_overriding_staff(): void
    {
        $pulmo = Specialty::query()->create(['slug' => 'pulmologija', 'name' => 'Пулмологија', 'is_published' => true]);
        $cardio = Specialty::query()->create(['slug' => 'kardiologija', 'name' => 'Кардиологија', 'is_published' => true]);

        $untouched = $this->alias('Интерна медицина - пневмофтизиолог');
        $latin = $this->alias('Orthodontist');
        $staffLeftUnmapped = $this->alias('Интерна медицина - пнеумофтизиолог', touched: true);
        $staffMapped = $this->alias('Интервентен кардиолог', specialtyId: $pulmo->id, touched: true);
        $fzom = $this->alias('Интервентен кардиолог', source: 'fzom');
        $unclear = $this->alias('Дебелина');
        $item = ImportReviewItem::raise('website', ImportReviewKind::Unmatched, 'specialty:website:'.$untouched->raw_key, 'Unmapped specialty wording', ['alias_id' => $untouched->id]);
        $unclearItem = ImportReviewItem::raise('website', ImportReviewKind::Unmatched, 'specialty:website:'.$unclear->raw_key, 'Unmapped specialty wording', ['alias_id' => $unclear->id]);

        $this->migrate();
        $this->migrate();

        $this->assertSame('pulmologija', $this->slugOf($untouched));
        // A slug missing from the catalogue table is created hidden, as the importer does.
        $this->assertSame('stomatologija-ortodoncija', $this->slugOf($latin));
        $this->assertFalse((bool) Specialty::query()->where('slug', 'stomatologija-ortodoncija')->value('is_published'));
        $this->assertNull($this->slugOf($staffLeftUnmapped), 'A wording staff edited is theirs.');
        $this->assertSame('pulmologija', $this->slugOf($staffMapped));
        $this->assertNull($this->slugOf($fzom), 'Only website wordings.');
        $this->assertNull($this->slugOf($unclear));
        $this->assertSame(ImportReviewStatus::Resolved, $item->fresh()->status);
        $this->assertSame(ImportReviewStatus::Open, $unclearItem->fresh()->status);
        $this->assertSame(1, Specialty::query()->where('slug', 'kardiologija')->count());
        $this->assertNotNull($cardio->fresh());
    }
}
