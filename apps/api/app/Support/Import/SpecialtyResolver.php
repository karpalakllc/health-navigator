<?php

namespace App\Support\Import;

use App\Enums\ImportReviewKind;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use App\Support\Import\Fzom\FzomSpecialtyCatalog;

/**
 * Turns a source's specialty wording into our specialty ids through
 * specialty_aliases. The first sighting of a wording stores an alias from
 * the catalogue default (creating the specialty, unpublished, when it does
 * not exist yet); later runs use the stored alias, so a staff correction
 * of an alias is what counts from then on.
 */
final class SpecialtyResolver
{
    /** @var array<string, SpecialtyAlias> */
    private array $aliases = [];

    /** @var array<string, int> */
    private array $slugIds = [];

    public function __construct(
        private readonly ImportContext $context,
        private readonly string $aliasSource,
    ) {
        SpecialtyAlias::query()->where('source', $aliasSource)->get()
            ->each(function (SpecialtyAlias $alias): void {
                $this->aliases[$alias->raw_key] = $alias;
            });
    }

    /**
     * @return array{ids: list<int>, excluded: list<string>, unmapped: list<string>}
     */
    public function resolve(?string $raw, ?int $contractType = null): array
    {
        $ids = [];
        $excluded = [];
        $unmapped = [];

        $items = $raw === null ? [] : array_filter(array_map('trim', explode(',', $raw)), fn (string $item): bool => $item !== '');

        foreach ($items as $item) {
            $alias = $this->alias($item);

            if ($alias->is_excluded) {
                $excluded[] = $item;
            } elseif ($alias->specialty_id !== null) {
                $ids[] = (int) $alias->specialty_id;
            } else {
                $unmapped[] = $item;
            }
        }

        if ($ids === [] && $excluded === [] && $contractType !== null && isset(FzomSpecialtyCatalog::CONTRACT_TYPE_FALLBACK[$contractType])) {
            $ids[] = $this->specialtyId(FzomSpecialtyCatalog::CONTRACT_TYPE_FALLBACK[$contractType]);
        }

        return ['ids' => array_values(array_unique($ids)), 'excluded' => $excluded, 'unmapped' => $unmapped];
    }

    /**
     * Slug-based lookup for sources that already speak our catalogue.
     */
    public function specialtyId(string $slug): int
    {
        if (isset($this->slugIds[$slug])) {
            return $this->slugIds[$slug];
        }

        $specialty = Specialty::withTrashed()->where('slug', $slug)->first();

        if ($specialty === null) {
            $specialty = Specialty::query()->create([
                'slug' => $slug,
                'name' => FzomSpecialtyCatalog::SPECIALTIES[$slug] ?? $slug,
                'is_published' => false,
            ]);
            $specialty->forceFill(['created_by_import' => true])->save();
            $this->context->increment('specialties_created');
        }

        return $this->slugIds[$slug] = (int) $specialty->getKey();
    }

    private function alias(string $raw): SpecialtyAlias
    {
        $key = SpecialtyAlias::keyFor($raw);

        if (isset($this->aliases[$key])) {
            return $this->aliases[$key];
        }

        $default = FzomSpecialtyCatalog::defaultFor($key);

        $alias = SpecialtyAlias::query()->create([
            'source' => $this->aliasSource,
            'raw' => mb_substr($raw, 0, 255),
            'raw_key' => mb_substr($key, 0, 255),
            'is_excluded' => $default === FzomSpecialtyCatalog::EXCLUDED,
            'specialty_id' => $default !== null && $default !== FzomSpecialtyCatalog::EXCLUDED ? $this->specialtyId($default) : null,
        ]);

        if ($default === null) {
            $this->context->review(
                ImportReviewKind::Unmatched,
                'specialty:'.$this->aliasSource.':'.$key,
                'Unmapped specialty wording: '.$raw,
                ['alias_id' => $alias->getKey(), 'raw' => $raw],
            );
        }

        return $this->aliases[$key] = $alias;
    }
}
