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
