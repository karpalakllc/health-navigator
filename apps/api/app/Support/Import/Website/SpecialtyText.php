<?php

namespace App\Support\Import\Website;

use App\Models\SpecialtyAlias;

/**
 * Institution websites describe a doctor's specialty in free text:
 * "Специјалист по општа хирургија; супспецијалист по пластична хирургија",
 * "Специјалист хирург-уролог", "Офталмолог", "Кардиологија (супспецијалист)".
 * This reduces such text to specialty wordings the catalogue knows
 * ("ОПШТА ХИРУРГИЈА", "УРОЛОГИЈА"…). Rank words alone ("специјалист",
 * "специјализант", "шеф на оддел") carry no specialty and are dropped.
 */
final class SpecialtyText
{
    /** Job titles that are not a specialty. */
    private const IGNORED = ['', 'ШЕФ НА ОДДЕЛ', 'ДИРЕКТОР', 'РАКОВОДИТЕЛ', 'НАЧАЛНИК', 'МЕДИЦИНА'];

    /** Practitioner nouns → the field's wording. */
    private const NOUNS = [
        'ХИРУРГ' => 'ОПШТА ХИРУРГИЈА',
        'ИНТЕРНИСТ' => 'ИНТЕРНА МЕДИЦИНА',
        'АКУШЕР' => 'АКУШЕРСТВО И ГИНЕКОЛОГИЈА',
        'ГИНЕКОЛОГ' => 'АКУШЕРСТВО И ГИНЕКОЛОГИЈА',
        'НЕВРОХИРУРГ' => 'НЕВРОХИРУРГИЈА',
        'КАРДИОХИРУРГ' => 'КАРДИОХИРУРГИЈА',
        'ПЛАСТИЧЕН ХИРУРГ' => 'ПЛАСТИЧНА И РЕКОНСТРУКТИВНА ХИРУРГИЈА',
        'ТОРАКАЛЕН ХИРУРГ' => 'ТОРАКАЛОВАСКУЛАРНА ХИРУРГИЈА',
        'ДЕТСКИ ХИРУРГ' => 'ДЕТСКА ХИРУРГИЈА',
        'ПСИХИЈАТАР' => 'ПСИХИЈАТРИЈА',
        'ФИЗИЈАТАР' => 'ФИЗИКАЛНА МЕДИЦИНА И РЕХАБИЛИТАЦИЈА',
        'СТОМАТОЛОГ' => 'СТОМАТОЛОГ',
        'ОРТОДОНТ' => 'ОРТОДОНЦИЈА',
        'ДОКТОР НА МЕДИЦИНА' => 'ОПШТА МЕДИЦИНА',
        'МЕДИЦИНА' => 'ОПШТА МЕДИЦИНА',
    ];

