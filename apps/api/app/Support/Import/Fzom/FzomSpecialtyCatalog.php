<?php

namespace App\Support\Import\Fzom;

/**
 * Starting map from the ФЗОМ specialty wording (`Specijalnosti`, one or more
 * comma-separated items) to our specialty catalogue.
 *
 * It is only a default: the first time the importer meets a wording it
 * stores an alias row from this map (specialty_aliases, source "fzom"), and
 * from then on the alias row wins, so staff corrections survive. A wording
 * neither here nor matched by a prefix rule is stored unmapped and reported.
 *
 * EXCLUDED wordings are professions we do not list as doctors (pharmacists,
 * psychologists, speech therapists, engineers, technologists…). A person
 * whose every specialty is excluded is not imported at all.
 */
final class FzomSpecialtyCatalog
{
    public const EXCLUDED = '-';

    /**
     * Our specialties: slug => name. Existing slugs (kardiologija,
     * ginekologija, pedijatrija, dermatologija, ortopedija) are reused.
     *
     * @var array<string, string>
     */
    public const SPECIALTIES = [
        'opshta-medicina' => 'Општа медицина',
        'semejna-medicina' => 'Семејна медицина',
        'ginekologija' => 'Гинекологија',
        'interna-medicina' => 'Интерна медицина',
        'pedijatrija' => 'Педијатрија',
        'anesteziologija' => 'Анестезиологија и интензивно лекување',
        'psihijatrija' => 'Психијатрија',
        'detska-psihijatrija' => 'Детска и адолесцентна психијатрија',
        'nevrologija' => 'Неврологија',
        'oftalmologija' => 'Офталмологија',
        'otorinolaringologija' => 'Оториноларингологија',
        'opshta-hirurgija' => 'Општа хирургија',
        'ortopedija' => 'Ортопедија',
        'traumatologija' => 'Трауматологија',
        'fizikalna-medicina' => 'Физикална медицина и рехабилитација',
        'medicinska-biohemija' => 'Медицинска биохемија',
        'dermatologija' => 'Дерматологија',
        'radiologija' => 'Радиологија',
        'kardiologija' => 'Кардиологија',
        'urologija' => 'Урологија',
        'infektologija' => 'Инфектологија',
        'transfuziona-medicina' => 'Трансфузиона медицина',
        'nevrohirurgija' => 'Неврохирургија',
        'mikrobiologija' => 'Медицинска микробиологија',
        'onkologija' => 'Радиотерапија и онкологија',
        'gastroenterologija' => 'Гастроентерохепатологија',
        'medicina-na-trudot' => 'Медицина на трудот',
        'pulmologija' => 'Пулмологија',
        'epidemiologija' => 'Епидемиологија',
        'patologija' => 'Патологија',
        'detska-hirurgija' => 'Детска хирургија',
        'maksilofacijalna-hirurgija' => 'Максилофацијална хирургија',
        'plastichna-hirurgija' => 'Пластична и реконструктивна хирургија',
        'nefrologija' => 'Нефрологија',
        'nuklearna-medicina' => 'Нуклеарна медицина',
        'sportska-medicina' => 'Спортска медицина',
        'endokrinologija' => 'Ендокринологија',
        'revmatologija' => 'Ревматологија',
        'hematologija' => 'Хематологија',
        'sudska-medicina' => 'Судска медицина',
        'urgentna-medicina' => 'Ургентна медицина',
        'kardiohirurgija' => 'Кардиохирургија',
        'vaskularna-hirurgija' => 'Васкуларна хирургија',
        'torakalna-hirurgija' => 'Торакална хирургија',
        'klinichka-farmakologija' => 'Клиничка фармакологија',
        'medicinska-genetika' => 'Медицинска генетика',
        'uchilishna-medicina' => 'Училишна медицина',
        'higiena' => 'Хигиена со здравствена екологија',
        'socijalna-medicina' => 'Социјална медицина',
        'imunologija' => 'Имунологија',
        // Dentistry: every dental specialty starts with this slug prefix.
        'stomatologija' => 'Стоматологија',
        'stomatologija-ortodoncija' => 'Ортодонција',
        'stomatologija-protetika' => 'Стоматолошка протетика',
        'stomatologija-oralna-hirurgija' => 'Орална хирургија',
        'stomatologija-detska' => 'Детска и превентивна стоматологија',
        'stomatologija-parodontologija' => 'Пародонтологија и орална медицина',
        'stomatologija-endodoncija' => 'Ендодонција',
    ];

