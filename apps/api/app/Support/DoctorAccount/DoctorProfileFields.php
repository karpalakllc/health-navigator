<?php

namespace App\Support\DoctorAccount;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Specialty;
use App\Support\OfficeHours;
use App\Support\TaxonomyCache;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Which doctor-profile fields a linked doctor may change, and how.
 *
 * Immediate fields describe the practice, not the person's identity or
 * qualifications: they save at once. Sensitive fields (identity, title,
 * qualifications, specialties, workplaces) go through a change request that
 * staff approve; until then the profile keeps the old values.
 *
 * Never editable by the doctor at all: slug, publication, is_featured,
 * is_sponsored, the review aggregates and the account link.
 */
final class DoctorProfileFields
{
    /** Saved immediately by PATCH /me/doctor. */
    public const IMMEDIATE = [
        'bio',
        'phone',
        'email',
        'consultation_fee_note',
        'accepts_new_patients',
        'office_hours',
        'language_ids',
        'clinical_interest_ids',
        'procedure_ids',
    ];

    /** Scalar columns that need staff approval. */
    public const SENSITIVE_ATTRIBUTES = [
        'full_name',
        'title',
        'subspecialty',
        'education',
        'years_experience',
        'city',
    ];

    /** Relations that need staff approval (stored in a change request as id/name/is_primary lists). */
    public const SENSITIVE_RELATIONS = ['specialties', 'facilities'];

    public const BIO_MAX_LENGTH = 5000;

    public const EDUCATION_MAX_LENGTH = 2000;

    public const MAX_LINKS = 10;