    /** Wordings the register never uses but websites do. */
    public const EXTRA_ALIASES = [
        'АНЕСТЕЗИОЛОГИЈА' => 'anesteziologija',
        'АНЕСТЕЗИОЛОГИЈА СО ИНТЕНЗИВНО ЛЕКУВАЊЕ' => 'anesteziologija',
        'АНЕСТЕЗИОЛОГИЈА СО РЕАНИМАТОЛОГИЈА' => 'anesteziologija',
        'АНЕСТЕЗИЈА СО ИНТЕНЗИВНО ЛЕКУВАЊЕ' => 'anesteziologija',
        'АНЕСТЕЗИЈА' => 'anesteziologija',
        'ГИНЕКОЛОГИЈА' => 'ginekologija',
        'ГИНЕКОЛОГИЈА И АКУШЕРСТВО' => 'ginekologija',
        'МИКРОБИОЛОГИЈА' => 'mikrobiologija',
        'МЕДИЦИНСКА МИКРОБИОЛОГИЈА' => 'mikrobiologija',
        'БОЛЕСТИ НА УСТАТА И ПАРОДОНТОТ' => 'stomatologija-parodontologija',
        'ПАРОДОНТОЛОГИЈА' => 'stomatologija-parodontologija',
        'АБДОМИНАЛНА ХИРУРГИЈА' => 'opshta-hirurgija',
        'ХИРУРГИЈА' => 'opshta-hirurgija',
        'НЕВРОПСИХИЈАТРИЈА' => 'psihijatrija',
        'ХИГИЕНА' => 'higiena',
        'СОЦИЈАЛНА МЕДИЦИНА' => 'socijalna-medicina',
        'ТОРАКАЛНА ХИРУРГИЈА' => 'torakalna-hirurgija',
        'ТОРАКАЛНА И ВАСКУЛАРНА ХИРУРГИЈА' => 'torakalna-hirurgija',
        'ПЛАСТИЧНА ХИРУРГИЈА' => 'plastichna-hirurgija',
        'ДЕРМАТОЛОГИЈА' => 'dermatologija',
        'ДЕРМАТОВЕНЕРОЛОГИЈА' => 'dermatologija',
        'ГАСТРОЕНТЕРОЛОГИЈА' => 'gastroenterologija',
        'ОНКОЛОГИЈА' => 'onkologija',
        'ПУЛМОЛОГИЈА' => 'pulmologija',
        'НЕОНАТОЛОГИЈА' => 'pedijatrija',
        'ОРТОПЕДИЈА И ТРАУМАТОЛОГИЈА' => 'ortopedija',
        'ОТОРИНОЛАРИНГОЛОГИЈА' => 'otorinolaringologija',
        'ДЕТСКА СТОМАТОЛОГИЈА' => 'stomatologija-detska',
        'ПРОТЕТИКА' => 'stomatologija-protetika',
        'ЕНДОДОНЦИЈА' => 'stomatologija-endodoncija',
        'ВАСКУЛАРНА ХИРУРГИЈА' => 'vaskularna-hirurgija',
        'ДИГЕСТИВНА ХИРУРГИЈА' => 'opshta-hirurgija',
        'ЕНДОКРИНОЛОГИЈА' => 'endokrinologija',
        'АКУШЕРСТВО' => 'ginekologija',
        'ТРАНСФУЗИОЛОГИЈА' => 'transfuziona-medicina',
        'АНЕСТЕЗИОЛОГИЈА И РЕАНИМАЦИЈА' => 'anesteziologija',
        'АНЕСТЕЗИОЛОГИЈА И ИНТЕНЗИВНО ЛЕКУВАЊЕ' => 'anesteziologija',
        'АНЕСТЕЗИОЛОГИЈА СО РЕАНИМАЦИЈА' => 'anesteziologija',
        'АНЕСТЕЗИОЛОГИЈА СО РЕАНИМАЦИЈА И ИНТЕНЗИВНО ЛЕКУВАЊЕ' => 'anesteziologija',
        'РЕАНИМАЦИЈА И ИНТЕНЗИВНО ЛЕКУВАЊЕ' => 'anesteziologija',
        'ОПШТ СТОМАТОЛОГ' => 'stomatologija',
        'ДЕНТАЛНА МЕДИЦИНА' => 'stomatologija',
        'ПУЛМОАЛЕРГОЛОГИЈА' => 'pulmologija',
        'АЛЕРГОЛОГИЈА' => 'pulmologija',
        'ХИГИЕНА И ЗДРАВСТВЕНА ЕКОЛОГИЈА' => 'higiena',
        'МЕДИЦИНСКА МИКРОБИОЛОГИЈА И ПАРАЗИТОЛОГИЈА' => 'mikrobiologija',
        'ОНКОЛОГИЈА И РАДИОТЕРАПИЈА' => 'onkologija',
        'ОРТОПЕД' => 'ortopedija',
        'ОРЛ' => 'otorinolaringologija',
        'ОРЛ ХИРУРГ' => 'otorinolaringologija',
        'РАДИОДИЈАГНОСТИЧАР' => 'radiologija',
        'МЕДИЦИНА НА ТРУД' => 'medicina-na-trudot',
        'ТРУДОВА МЕДИЦИНА' => 'medicina-na-trudot',
        'ДЕТСКА НЕВРОЛОГИЈА' => 'nevrologija',
        'ГРАДНА ХИРУРГИЈА' => 'torakalna-hirurgija',
        'НЕОНАТАЛОГИЈА' => 'pedijatrija',
        'ДЕРМАТО-ВЕНЕРОЛОГИЈА' => 'dermatologija',
        'ВАСКУЛАРЕН ХИРУРГ' => 'vaskularna-hirurgija',
        'ИНФЕКТИВНИ БОЛЕСТИ' => 'infektologija',
        'ОРТОПЕДСКА ХИРУРГИЈА И ТРАУМАТОЛОГИЈА' => 'ortopedija',
    ];