    public const DENTAL_SLUG_PREFIX = 'stomatologija';

    /**
     * Wording (SpecialtyAlias::keyFor) => our slug, or EXCLUDED.
     *
     * @var array<string, string>
     */
    public const ALIASES = [
        'ОПШТА МЕДИЦИНА' => 'opshta-medicina',
        'БЕЗ СПЕЦИЈАЛНОСТ' => 'opshta-medicina',
        'ПРИМАРНА ЗДРАВСТВЕНА ЗАШТИТА' => 'opshta-medicina',
        'СЕМЕЈНА МЕДИЦИНА' => 'semejna-medicina',
        'АКУШЕРСТВО И ГИНЕКОЛОГИЈА' => 'ginekologija',
        'ГИНЕКОЛОГИЈА И АКУШЕРСТВО' => 'ginekologija',
        'ПЕРИНАТОЛОГИЈА' => 'ginekologija',
        'УРОГИНЕКОЛОГИЈА' => 'ginekologija',
        'ИНТЕРНА МЕДИЦИНА' => 'interna-medicina',
        'ИНТЕРНИСТ ОД ЦЕНТАР ЗА АСТМА И НОВВ' => 'interna-medicina',
        'ПЕДИЈАТАР' => 'pedijatrija',
        'ПЕДИЈАТРИЈА' => 'pedijatrija',
        'БОЛНИЧКИ ПЕДИЈАТАР' => 'pedijatrija',
        'СПЕЦИЈАЛИСТ ОД ЦЕНТАР ЗА ЦИСТИЧНА ФИБРОЗА' => 'pedijatrija',
        'АНЕСТЕЗИОЛОГИЈА СО ИНТЕНЗИВНА МЕДИЦИНА' => 'anesteziologija',
        'АНЕСТЕЗИОЛОГИЈА СО РЕАНИМАЦИЈА И ИНТЕНЗИВНИ ЛЕКУВАЊЕ' => 'anesteziologija',
        'ИНТЕНЗИВНО ЛЕКУВАЊЕ НА КРИТИЧНО БОЛНИ ПАЦИЕНТИ' => 'anesteziologija',
        'ПСИХИЈАТРИЈА' => 'psihijatrija',
        'НЕВРОПСИХИЈАТАР' => 'psihijatrija',
        'СОЦИЈАЛНА ПСИХИЈАТРИЈА' => 'psihijatrija',
        'СУДСКА ПСИХИЈАТРИЈА' => 'psihijatrija',
        'БОЛЕСТИ НА ЗАВИСНОСТ' => 'psihijatrija',
        'ДЕТСКА И АДОЛЕСЦЕНТНА ПСИХИЈАТРИЈА' => 'detska-psihijatrija',
        'НЕВРОЛОГИЈА' => 'nevrologija',
        'ОФТАЛМОЛОГИЈА' => 'oftalmologija',
        'ОТОРИНОЛАРИНГОЛОГИЈА' => 'otorinolaringologija',
        'ОПШТА ОТОРИНОЛАРИНГОЛОГИЈА' => 'otorinolaringologija',
        'АУДИОЛОГИЈА' => 'otorinolaringologija',
        'ОПШТА ХИРУРГИЈА' => 'opshta-hirurgija',
        'АБДОМИНАЛЕН ХИРУРГ' => 'opshta-hirurgija',
        'ДИГЕСТИВНА ХИРУРГИЈА' => 'opshta-hirurgija',
        'ОРТОПЕДИЈА' => 'ortopedija',
        'ТРАУМАТОЛОГИЈА' => 'traumatologija',
        'ФИЗИКАЛНА МЕДИЦИНА И РЕХАБИЛИТАЦИЈА' => 'fizikalna-medicina',
        'ФИЗИКАЛНА И РЕХАБИЛИТАЦИОНА МЕДИЦИНА' => 'fizikalna-medicina',
        'МЕДИЦИНСКА БИОХЕМИЈА' => 'medicinska-biohemija',
        'ДЕРМАТОВЕНЕРОЛОГИЈА' => 'dermatologija',
        'РАДИОЛОГИЈА' => 'radiologija',
        'РАДИОДИЈАГНОСТИКА' => 'radiologija',
        'ГИНЕКОЛОШКА И МАМА РАДИОДИЈАГНОСТИКА' => 'radiologija',
        'ДИГЕСТИВНА РАДИОЛОГИЈА' => 'radiologija',
        'НЕВРОРАДИОЛОГИЈА' => 'radiologija',
        'УРОЛОШКА РАДИОДИЈАГНОСТИКА' => 'radiologija',
        'КАРДИОЛОГИЈА' => 'kardiologija',
        'УРОЛОГИЈА' => 'urologija',
        'УРОЛОШКА ХИРУРГИЈА' => 'urologija',
        'ИНФЕКТОЛОГИЈА' => 'infektologija',
        'ТРАНСФУЗИСКА МЕДИЦИНА' => 'transfuziona-medicina',
        'НЕВРОХИРУРГИЈА' => 'nevrohirurgija',
        'МЕДИЦИНСКА МИКРОБИОЛОГИЈА СО ПАРАЗИТОЛОГИЈА' => 'mikrobiologija',
        'МИКРОБИ СО ПАРАЗИТ' => 'mikrobiologija',
        'РАДИОТЕРАПИЈА И ОНКОЛОГИЈА' => 'onkologija',
        'РАДИОТЕРАПИЈА' => 'onkologija',
        'ГАСТРОЕНТЕРОХЕПАТОЛОГИЈА' => 'gastroenterologija',
        'МЕДИЦИНА НА ТРУДОТ' => 'medicina-na-trudot',
        'ПУЛМОЛОГ' => 'pulmologija',
        'ПУЛМОЛОГИЈА И АЛЕРГОЛОГИЈА' => 'pulmologija',
        'ПУЛМОЛОГИЈА И РЕСПИРАТОРНА АЛЕРГОЛОГИЈА' => 'pulmologija',
        'ЕПИДЕМИОЛОГИЈА' => 'epidemiologija',
        'ПАТОЛОШКА АНАТОМИЈА' => 'patologija',
        'ПАТОЛОГИЈА' => 'patologija',
        'ДЕТСКА ХИРУРГИЈА' => 'detska-hirurgija',
        'МАКСИЛОФАЦИЈАЛНА ХИРУРГИЈА' => 'maksilofacijalna-hirurgija',
        'ПЛАСТИЧНА И РЕКОНСТРУКТИВНА ХИРУРГИЈА' => 'plastichna-hirurgija',
        'НЕФРОЛОГИЈА' => 'nefrologija',
        'НУКЛЕАРНА МЕДИЦИНА' => 'nuklearna-medicina',
        'СПОРТСКА МЕДИЦИНА' => 'sportska-medicina',
        'ЕНДОКРИНОЛОГИЈА' => 'endokrinologija',
        'ДИЈАБЕТОЛОГИЈА' => 'endokrinologija',
        'РЕВМАТОЛОГИЈА' => 'revmatologija',
        'ХЕМАТОЛОГИЈА' => 'hematologija',
        'СУДСКА МЕДИЦИНА' => 'sudska-medicina',
        'УРГЕНТНА МЕДИЦИНА' => 'urgentna-medicina',
        'КАРДИОХИРУРГИЈА' => 'kardiohirurgija',
        'КАРДИОВАСКУЛАРНА ХИРУРГИЈА' => 'kardiohirurgija',
        'ВАСКУЛАРНА ХИРУРГИЈА' => 'vaskularna-hirurgija',
        'ГРАДЕН ХИРУРГ' => 'torakalna-hirurgija',
        'ТОРАКАЛОВАСКУЛАРНА ХИРУРГИЈА' => 'torakalna-hirurgija',
        'КЛИНИЧКА ФАРМАКОЛОГИЈА' => 'klinichka-farmakologija',
        'КЛИНИЧКА ГЕНЕТИКА' => 'medicinska-genetika',
        'МЕДИЦИНСКА ГЕНЕТИКА' => 'medicinska-genetika',
        'МЕДИЦИНСКА ГЕНЕТИКА И МОЛЕКУЛАРНА БИОЛОГИЈА' => 'medicinska-genetika',
        'УЧИЛИШНА МЕДИЦИНА' => 'uchilishna-medicina',
        'ХИГИЕНА СО ЗДРАВСТВЕНА ЕКОЛОГИЈА' => 'higiena',
        'СОЦИЈАЛНА МЕДИЦИНА СО ОРГАНИЗАЦИЈА НА ЗДРАВСТВЕНАТА ДЕЈНОСТ' => 'socijalna-medicina',
        'ИМУНОЛОГИЈА' => 'imunologija',
        'ОПШТА СТОМАТОЛОГИЈА' => 'stomatologija',
        'СТОМАТОЛОГ' => 'stomatologija',
        'СТОМАТОЛОГИЈА' => 'stomatologija',
        'ОРТОДОНЦИЈА' => 'stomatologija-ortodoncija',
        'СТОМАТОЛОШКА ПРОТЕТИКА' => 'stomatologija-protetika',
        'ОРАЛНА ХИУРГИЈА' => 'stomatologija-oralna-hirurgija',
        'ОРАЛНА ХИРУРГИЈА' => 'stomatologija-oralna-hirurgija',
        'ДЕТСКА И ПРЕВЕНТИВНА СТОМАТОЛОГИЈА' => 'stomatologija-detska',
        'БОЛЕСТИ НА УСТА И ПАРАДОНТОТ' => 'stomatologija-parodontologija',
        'ОРАЛНА МЕДИЦИНА' => 'stomatologija-parodontologija',
        'БОЛЕСТИ НА ЗАБИТЕ И ЕНДОДОНТОТ' => 'stomatologija-endodoncija',
        'ЕНДОДОНЦИЈА И РЕСТАВРАТИВНА СТОМАТОЛОГИЈА' => 'stomatologija-endodoncija',
        // Not doctors: never imported as people.
        'ФАРМАЦЕВТ' => self::EXCLUDED,
        'ФАРМАЦЕВТСКА ТЕХНОЛОГИЈА' => self::EXCLUDED,
        'ИСПИТУВАНЈЕ И КОНТРОЛА НА ЛЕКОВИ' => self::EXCLUDED,
        'РАДИОЛОШКИ ТЕХНОЛОГ' => self::EXCLUDED,
        'САНИТАРНА ХЕМИЈА' => self::EXCLUDED,
        'МЕДИЦИНСКА ПСИХОЛОГИЈА' => self::EXCLUDED,
        'МОЛЕКУЛАРНА БИОЛОГИЈА И ГЕНЕТИКА' => self::EXCLUDED,
        'ИСТРАЖУВАЧКИ ЦЕНТАР ЗА ГЕНЕТСКО ИНЗЕНЕРСТВО И БИОТЕХНОЛОГИЈА' => self::EXCLUDED,
    ];

