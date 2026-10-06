<?php

namespace App\Models;

use App\Support\Licences\SpecialtyKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One specialty wording of one source (the Лекарска комора list, ФЗОМ) and
 * the group it belongs to; see the 2026_10_15_110000 migration. Edited by
 * staff in „Licence specialty mapping“ (licences.manage).
 *
 * @property string $source
 * @property string $source_text
 * @property string $source_key
 * @property string|null $group_key
 * @property list<string>|null $compatible_groups
 * @property int|null $specialty_id
 * @property bool $is_ignored
 * @property Carbon|null $reviewed_at
 */
class LicenceSpecialtyMapping extends Model
{
    public const SOURCE_KOMORA = 'komora';

    public const SOURCE_FZOM = 'fzom';

    /** @var array<string, string> */
    public const SOURCES = [
        self::SOURCE_KOMORA => 'Лекарска комора',
        self::SOURCE_FZOM => 'ФЗОМ',
    ];

    protected $fillable = [
        'source',
        'source_text',
        'group_key',
        'compatible_groups',
        'specialty_id',
        'is_ignored',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (LicenceSpecialtyMapping $mapping): void {
            $mapping->source_key = SpecialtyKey::for($mapping->source_text);
            $mapping->group_key = self::normaliseGroup($mapping->group_key);
            $mapping->compatible_groups = self::normaliseGroups($mapping->compatible_groups ?? []) ?: null;
        });
    }

    protected function casts(): array
    {
        return [
            'compatible_groups' => 'array',
            'is_ignored' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Specialty, $this>
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Wording with neither a group nor the "not a physician's specialty" mark.
     *
     * @param  Builder<LicenceSpecialtyMapping>  $query
     * @return Builder<LicenceSpecialtyMapping>
     */
    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->whereNull('group_key')->where('is_ignored', false);
    }

    /**
     * Every group key in use, for the editor's suggestions.
     *
     * @return list<string>
     */
    public static function knownGroups(): array
    {
        return static::query()
            ->whereNotNull('group_key')
            ->distinct()
            ->orderBy('group_key')
            ->pluck('group_key')
            ->map(fn ($group): string => (string) $group)
            ->values()
            ->all();
    }

    public static function normaliseGroup(?string $group): ?string
    {
        $group = trim(mb_strtolower((string) $group, 'UTF-8'));

        return $group === '' ? null : $group;
    }

    /**
     * @param  array<array-key, mixed>  $groups
     * @return list<string>
     */
    public static function normaliseGroups(array $groups): array
    {
        $normalised = [];

        foreach ($groups as $group) {
            $group = self::normaliseGroup(is_string($group) ? $group : null);

            if ($group !== null && ! in_array($group, $normalised, true)) {
                $normalised[] = $group;
            }
        }

        return $normalised;
    }
}