    /**
     * Wordings of the first real website import (2026-10) that nothing
     * mapped, reviewed one by one and mapped only where the meaning is
     * certain (as the parser leaves them: rank words stripped, split at
     * commas). A subspecialty on a base specialty takes the precise field
     * the catalogue has („Интерна медицина - пневмофтизиолог“ →
     * pulmologija), a paediatric subspecialty stays paediatrics, a doctor in
     * specialisation keeps the specialty held today (general medicine,
     * general dentistry). Left unmapped on purpose (listed in
     * docs/data-import.md §7): job titles, degrees, specialisations in
     * progress, fragments, fields the catalogue lacks, and two unrelated
     * specialties in one wording. Plain spelling here; extraAliases() keys
     * them the way SpecialtyAlias::keyFor does (Latin look-alikes).
     */
    public const WEBSITE_WORDINGS = [
        // Internal medicine and its subspecialties
        'ВНАТРЕШНИ БОЛЕСТИ' => 'interna-medicina',
        'INTERNAL DISEASES' => 'interna-medicina',
        'СПЕЦ. ИНТЕРНИСТ' => 'interna-medicina',
        'КАРДИОЛОГ - СПЕЦ. ИНТЕРНИСТ' => 'kardiologija',
        'ИНТЕРВЕНТЕН КАРДИОЛОГ' => 'kardiologija',
        'КАРДИОАНГИОЛОГИЈА' => 'kardiologija',
        'ИНТЕРНА МЕДИЦИНА - ПНЕВМОФТИЗИОЛОГ' => 'pulmologija',
        'ИНТЕРНА МЕДИЦИНА - ПНЕУМОФТИЗИОЛОГ' => 'pulmologija',
        'ИНТЕРНА МЕДИЦИНА И ХЕМАТОЛОГИЈА' => 'hematologija',
        'НЕФРОЛОГ.' => 'nefrologija',
        'ВАСКУЛАРЕН НЕФРОЛОГ' => 'nefrologija',
        // Paediatrics (a paediatric subspecialty stays paediatrics)
        'СПЕЦ. ПЕДИЈАТАР' => 'pedijatrija',
        'PEDIATRY' => 'pedijatrija',
        'ПЕДИЈАТРИЈА - ПУЛМОЛОГ' => 'pedijatrija',
        'ПЕДИЈАТРИЈА - НЕВРОЛОГ' => 'pedijatrija',
        'ПЕДИЈАТРИЈА - НЕФРОЛОГ' => 'pedijatrija',
        'ПЕДИЈАТРИЈА - КАРДИОЛОГ' => 'pedijatrija',
        'ПЕДИЈАТРИЈА - ФЕТАЛНА ЕХОКАРДИОГРАФИЈА' => 'pedijatrija',
        'ПЕДИЈАТРИЈА И ПУЛМО-АЛЕРГОЛОГИЈА' => 'pedijatrija',
        'ПЕДИЈАТРИСКА КАРДИОЛОГИЈА' => 'pedijatrija',
        'ПЕДИЈАТРИСКА НЕФРОЛОГИЈА' => 'pedijatrija',
        'ПЕДИЈАТРИСКИ ПУЛМОАЛЕРГОЛОГ' => 'pedijatrija',
        'ДЕТСКА ЕНДОКРИНОЛОГИЈА И ГЕНЕТИКА' => 'pedijatrija',
        // Obstetrics and gynaecology
        'СПЕЦ. ГИНЕКОЛОГ' => 'ginekologija',
        'ГИНЕКОЛОГ АКУШЕР' => 'ginekologija',
        'ГИНЕКОЛОГ-АКУШЕР И ИВФ СПЕЦИЈАЛИСТ' => 'ginekologija',
        'ОБСТЕТРИЦИЈА И ГИНЕКОЛОГИЈА' => 'ginekologija',
        'GYNECOLOGY & OBSTETRICS' => 'ginekologija',
        'ГИНЕКОЛОШКА ХИРУРШКА ОНКОЛОГИЈА' => 'ginekologija',
        // Surgery (a precise field wins over „општа хирургија“, as in wordings())
        'ОПШТ ХИРУРГ' => 'opshta-hirurgija',
        'АБДОМЕНАЛЕН ХИРУРГ' => 'opshta-hirurgija',
        'ХИРУРГ ТРАУМАТОЛОГ' => 'traumatologija',
        'ХИРУРГИЈА И ТРАУМАТОЛОГИЈА' => 'traumatologija',
        'ОПШТА ХИРУРГИЈА И ТРАУМАТОЛОГИЈА' => 'traumatologija',
        'ОПШТА И ВАСКУЛАРНА ХИРУРГИЈА' => 'vaskularna-hirurgija',
        'УРОЛОГИЈА И ОПШТА ХИРУРГИЈА' => 'urologija',
        'ДЕТСКА ХИРУРГИЈА И ОПШТА ХИРУРГИЈА' => 'detska-hirurgija',
        'КАРДИОВАСКУЛАРЕН ХИРУРГ' => 'kardiohirurgija',
        'ТОРАКАЛЕН' => 'torakalna-hirurgija',
        'ПЛАСТИЧНА' => 'plastichna-hirurgija',
        'РЕКОНСТРУКТИВНА И ЕСТЕТСКА ХИРУРГИЈА' => 'plastichna-hirurgija',
        'ОРТОПЕД СУПСПЕЦИЈАЛИСТ ПО АРТРОСКОПСКА ХИРУРГИЈА' => 'ortopedija',
        'МАКСИЛОФАЦИЈАЛЕН ХИРУРГ' => 'maksilofacijalna-hirurgija',
        'SPECIALIST OF MAXILLOFACIAL SURGERY' => 'maksilofacijalna-hirurgija',
        // Eyes, ENT
        'ОФТАЛМОХИРУРГ' => 'oftalmologija',
        'OPTHALMOLOGY' => 'oftalmologija',
        'СПЕЦ. ОФТАЛМОЛОГ И ХИРУРГ' => 'oftalmologija',
        'ПРОФ. СПЕЦ. ОФТАЛМОЛОГ И ХИРУРГ' => 'oftalmologija',
        'ДОЦ. СПЕЦ. ОФТАЛМОЛОГ И ХИРУРГ' => 'oftalmologija',
        'ПРОФ. СПЕЦ. ОФТАЛМОЛОГ' => 'oftalmologija',
        'ОРЛ ХИРУРГ-ФАРИНГОЛАРИНГОЛОГ' => 'otorinolaringologija',
        // Anaesthesiology, radiology, other single fields
        'АНЕСТЕЗИОЛОГИЈА И РЕАНИМАТОЛОГИЈА' => 'anesteziologija',
        'РЕАНИМАТОЛОГИЈА' => 'anesteziologija',
        'ANESTHESIOLOGY & REANIMATION' => 'anesteziologija',
        'СУПСПЕЦ. ИНТЕРВЕНТНА РАДИОЛОГИЈА' => 'radiologija',
        'СУПСПЕЦ. ИНТЕРЕВЕНТНА РАДИОЛОГИЈА' => 'radiologija',
        'ИНТЕРЕВЕНТНА РАДИОЛОГИЈА' => 'radiologija',
        'ТРАНСФУЗИОНА МЕДИЦИНА' => 'transfuziona-medicina',
        'ФИЗИКАЛНА МЕДИЦИНА' => 'fizikalna-medicina',
        'СОЦИЈАЛНА МЕДИЦИНА СО ОРГАНИЗАЦИЈА НА ЗДРАВСТВЕНА ДЕЈНОСТ' => 'socijalna-medicina',
        // General practitioners, incl. doctors in specialisation
        'MJEKE E PERGJITHSHME' => 'opshta-medicina',
        'ОПШТА МЕДИЦИНА - СПЕЦИЈАЛИЗАНТ ПО ПУЛМОЛОГИЈА' => 'opshta-medicina',
        'ОПШТА МЕДИЦИНА - СПЕЦИЈАЛИЗАНТ ПО РАДИОЛОГИЈА' => 'opshta-medicina',
        'ОПШТА МЕДИЦИНА - СПЕЦИЈАЛИЗАНТ ПО ОПШТА ХИРУРГИЈА' => 'opshta-medicina',
        // Dentistry
        'GENERAL DENTIST' => 'stomatologija',
        'LEAD DENTIST' => 'stomatologija',
        'DOCTOR OF DENTISTRY' => 'stomatologija',
        'STOMATOLOG' => 'stomatologija',
        'COSMETIC & LASER DENTIST' => 'stomatologija',
        'ORAL HEALTH SPECIALIST' => 'stomatologija',
        'ORAL HEALTH SPECIALIST & LASER DENTIST' => 'stomatologija',
        'IMPLANTOLOGIST' => 'stomatologija',
        'ИМПЛАНТОЛОГИЈА' => 'stomatologija',
        'ОПШТА И ЕСТЕТСКА СТОМАТОЛОГИЈА' => 'stomatologija',
        'ОПШТА И ПРЕВЕНТИВНА СТОМАТОЛОГИЈА' => 'stomatologija',
        'АКТУЕЛЕН СПЕЦИЈАЛИЗАНТ ПО ПРОТЕТИКА' => 'stomatologija',
        'АКТУЕЛЕН СПЕЦИЈАЛИЗАНТ ПО ПАРОДОНТОЛОГИЈА' => 'stomatologija',
        'TRAINEE IN PERIODONTOLOGY' => 'stomatologija',
        'СПЕЦИЈАЛИЗИРА ОРТОДОНЦИЈА' => 'stomatologija',
        'ORTHODONTIST' => 'stomatologija-ortodoncija',
        'ORTODONT' => 'stomatologija-ortodoncija',
        'ОРТОДОНЦИЈА И ТЕМПОРОМАНДИБУЛАРНА ДИСФУНКЦИЈА' => 'stomatologija-ortodoncija',
        'ОРТОДОНЦИЈА И ЕСТЕТСКИ КОРОНКИ' => 'stomatologija-ortodoncija',
        'СТОМАТОЛОШКА ПРОТЕТИКА И ИМПЛАНТОПРОТЕТИКА' => 'stomatologija-protetika',
        'ORAL SURGEON' => 'stomatologija-oralna-hirurgija',
        'ОРАЛЕН ХИРУРГ И ИМПЛАНТОЛОГ' => 'stomatologija-oralna-hirurgija',
        'СТОМАТОЛОГ-ОРАЛЕН ХИРУРГ' => 'stomatologija-oralna-hirurgija',
        'ОРАЛНА ХИРУРГИЈА И ИМПЛАНТОЛОГИЈА' => 'stomatologija-oralna-hirurgija',
        'СПЕЦИЈАЛИЗИРАН ДОКТОР ПО МИКРОСКОПСКА ЕНДОДОНЦИЈА' => 'stomatologija-endodoncija',
        'SPECIALIST OF ENDODONTICS' => 'stomatologija-endodoncija',
        'ENDODONTIST & LASER DENTIST' => 'stomatologija-endodoncija',
        'ФАЦИЈАЛНА ЕСТЕТИКА И ЕНДОДОНЦИЈА' => 'stomatologija-endodoncija',
        'ЕНДОДОНЦИЈА И РЕСТАВРАЦИСКА СТОМАТОЛОГИЈА' => 'stomatologija-endodoncija',
        'ДЕТСКА И РЕСТАВРАЦИСКА СТОМАТОЛОГИЈА' => 'stomatologija-detska',
    ];

