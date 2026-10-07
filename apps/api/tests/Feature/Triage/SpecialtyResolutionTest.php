<?php

namespace Tests\Feature\Triage;

use App\Models\LicenceSpecialtyMapping;
use App\Models\Specialty;
use App\Services\Triage\V2\OutcomePresenter;
use App\Support\Import\Fzom\FzomSpecialtyCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flows name specialties by licence-group key (opsta-medicina,
 * gastroenterohepatologija, …); the catalogue the ФЗОМ import creates uses
 * its own slugs (opshta-medicina, gastroenterologija, …). An outcome must
 * still link to the directory.
 */
class SpecialtyResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Keys a flow may use although no catalogue specialty answers to them:
     * the outcome then shows the readable name without a directory link.
     *
     * @var list<string>
     */
    private const ALLOWED_FALLBACK = [];

    /** The catalogue as the ФЗОМ import builds it, every specialty published. */
    private function seedRealisticCatalogue(): void
    {
        foreach (FzomSpecialtyCatalog::SPECIALTIES as $slug => $name) {
            Specialty::factory()->create(['slug' => $slug, 'name' => $name, 'is_published' => true]);
        }
    }

    /** @return array<string, list<string>> key => files using it */
    private function flowSpecialtyKeys(): array
    {
        $keys = [];
        $files = array_merge(
            glob(database_path('data/triage/flows/*.json')) ?: [],
            glob(database_path('data/triage/global/*.json')) ?: [],
        );

        $walk = function (mixed $node, string $file) use (&$walk, &$keys): void {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['specialties']) && is_array($node['specialties'])) {
                foreach ($node['specialties'] as $key) {
                    $keys[(string) $key][] = $file;
                }
            }
            foreach ($node as $child) {
                $walk($child, $file);
            }
        };

        foreach ($files as $file) {
            $walk(json_decode((string) file_get_contents($file), true), basename($file));
        }

        return $keys;
    }

    /** @return array{key: string, name: string, slug: string|null} */
    private function resolve(string $key): array
    {
        $presented = (new OutcomePresenter)->present([
            'id' => 'o', 'level' => 'see_gp_this_week', 'title' => 't', 'summary' => 's',
            'care' => ['setting' => 'specialist', 'specialties' => [$key]],
        ]);

        return $presented['care']['specialties'][0];
    }

    public function test_every_specialty_key_in_the_flows_resolves_to_a_catalogue_specialty(): void
    {
        $this->seedRealisticCatalogue();
        $keys = $this->flowSpecialtyKeys();
        $this->assertNotEmpty($keys);

        $unresolved = [];
        foreach (array_keys($keys) as $key) {
            if ($this->resolve($key)['slug'] === null && ! in_array($key, self::ALLOWED_FALLBACK, true)) {
                $unresolved[] = $key.' ('.implode(', ', array_unique($keys[$key])).')';
            }
        }

        $this->assertSame([], $unresolved, 'Specialty keys with no catalogue specialty');
    }

    public function test_licence_group_keys_map_to_the_import_slugs(): void
    {
        $this->seedRealisticCatalogue();

        $this->assertSame('opshta-medicina', $this->resolve('opsta-medicina')['slug']);
        $this->assertSame('gastroenterologija', $this->resolve('gastroenterohepatologija')['slug']);
        $this->assertSame('opshta-hirurgija', $this->resolve('opsta-hirurgija')['slug']);
        $this->assertSame('plastichna-hirurgija', $this->resolve('plasticna-hirurgija')['slug']);
        // The catalogue's own name is shown, the key is kept for the web's ids.
        $this->assertSame('Гастроентерохепатологија', $this->resolve('gastroenterohepatologija')['name']);
        $this->assertSame('gastroenterohepatologija', $this->resolve('gastroenterohepatologija')['key']);
        // A key equal to a slug stays itself (psihijatrija, not the child one).
        $this->assertSame('psihijatrija', $this->resolve('psihijatrija')['slug']);
    }

    public function test_an_unpublished_specialty_is_not_linked(): void
    {
        Specialty::factory()->create(['slug' => 'opshta-medicina', 'name' => 'Општа медицина', 'is_published' => false]);

        $this->assertNull($this->resolve('opsta-medicina')['slug']);
    }

    public function test_a_staff_linked_licence_mapping_wins_over_the_shipped_default(): void
    {
        $this->seedRealisticCatalogue();
        $own = Specialty::factory()->create(['slug' => 'gastroenterologija-i-hepatologija', 'name' => 'Гастро', 'is_published' => true]);
        LicenceSpecialtyMapping::query()->where('group_key', 'gastroenterohepatologija')->update(['specialty_id' => $own->id]);

        $this->assertSame('gastroenterologija-i-hepatologija', $this->resolve('gastroenterohepatologija')['slug']);
    }
}
