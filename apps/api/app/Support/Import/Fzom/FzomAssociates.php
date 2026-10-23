<?php

namespace App\Support\Import\Fzom;

/**
 * Health associates („здравствени соработници“): laboratory biologists and
 * chemists, psychologists, speech therapists, defectologists, social
 * workers, pharmacists, radiology technologists. ФЗОМ gives them facsimile
 * codes in a range of their own (8xxxxx; physicians have 7xxxxx and
 * dentists other ranges) and they are not doctors.
 *
 * A person whose facsimile starts with one of
 * `import.fzom.associate_facsimile_prefixes` is never imported as a doctor.
 * Neither the contract-type nor the work-unit activity (`Dejnost`)
 * specialty fallback applies to them: those describe the unit, not the
 * person. The one exception is a specialty wording of their own that only a
 * physician can hold; that profile is imported as a hidden draft and staff
 * confirm it.
 */
final class FzomAssociates
{
    /**
     * Specialties that people who are not physicians also hold (laboratory
     * and public-health specialisations open to biologists, chemists,
     * pharmacists and sanitary engineers). A wording mapped to one of these
     * on an associate code does not make the person a physician.
     */
    public const OPEN_TO_NON_PHYSICIANS = [
        'medicinska-genetika',
        'mikrobiologija',
        'medicinska-biohemija',
        'klinichka-farmakologija',
        'imunologija',
        'higiena',
        'epidemiologija',
    ];

    public static function isAssociateFacsimile(?string $facsimile): bool
    {
        if ($facsimile === null || $facsimile === '') {
            return false;
        }

        foreach (self::prefixes() as $prefix) {
            if (str_starts_with($facsimile, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return array_values(array_filter(
            array_map(fn ($prefix): string => trim((string) $prefix), (array) config('import.fzom.associate_facsimile_prefixes')),
            fn (string $prefix): bool => $prefix !== '',
        ));
    }

    /**
     * Only a physician (or dentist) can hold it.
     */
    public static function isPhysicianOnlySlug(string $slug): bool
    {
        return ! in_array($slug, self::OPEN_TO_NON_PHYSICIANS, true);
    }
}