    /**
     * @return array<string, mixed>
     */
    public static function immediateRules(): array
    {
        return [
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.self::BIO_MAX_LENGTH],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[0-9+()\/\-\s.]{6,50}$/u'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'consultation_fee_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'accepts_new_patients' => ['sometimes', 'boolean'],
            'office_hours' => ['sometimes', 'nullable', 'array', self::officeHoursRule()],
            'office_hours.*' => ['nullable', 'string', 'max:100'],
            'language_ids' => ['sometimes', 'array', 'max:20'],
            'language_ids.*' => ['integer', 'distinct', Rule::exists('languages', 'id')->where('is_published', true)],
            'clinical_interest_ids' => ['sometimes', 'array', 'max:30'],
            'clinical_interest_ids.*' => ['integer', 'distinct', Rule::exists('clinical_interests', 'id')->where('is_published', true)],
            'procedure_ids' => ['sometimes', 'array', 'max:30'],
            'procedure_ids.*' => ['integer', 'distinct', Rule::exists('procedures', 'id')->where('is_published', true)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sensitiveRules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subspecialty' => ['sometimes', 'nullable', 'string', 'max:255'],
            'education' => ['sometimes', 'nullable', 'string', 'max:'.self::EDUCATION_MAX_LENGTH],
            'years_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:70'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'specialty_ids' => ['sometimes', 'array', 'max:'.self::MAX_LINKS],
            'specialty_ids.*' => ['integer', 'distinct', Rule::exists('specialties', 'id')->where('is_published', true)],
            'primary_specialty_id' => ['sometimes', 'nullable', 'integer'],
            'facility_ids' => ['sometimes', 'array', 'max:'.self::MAX_LINKS],
            'facility_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('facilities', 'id')
                    ->where('is_published', true)
                    ->whereIn('type', FacilityType::clinicalValues())
                    ->whereNull('deleted_at'),
            ],
            'primary_facility_id' => ['sometimes', 'nullable', 'integer'],
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    private static function officeHoursRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $day) {
                if (! array_key_exists((string) $day, OfficeHours::DAY_OPTIONS)) {
                    $fail(__('api.doctor_account.office_hours_day'));

                    return;
                }
            }
        };
    }

    /**
     * Cleaned attribute values and relation ids from validated PATCH input.
     *
     * @param  array<string, mixed>  $validated
     * @return array{attributes: array<string, mixed>, relations: array<string, list<int>>}
     */
    public static function normaliseImmediate(array $validated): array
    {
        $attributes = [];

        if (array_key_exists('bio', $validated)) {
            $attributes['bio'] = PlainText::paragraphs($validated['bio']);
        }

        foreach (['phone', 'consultation_fee_note'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = PlainText::line($validated[$field]);
            }
        }

        if (array_key_exists('email', $validated)) {
            $email = PlainText::line($validated['email']);
            $attributes['email'] = $email === null ? null : mb_strtolower($email);
        }

        if (array_key_exists('accepts_new_patients', $validated)) {
            $attributes['accepts_new_patients'] = (bool) $validated['accepts_new_patients'];
        }

        if (array_key_exists('office_hours', $validated)) {
            $hours = [];

            foreach (OfficeHours::DAY_OPTIONS as $day) {
                $time = PlainText::line(is_array($validated['office_hours']) ? ($validated['office_hours'][$day] ?? null) : null);

                if ($time !== null) {
                    $hours[$day] = $time;
                }
            }

            $attributes['office_hours'] = $hours === [] ? null : $hours;
        }

        $relations = [];

        foreach (['language_ids' => 'languages', 'clinical_interest_ids' => 'clinicalInterests', 'procedure_ids' => 'procedures'] as $field => $relation) {
            if (array_key_exists($field, $validated)) {
                $relations[$relation] = array_values(array_map('intval', (array) $validated[$field]));
            }
        }

        return ['attributes' => $attributes, 'relations' => $relations];
    }

    /**
     * Apply validated immediate changes to the profile in one transaction.
     *
     * @param  array{attributes: array<string, mixed>, relations: array<string, list<int>>}  $changes
     */
    public static function applyImmediate(Doctor $doctor, array $changes): void
    {
        DB::transaction(function () use ($doctor, $changes): void {
            if ($changes['attributes'] !== []) {
                $doctor->forceFill($changes['attributes'])->save();
            }

            foreach ($changes['relations'] as $relation => $ids) {
                $result = $doctor->{$relation}()->sync($ids);

                if (array_filter($result) !== []) {
                    activity('doctor_profile')
                        ->performedOn($doctor)
                        ->event('relations_updated')
                        ->withProperties(['relation' => $relation, 'ids' => $ids])
                        ->log('relations_updated');
                }
            }
        });

        if (array_key_exists('languages', $changes['relations'])) {
            // The pivot sync fires no model event; GET /languages lists only
            // languages some published doctor speaks.
            TaxonomyCache::flush(TaxonomyCache::LANGUAGES);
        }
    }

    /**
     * The sensitive values the profile shows now, in the shape a change
     * request stores them.
     *
     * @return array<string, mixed>
     */
    public static function sensitiveSnapshot(Doctor $doctor): array
    {
        $doctor->loadMissing(['specialties', 'facilities']);

        $snapshot = [];

        foreach (self::SENSITIVE_ATTRIBUTES as $attribute) {
            $snapshot[$attribute] = $doctor->getAttribute($attribute);
        }

        $snapshot['specialties'] = self::linkList($doctor->specialties->map(fn (Specialty $s): array => [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'is_primary' => (bool) $s->getRelationValue('pivot')?->getAttribute('is_primary'),
        ])->all());

        $snapshot['facilities'] = self::linkList($doctor->facilities->map(fn (Facility $f): array => [
            'id' => (int) $f->id,
            'name' => (string) $f->name,
            'is_primary' => (bool) $f->getRelationValue('pivot')?->getAttribute('is_primary'),
        ])->all());

        return $snapshot;
    }

    /**
     * The field-level diff {field: {old, new}} between the profile and the
     * validated request; fields that would not change are left out.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function sensitiveDiff(Doctor $doctor, array $validated): array
    {
        $current = self::sensitiveSnapshot($doctor);
        $diff = [];

        foreach (self::SENSITIVE_ATTRIBUTES as $attribute) {
            if (! array_key_exists($attribute, $validated)) {
                continue;
            }

            $new = match ($attribute) {
                'years_experience' => $validated[$attribute] === null ? null : (int) $validated[$attribute],
                'education' => PlainText::paragraphs($validated[$attribute]),
                default => PlainText::line($validated[$attribute]),
            };

            if ($new !== $current[$attribute]) {
                $diff[$attribute] = ['old' => $current[$attribute], 'new' => $new];
            }
        }

        foreach ([
            'specialties' => ['specialty_ids', 'primary_specialty_id', Specialty::class],
            'facilities' => ['facility_ids', 'primary_facility_id', Facility::class],
        ] as $relation => [$idsKey, $primaryKey, $model]) {
            if (! array_key_exists($idsKey, $validated) && ! array_key_exists($primaryKey, $validated)) {
                continue;
            }

            /** @var list<array{id: int, name: string, is_primary: bool}> $old */
            $old = $current[$relation];
            $ids = array_key_exists($idsKey, $validated)
                ? array_values(array_unique(array_map('intval', (array) $validated[$idsKey])))
                : array_column($old, 'id');
            $currentPrimary = collect($old)->firstWhere('is_primary', true)['id'] ?? null;
            $primary = array_key_exists($primaryKey, $validated) ? $validated[$primaryKey] : $currentPrimary;
            $primary = $primary === null ? null : (int) $primary;

            if ($primary !== null && ! in_array($primary, $ids, true)) {
                $primary = null;
            }

            $names = $model::query()->whereKey($ids)->pluck('name', 'id');
            $new = self::linkList(array_map(fn (int $id): array => [
                'id' => $id,
                'name' => (string) ($names[$id] ?? ''),
                'is_primary' => $id === $primary,
            ], $ids));

            if (self::linkKey($new) !== self::linkKey($old)) {
                $diff[$relation] = ['old' => $old, 'new' => $new];
            }
        }

        return $diff;
    }

    /**
     * Write an approved diff to the profile. Whatever staff changed since the
     * request was made is overwritten only for the fields in the diff.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $diff
     */
    public static function applySensitive(Doctor $doctor, array $diff): void
    {
        $attributes = array_intersect_key(
            array_map(fn (array $change): mixed => $change['new'], $diff),
            array_flip(self::SENSITIVE_ATTRIBUTES),
        );

        if (array_key_exists('full_name', $attributes) && blank($attributes['full_name'])) {
            unset($attributes['full_name']);
        }

        if ($attributes !== []) {
            $doctor->forceFill($attributes)->save();
        }

        foreach (self::SENSITIVE_RELATIONS as $relation) {
            if (! isset($diff[$relation])) {
                continue;
            }

            $sync = [];

            foreach ((array) $diff[$relation]['new'] as $link) {
                $sync[(int) $link['id']] = ['is_primary' => (bool) $link['is_primary']];
            }

            // A request can wait for days: a specialty or workplace it adds
            // that staff have unpublished (or that is no longer clinical) since
            // is left out, so approval cannot link the profile to it.
            $current = $doctor->{$relation}()->pluck($relation.'.id')->map(fn ($id): int => (int) $id)->all();
            $added = array_diff(array_keys($sync), $current);

            if ($added !== []) {
                $still = ($relation === 'facilities'
                    ? Facility::query()->published()->clinical()
                    : Specialty::query()->published())
                    ->whereKey($added)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                foreach (array_diff($added, $still) as $gone) {
                    unset($sync[$gone]);
                }
            }

            $doctor->{$relation}()->sync($sync);

            activity('doctor_profile')
                ->performedOn($doctor)
                ->event('relations_updated')
                ->withProperties(['relation' => $relation, 'old' => $diff[$relation]['old'], 'new' => $diff[$relation]['new']])
                ->log('relations_updated');
        }

        if (isset($diff['specialties'])) {
            // As SyncsDoctorSpecialties: the pivot sync fires no model event.
            TaxonomyCache::flush(TaxonomyCache::SPECIALTIES);
            TaxonomyCache::flush(TaxonomyCache::HOME_HIGHLIGHTS);
            $doctor->unsetRelation('specialties')->searchable();
        }
    }

    /**
     * @param  array<int, array{id: int, name: string, is_primary: bool}>  $links
     * @return list<array{id: int, name: string, is_primary: bool}>
     */
    private static function linkList(array $links): array
    {
        usort($links, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $links;
    }

    /**
     * @param  list<array{id: int, name: string, is_primary: bool}>  $links
     */
    private static function linkKey(array $links): string
    {
        return implode(',', array_map(fn (array $link): string => $link['id'].($link['is_primary'] ? '*' : ''), $links));
    }
}
