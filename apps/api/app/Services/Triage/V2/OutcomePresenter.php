<?php

namespace App\Services\Triage\V2;

use App\Models\Specialty;

/**
 * An outcome as the browser receives it. Specialty keys are resolved against
 * our catalogue: `slug` is set only when the doctors directory can filter by
 * it, so the web never builds a link to an empty filter.
 */
final class OutcomePresenter
{
    /** @var array<string, array{slug: string, name: string}>|null */
    private ?array $catalogue = null;

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    public function present(array $outcome): array
    {
        $care = (array) ($outcome['care'] ?? []);

        return [
            'id' => (string) ($outcome['id'] ?? ''),
            'level' => (string) $outcome['level'],
            'crisis' => (bool) ($outcome['crisis'] ?? false),
            'title' => (string) $outcome['title'],
            'summary' => (string) $outcome['summary'],
            'reasons' => array_values($outcome['reasons'] ?? []),
            'do_now' => array_values($outcome['do_now'] ?? []),
            'watch_for' => array_values($outcome['watch_for'] ?? []),
            'call' => array_values(array_map(
                fn (array $line) => ['number' => (string) $line['number'], 'label' => (string) ($line['label'] ?? '')],
                (array) ($outcome['call'] ?? []),
            )),
            'care' => [
                'setting' => (string) ($care['setting'] ?? 'gp'),
                'specialties' => array_values(array_map(fn (string $key) => $this->specialty($key), (array) ($care['specialties'] ?? []))),
                'facility_types' => array_values((array) ($care['facility_types'] ?? [])),
            ],
        ];
    }

    /** @return array{key: string, name: string, slug: string|null} */
    private function specialty(string $key): array
    {
        $this->catalogue ??= Specialty::query()->published()->get(['slug', 'name'])
            ->mapWithKeys(fn (Specialty $s) => [$s->slug => ['slug' => $s->slug, 'name' => $s->name]])
            ->all();

        $match = $this->catalogue[$key] ?? null;

        return [
            'key' => $key,
            'name' => $match['name'] ?? SpecialtyGroups::name($key),
            'slug' => $match['slug'] ?? null,
        ];
    }
}
