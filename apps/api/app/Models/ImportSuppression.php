<?php

namespace App\Models;

use App\Support\Import\NameKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A doctor no import may create or publish again (docs/data-import.md §9):
 * removed after an upheld objection, or deleted by staff. Every importer
 * checks the active suppressions (ImportSuppressions) before it creates or
 * updates a doctor; the review queue's Publish refuses a suppressed one.
 *
 * @property list<string>|null $source_keys
 * @property Carbon|null $lifted_at
 */
class ImportSuppression extends Model
{
    public const REASON_OBJECTION = 'objection';

    public const REASON_DELETED = 'deleted';

    protected $fillable = [
        'doctor_id',
        'fzo_facsimile',
        'licence_number',
        'name_key_sorted',
        'city_key',
        'source_keys',
        'label',
        'reason',
        'profile_correction_id',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'source_keys' => 'array',
            'lifted_at' => 'datetime',
        ];
    }

    /**
     * Records (or refreshes) the active suppression of a doctor, with every
     * key the sources could use to bring the person back. An objection
     * outranks a plain deletion.
     */
    public static function forDoctor(Doctor $doctor, string $reason, ?User $by = null, ?int $correctionId = null): self
    {
        $suppression = static::query()->active()->where('doctor_id', $doctor->getKey())->first() ?? new self([
            'doctor_id' => $doctor->getKey(),
            'reason' => $reason,
            'created_by_id' => $by?->getKey(),
        ]);

        $sourceKeys = SourceRecord::query()
            ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
            ->where('subject_id', $doctor->getKey())
            ->get(['source', 'external_key'])
            ->map(fn (SourceRecord $record): string => $record->source.':'.$record->external_key)
            ->all();

        $suppression->fill([
            'fzo_facsimile' => $doctor->fzo_facsimile ?? $suppression->fzo_facsimile,
            'licence_number' => $doctor->licence_number ?? $suppression->licence_number,
            'name_key_sorted' => NameKey::sorted((string) $doctor->full_name) ?: $suppression->name_key_sorted,
            'city_key' => $doctor->city !== null ? NameKey::for((string) $doctor->city) : $suppression->city_key,
            'source_keys' => array_values(array_unique([...($suppression->source_keys ?? []), ...$sourceKeys])),
            'label' => mb_substr(trim((string) $doctor->full_name.($doctor->city ? ', '.$doctor->city : '')), 0, 255) ?: '#'.$doctor->getKey(),
        ]);

        if ($reason === self::REASON_OBJECTION) {
            $suppression->reason = self::REASON_OBJECTION;
            $suppression->profile_correction_id = $correctionId ?? $suppression->profile_correction_id;
        }

        $suppression->save();

        return $suppression;
    }

    public function lift(?User $by): void
    {
        $this->forceFill(['lifted_at' => now(), 'lifted_by_id' => $by?->getKey()])->save();
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null;
    }

    /**
     * @param  Builder<ImportSuppression>  $query
     * @return Builder<ImportSuppression>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at');
    }

    /**
     * @return BelongsTo<ProfileCorrection, $this>
     */
    public function profileCorrection(): BelongsTo
    {
        return $this->belongsTo(ProfileCorrection::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by_id');
    }
}