    /**
     * Prefix rules for wordings not listed above (new variants appear).
     *
     * @var array<string, string>
     */
    public const PREFIXES = [
        'ДИПЛОМИРАН' => self::EXCLUDED,
        'ПЕДИЈАТАР' => 'pedijatrija',
    ];

    /**
     * Specialty implied by the contract type when a row has none: general
     * practice, dentistry and gynaecology contracts in primary care.
     *
     * @var array<int, string>
     */
    public const CONTRACT_TYPE_FALLBACK = [
        1 => 'opshta-medicina',
        2 => 'stomatologija',
        3 => 'ginekologija',
        6 => 'stomatologija',
    ];

    /**
     * Work-unit activities (`Dejnost`) that name a specialty, used only for
     * a person with no specialty on any contract.
     *
     * @var array<string, string>
     */
    public const ACTIVITIES = [
        'ДЕТСКИ БОЛЕСТИ' => 'pedijatrija',
        'БЕЛОДРОБНИ ЗАБОЛУВАЊА КАЈ ДЕЦА' => 'pedijatrija',
        'НЕВРОПСИХИЈАТРИСКИ БОЛЕСТИ' => 'psihijatrija',
        'МЕНТАЛНО ЗДРАВЈЕ НА ДЕЦА И МЛАДИНЦИ' => 'detska-psihijatrija',
    ];

    public static function activityDefaultFor(string $activityKey): ?string
    {
        $slug = self::ACTIVITIES[$activityKey] ?? self::ALIASES[$activityKey] ?? null;

        return $slug === self::EXCLUDED ? null : $slug;
    }

    /**
     * Default for a wording: a slug, EXCLUDED, or null (unknown).
     */
    public static function defaultFor(string $rawKey): ?string
    {
        if (isset(self::ALIASES[$rawKey])) {
            return self::ALIASES[$rawKey];
        }

        foreach (self::PREFIXES as $prefix => $target) {
            if (str_starts_with($rawKey, $prefix)) {
                return $target;
            }
        }

        return null;
    }

    public static function isDental(string $slug): bool
    {
        return $slug === self::DENTAL_SLUG_PREFIX || str_starts_with($slug, self::DENTAL_SLUG_PREFIX.'-');
    }
}
