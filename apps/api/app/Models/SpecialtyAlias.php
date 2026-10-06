<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A source's wording of a specialty, mapped to ours. specialty_id null and
 * is_excluded false = not mapped yet (the import reports it as unmatched);
 * is_excluded = a profession we do not list (pharmacist, psychologist,
 * speech therapist…): a person with only excluded specialties is skipped.
 */
class SpecialtyAlias extends Model
{
    protected $fillable = [
        'source',
        'raw',
        'raw_key',
        'specialty_id',
        'is_excluded',
    ];

    protected function casts(): array
    {
        return [
            'is_excluded' => 'boolean',
        ];
    }

    public static function keyFor(string $raw): string
    {
        $upper = mb_strtoupper(trim($raw), 'UTF-8');
        // Latin look-alikes typed into Cyrillic words ("ОПШТA").
        $upper = strtr($upper, ['A' => 'А', 'E' => 'Е', 'O' => 'О', 'C' => 'С', 'K' => 'К', 'M' => 'М', 'T' => 'Т', 'H' => 'Н', 'P' => 'Р', 'X' => 'Х', 'J' => 'Ј', 'B' => 'В']);

        return trim((string) preg_replace('/\s+/u', ' ', $upper));
    }

    /**
     * @return BelongsTo<Specialty, $this>
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
