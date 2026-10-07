<?php

namespace App\Support\Verification\Engine;

use App\Support\Verification\VerificationBasis;

/**
 * The rules under which the engine verifies a profile on its own. Each one
 * needs two independent sources that agree (or a person who checked the
 * identity). docs/verification.md lists them with their evidence.
 */
enum VerificationRule: string
{
    /** Doctor: current ФЗОМ contract + valid Комора licence, same name, compatible specialty, no other fitting namesake. */
    case FzomLicence = 'fzom_licence';

    /**
     * Dentist: a current ФЗОМ contract alone (owner's decision, 2026-10). There is no public
     * dental licence list; ФЗОМ contracts only licensed dentists, and leaving ФЗОМ removes it.
     */
    case FzomDentist = 'fzom_dentist';

    /** Doctor: the institution's own staff page + valid Комора licence, name unique on the list, compatible specialty. */
    case WebsiteLicence = 'website_licence';

    /** Doctor: current ФЗОМ contract at the same institution whose own staff page lists them, specialties not contradicting. */
    case FzomWebsite = 'fzom_website';

    /** Doctor: staff linked a member account to the profile after checking the person's identity. */
    case OwnerClaim = 'owner_claim';

    /** Facility: current in the ФЗОМ register, with the register's tax number, name and town. */
    case FacilityRegister = 'fzom_register';

    public function basis(): VerificationBasis
    {
        return match ($this) {
            self::FzomLicence, self::FzomDentist, self::FacilityRegister => VerificationBasis::OfficialRegisters,
            self::WebsiteLicence => VerificationBasis::LicenceAndWebsite,
            self::FzomWebsite => VerificationBasis::WebsiteAndRegister,
            self::OwnerClaim => VerificationBasis::OwnerClaim,
        };
    }
}