    /**
     * EXTRA_ALIASES and WEBSITE_WORDINGS keyed as SpecialtyAlias::keyFor
     * keys a wording: what the website importer consults before the ФЗОМ
     * catalogue (and the 2026_10_16_130000 data migration applies to the
     * aliases stored before).
     *
     * @return array<string, string> wording key => our specialty slug
     */
    public static function extraAliases(): array
    {
        $aliases = self::EXTRA_ALIASES;

        foreach (self::WEBSITE_WORDINGS as $wording => $slug) {
            $aliases[SpecialtyAlias::keyFor($wording)] = $slug;
        }

        return $aliases;
    }

    /**
     * @return list<string> wordings (SpecialtyAlias::keyFor form), best first
     */
    public static function wordings(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $upper = SpecialtyAlias::keyFor((string) preg_replace('/\([^)]*\)/u', ' ', $text));
        $parts = preg_split('/\s*(?:;|,|\/|\s[—–]\s|\sИ\s(?=(?:СУ[ПБ]?С?ПЕЦИЈАЛИСТ|СПЕЦИЈАЛИСТ)))\s*/u', $upper) ?: [];
        $wordings = [];

        foreach ($parts as $part) {
            $part = trim((string) preg_replace('/^(?:(?:СУПСПЕЦИЈАЛИСТ|СУБСПЕЦИЈАЛИСТ|СУСПЕЦИЈАЛИСТ|СУБСПЕЦ\.?|СПЕЦИЈАЛИСТ|СПЕЦИЈАЛИЗАНТ|ДОКТОР|Д-Р|ЛЕКАР)(?:\s+(?:ПО|НА|ЗА))?[\s:—–-]*)+/u', '', trim($part)));

            if (in_array($part, self::IGNORED, true)) {
                continue;
            }

            // "ХИРУРГ-УРОЛОГ", "ГИНЕКОЛОГ-АКУШЕР", "ОФТАЛМОЛОГ И ХИРУРГ": each noun.
            // Only practitioner nouns are split, never a field name
            // ("ГИНЕКОЛОГИЈА И АКУШЕРСТВО", "ДЕРМАТО-ВЕНЕРОЛОГИЈА").
            $pieces = preg_match('/^[\p{L}]+(?:\s*-\s*|\s+И\s+)[\p{L}]+$/u', $part) === 1 && preg_match('/(?:ИЈА|СТВО|ИНА)\b/u', $part) !== 1
                ? preg_split('/\s*-\s*|\s+И\s+/u', $part) ?: [$part]
                : [$part];

            foreach ($pieces as $piece) {
                $wording = self::fromNoun(trim($piece));

                if (! in_array($wording, self::IGNORED, true) && ! in_array($wording, $wordings, true)) {
                    $wordings[] = $wording;
                }
            }
        }

        // A generic "општа хирургија" next to a precise surgical field adds nothing.
        if (count($wordings) > 1) {
            $wordings = array_values(array_filter($wordings, fn (string $w): bool => $w !== 'ОПШТА ХИРУРГИЈА')) ?: $wordings;
        }

        return $wordings;
    }

    private static function fromNoun(string $word): string
    {
        if (isset(self::NOUNS[$word])) {
            return self::NOUNS[$word];
        }

        // ОФТАЛМОЛОГ → ОФТАЛМОЛОГИЈА, УРОЛОГ → УРОЛОГИЈА, КАРДИОЛОГ → КАРДИОЛОГИЈА.
        if (preg_match('/^[\p{L}]+ЛОГ$/u', $word) === 1) {
            return $word.'ИЈА';
        }

        return $word;
    }
}
