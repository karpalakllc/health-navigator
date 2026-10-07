<?php

namespace App\Support\Verification\Engine;

/**
 * A facility (or pharmacy) is verified when an official register lists it
 * today with the tax number, name and town the profile shows. The ФЗОМ
 * register is the one imported; pharmacies are not in it (ФЗОМ's pharmacy
 * contracts are deliberately not imported), so they wait for staff or a
 * future register import.
 */
final class FacilityRules
{
    public function decide(FacilityEvidence $e): Verdict
    {
        foreach ($e->register as $record) {
            if ($record->agrees()) {
                return Verdict::verified(VerificationRule::FacilityRegister, ['source_record_id' => $record->recordId]);
            }
        }

        $current = array_values(array_filter($e->register, fn (RegisterFact $record): bool => $record->current));

        return match (true) {
            $current !== [] => Verdict::unverified(Reason::REGISTER_MISMATCH, [
                'source_record_id' => $current[0]->recordId,
                'tax_number_matches' => $current[0]->taxNumberMatches,
                'name_matches' => $current[0]->nameMatches,
                'town_matches' => $current[0]->townMatches,
            ]),
            $e->register !== [] => Verdict::unverified(Reason::SOURCE_REMOVED, ['source_record_id' => $e->register[0]->recordId]),
            $e->pharmacy => Verdict::unverified(Reason::NO_PHARMACY_REGISTER),
            $e->fromWebsite => Verdict::unverified(Reason::NOT_IN_REGISTER),
            default => Verdict::unverified(Reason::NO_IMPORT_EVIDENCE),
        };
    }
}
