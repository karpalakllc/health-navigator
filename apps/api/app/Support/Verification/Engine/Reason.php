<?php

namespace App\Support\Verification\Engine;

/**
 * Why a profile is not verified: stable snake_case codes stored by the
 * VerificationWriter (verification_reasons.reason, internal) and counted by
 * `import:adjudicate --report`. The first that applies is recorded.
 * docs/verification.md explains each.
 */
final class Reason
{
    public const SUPPRESSED = 'suppressed';

    public const LICENCE_OFF_LIST = 'licence_off_list';

    public const LICENCE_EXPIRED = 'licence_expired';

    public const SOURCE_REMOVED = 'source_removed';

    public const NAME_MISMATCH = 'licence_name_differs';

    public const SPECIALTY_MISMATCH = 'specialty_mismatch';

    public const NO_SPECIALTY = 'no_specialty';

    public const AMBIGUOUS_NAME = 'ambiguous_name';

    public const STALE_SOURCE = 'stale_source';

    public const LOW_CONFIDENCE_SOURCE = 'low_confidence_source';

    public const SOURCES_DISAGREE = 'sources_disagree';

    public const DENTIST_SINGLE_SOURCE = 'dentist_single_source';

    public const NO_LICENCE = 'no_licence';

    public const NO_IMPORT_EVIDENCE = 'no_import_evidence';

    public const REGISTER_MISMATCH = 'register_mismatch';

    public const NOT_IN_REGISTER = 'not_in_register';

    public const NO_PHARMACY_REGISTER = 'no_pharmacy_register';

    /** @var array<string, string> code => one line for staff (the report) */
    public const DESCRIPTIONS = [
        self::SUPPRESSED => 'Removed on objection or deleted: never verified automatically',
        self::LICENCE_OFF_LIST => 'The attached licence is no longer on the Комора list',
        self::LICENCE_EXPIRED => 'The attached licence has expired',
        self::SOURCE_REMOVED => 'No longer in the latest ФЗОМ snapshot',
        self::NAME_MISMATCH => 'The licence holder\'s name differs from the profile name',
        self::SPECIALTY_MISMATCH => 'Licence specialty does not fit the profile\'s',
        self::NO_SPECIALTY => 'The profile has no specialty to compare with the licence specialist\'s',
        self::AMBIGUOUS_NAME => 'Another doctor of the same name could hold the licence',
        self::STALE_SOURCE => 'Only a website flagged as compromised or stale lists the doctor',
        self::LOW_CONFIDENCE_SOURCE => 'Only a website entry the research marked as uncertain lists the doctor',
        self::SOURCES_DISAGREE => 'ФЗОМ and the website list the doctor at different institutions or specialties',
        self::DENTIST_SINGLE_SOURCE => 'Dentist known only from a website, not from ФЗОМ (there is no public dental licence list)',
        self::NO_LICENCE => 'No licence on the Комора list for this name',
        self::NO_IMPORT_EVIDENCE => 'Entered by hand, no source evidence (staff verify it)',
        self::REGISTER_MISMATCH => 'Facility name, town or tax number differs from the ФЗОМ register',
        self::NOT_IN_REGISTER => 'Facility only known from its website, not in the ФЗОМ register',
        self::NO_PHARMACY_REGISTER => 'Pharmacy: no official pharmacy register is imported yet',
    ];
}
