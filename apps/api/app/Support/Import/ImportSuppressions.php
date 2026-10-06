<?php

namespace App\Support\Import;

use App\Models\Doctor;
use App\Models\ImportSuppression;

/**
 * The active suppressions, loaded once per run: which source rows must not
 * become (or feed) a doctor profile again. See ImportSuppression.
 *
 * - by facsimile, licence number or source key: always;
 * - by normalised name + town, only before a NEW profile would be created:
 *   for a ФЗОМ row only against suppressions that had no facsimile (a staff
 *   profile), since a different facsimile is a different person; for a
 *   website row against all of them (websites carry no stronger key).
 */
final class ImportSuppressions
{
    /** @var array<string, true> */
    private array $facsimiles = [];

    /** @var array<string, true> */
    private array $licences = [];

    /** @var array<string, true> */
    private array $sourceKeys = [];

    /** @var array<int, true> */
    private array $doctorIds = [];

    /** @var array<string, list<array{city: string|null, has_facsimile: bool}>> */
    private array $names = [];

    public function __construct()
    {
        ImportSuppression::query()->active()->get()->each(function (ImportSuppression $suppression): void {
            if ($suppression->doctor_id !== null) {
                $this->doctorIds[(int) $suppression->doctor_id] = true;
            }

            if ($suppression->fzo_facsimile !== null) {
                $this->facsimiles[$suppression->fzo_facsimile] = true;
            }

            if ($suppression->licence_number !== null) {
                $this->licences[$suppression->licence_number] = true;
            }

            foreach ($suppression->source_keys ?? [] as $key) {
                $this->sourceKeys[$key] = true;
            }

            if ($suppression->name_key_sorted !== null && $suppression->name_key_sorted !== '') {
                $this->names[$suppression->name_key_sorted][] = [
                    'city' => $suppression->city_key,
                    'has_facsimile' => $suppression->fzo_facsimile !== null,
                ];
            }
        });
    }

    public static function isSuppressed(Doctor $doctor): bool
    {
        return ImportSuppression::query()->active()
            ->where(fn ($query) => $query->where('doctor_id', $doctor->getKey())
                ->when($doctor->fzo_facsimile !== null, fn ($q) => $q->orWhere('fzo_facsimile', $doctor->fzo_facsimile))
                ->when($doctor->licence_number !== null, fn ($q) => $q->orWhere('licence_number', $doctor->licence_number)))
            ->exists();
    }

    public function facsimile(?string $facsimile): bool
    {
        return $facsimile !== null && isset($this->facsimiles[$facsimile]);
    }

    public function licence(?string $number): bool
    {
        return $number !== null && isset($this->licences[$number]);
    }

    public function sourceKey(string $source, string $externalKey): bool
    {
        return isset($this->sourceKeys[$source.':'.$externalKey]);
    }

    public function doctorId(int|string|null $id): bool
    {
        return $id !== null && isset($this->doctorIds[(int) $id]);
    }

    /**
     * @param  bool  $onlyWithoutFacsimile  a ФЗОМ row: a suppression that had a facsimile is a different person
     */
    public function name(string $fullName, ?string $city, bool $onlyWithoutFacsimile): bool
    {
        $cityKey = $city !== null && trim($city) !== '' ? NameKey::for($city) : null;

        foreach ($this->names[NameKey::sorted($fullName)] ?? [] as $entry) {
            if ($onlyWithoutFacsimile && $entry['has_facsimile']) {
                continue;
            }

            // A town on both sides must agree; a missing one does not rule it out.
            if ($cityKey === null || $entry['city'] === null || $entry['city'] === $cityKey) {
                return true;
            }
        }

        return false;
    }
}
