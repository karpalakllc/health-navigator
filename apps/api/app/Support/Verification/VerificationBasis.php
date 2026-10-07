<?php

namespace App\Support\Verification;

use Illuminate\Support\Facades\Lang;

/**
 * Why a profile is „Верифициран“. The code is stored in
 * verification_basis and exposed in the API (`verification.basis`) with a
 * public Macedonian label (lang/{mk,en}/api.php verification.basis.*). The
 * evidence behind it stays internal (verification_reasons).
 */
enum VerificationBasis: string
{
    /** Doctor: ФЗОМ workplace + valid Комора licence + compatible specialty. Facility / pharmacy: an official register (ФЗОМ / Ministry) with matching tax no., name and town. */
    case OfficialRegisters = 'official_registers';

    /** Doctor on the institution's website staff page + unique valid Комора licence + compatible specialty. */
    case LicenceAndWebsite = 'licence_and_website';

    /** Doctor listed by both the institution's website and ФЗОМ at the same institution. */
    case WebsiteAndRegister = 'website_and_register';

    /** Checked by our staff (Filament „Verify“ or the engine's staff-confirmed rule). */
    case Staff = 'staff';

    /** The doctor claimed the profile and staff checked their identity. */
    case OwnerClaim = 'owner_claim';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Public label (the API locale decides). Doctors and facilities differ
     * only where the sources differ (ФЗОМ + Комора vs. a facility register);
     * a dentist verified by the ФЗОМ contract alone reads „Регистар на ФЗОМ“
     * (the other bases read as for doctors).
     *
     * @param  'doctor'|'dentist'|'facility'  $subject
     */
    public function publicLabel(string $subject = 'doctor'): string
    {
        $key = 'api.verification.basis.'.$subject.'.'.$this->value;

        if ($subject === 'dentist' && ! Lang::has($key)) {
            $key = 'api.verification.basis.doctor.'.$this->value;
        }

        return (string) __($key);
    }

    /** For the admin panel (English, with the public Macedonian wording). */
    public function label(): string
    {
        return match ($this) {
            self::OfficialRegisters => 'Official registers (ФЗОМ, Лекарска комора, Ministry)',
            self::LicenceAndWebsite => 'Valid licence + institution website',
            self::WebsiteAndRegister => 'Institution website + ФЗОМ',
            self::Staff => 'Checked by staff',
            self::OwnerClaim => 'Claimed by the doctor (identity checked)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $basis) {
            $options[$basis->value] = $basis->label();
        }

        return $options;
    }
}
