<?php

/*
| Specialty wording of the Лекарска комора licence list („Тип на
| специјализација“) and of the ФЗОМ „Шифрарник на лекари“ (`Specijalnosti`,
| one value per comma-separated item), grouped by what the specialty is.
|
| Built from every distinct value in the Комора list of 02.07.2026 (118) and
| the ФЗОМ files of 06.10.2026 (119), and reviewed by hand. Inserted into
| licence_specialty_mappings by the 2026_10_15_110000 migration
| (App\Support\Licences\LicenceSpecialtySeedList); staff edit it in the admin
| panel („Licence specialty mapping“), and wording first seen in a later list
| is added there unmapped for review.
|
| - group: a stable key. Where it equals one of our specialties' slugs
|   (kardiologija, pedijatrija, ortopedija, dermatologija, ginekologija) the
|   rows are linked to that specialty.
| - compatible: other groups a doctor licensed in this group may be listed
|   under at their workplace. ФЗОМ records the contracted post, not the
|   licence: a cardiologist is often contracted as an internist, a specialist
|   in family medicine as a general practitioner. Read from the licence's
|   side only.
| - ignored: not a physician's specialty (pharmacists, dentists, psychologists,
|   other professions). Never matched to a licence.
*/

return [
    [
        'group' => 'opsta-medicina',
        'compatible' => ['semejna-medicina', 'uchilishna-medicina', 'medicina-na-trudot', 'urgentna-medicina'],
        'komora' => ['доктор на медицина во ПЗЗ', 'општа медицина', 'примарна здравствена заштита'],
        'fzom' => ['ОПШТА МЕДИЦИНА', 'БЕЗ СПЕЦИЈАЛНОСТ', 'ПРИМАРНА ЗДРАВСТВЕНА ЗАШТИТА'],
    ],
    [
        'group' => 'semejna-medicina',
        'compatible' => ['opsta-medicina'],
        'komora' => ['семејна медицина'],
        'fzom' => ['СЕМЕЈНА МЕДИЦИНА'],
    ],
    [
        'group' => 'uchilishna-medicina',
        'compatible' => ['opsta-medicina', 'pedijatrija'],
        'komora' => ['училишна медицина'],
        'fzom' => ['УЧИЛИШНА МЕДИЦИНА'],
    ],
    [
        'group' => 'medicina-na-trudot',
        'compatible' => ['opsta-medicina'],
        'komora' => ['медицина на трудот'],
        'fzom' => ['МЕДИЦИНА НА ТРУДОТ'],
    ],
    [
        'group' => 'sportska-medicina',
        'compatible' => [],
        'komora' => ['спортска медицина'],
        'fzom' => ['СПОРТСКА МЕДИЦИНА'],
    ],
    [
        'group' => 'urgentna-medicina',
        'compatible' => ['opsta-medicina'],
        'komora' => ['ургентна медицина'],
        'fzom' => ['УРГЕНТНА МЕДИЦИНА'],
    ],
    [
        'group' => 'pedijatrija',
        'compatible' => [],
        'komora' => [
            'педијатрија', 'болничка педијатрија', 'неонатологија', 'педијатриска пулмологија',
            'педијатриска гастроентерохепатологија',
        ],
        'fzom' => [
            'ПЕДИЈАТАР', 'БОЛНИЧКИ ПЕДИЈАТАР', 'ПЕДИЈАТАР НЕОНАТОЛОГ', 'ПЕДИЈАТАР ПУЛМОЛОГ', 'ПЕДИЈАТАР ЕНДОКРИНОЛОГ',
            'ПЕДИЈАТАР НЕВРОЛОГ', 'ПЕДИЈАТАР ГЕНЕТИЧАР', 'ПЕДИЈАТАР-ДЕТ.КАРДИОЛОГ', 'ПЕДИЈАТАР-ГАСТРОЕНТЕРОЛОГ',
            'ПЕДИЈАТАР-спец.по хигиена и здр.екологија', 'СПЕЦИЈАЛИСТ ОД ЦЕНТАР ЗА ЦИСТИЧНА ФИБРОЗА',
        ],
    ],
    [
        'group' => 'pedijatrija',
        'compatible' => ['kardiologija'],
        'komora' => ['педијатриска кардиологија'],
        'fzom' => [],
    ],
    [
        'group' => 'pedijatrija',
        'compatible' => ['nevrologija'],
        'komora' => ['педијатриска неврологија'],
        'fzom' => [],
    ],
    [
        'group' => 'ginekologija',
        'compatible' => [],
        'komora' => [
            'акушерство и гинекологија', 'гинекологија и акушерство', 'перинатологија', 'урогинекологија',
            'гинеколошка хирушка онкологија',
        ],
        'fzom' => ['АКУШЕРСТВО И ГИНЕКОЛОГИЈА', 'ПЕРИНАТОЛОГИЈА', 'УРОГИНЕКОЛОГИЈА'],
    ],
    [
        'group' => 'interna-medicina',
        'compatible' => [],
        'komora' => ['интерна медицина'],
        'fzom' => ['ИНТЕРНА МЕДИЦИНА', 'ИНТЕРНИСТ ОД ЦЕНТАР ЗА АСТМА И HOBB'],
    ],
    [
        'group' => 'kardiologija',
        'compatible' => ['interna-medicina'],
        'komora' => ['кардиологија'],
        'fzom' => ['КАРДИОЛОГИЈА'],
    ],
    [
        'group' => 'gastroenterohepatologija',
        'compatible' => ['interna-medicina'],
        'komora' => ['гастроентерохепатологија', 'гастрохепатологија'],
        'fzom' => ['ГАСТРОЕНТЕРОХЕПАТОЛОГИЈА'],
    ],
    [
        'group' => 'nefrologija',
        'compatible' => ['interna-medicina'],
        'komora' => ['нефрологија'],
        'fzom' => ['НЕФРОЛОГИЈА'],
    ],
    [
        'group' => 'endokrinologija',
        'compatible' => ['interna-medicina'],
        'komora' => ['ендокринологија', 'ендокринологија и болести на метаболизмот', 'дијабетологија'],
        'fzom' => ['ЕНДОКРИНОЛОГИЈА', 'ДИЈАБЕТОЛОГИЈА'],
    ],
    [
        'group' => 'pulmologija',
        'compatible' => ['interna-medicina'],
        'komora' => [
            'пулмологија со алергологија', 'пулмологија и алергологија', 'пулмологија и респираторна алергологија',
            'пулмологија', 'пневмологија', 'пулмоалергологија',
        ],
        'fzom' => ['ПУЛМОЛОГИЈА И РЕСПИРАТОРНА АЛЕРГОЛОГИЈА', 'ПУЛМОЛОГИЈА И АЛЕРГОЛОГИЈА', 'ПУЛМОЛОГ'],
    ],
    [
        'group' => 'revmatologija',
        'compatible' => ['interna-medicina'],
        'komora' => ['ревматологија'],
        'fzom' => ['РЕВМАТОЛОГИЈА'],
    ],
    [
        'group' => 'hematologija',
        'compatible' => ['interna-medicina'],
        'komora' => ['хематологија'],
        'fzom' => ['ХЕМАТОЛОГИЈА'],
    ],
    [
        'group' => 'imunologija',
        'compatible' => ['interna-medicina'],
        'komora' => ['имунологија'],
        'fzom' => ['ИМУНОЛОГИЈА'],
    ],
    [
        'group' => 'klinicka-farmakologija',
        'compatible' => [],
        'komora' => ['клиничка фармакологија'],
        'fzom' => ['КЛИНИЧКА ФАРМАКОЛОГИЈА'],
    ],
    [
        'group' => 'infektologija',
        'compatible' => [],
        'komora' => ['инфектологија'],
        'fzom' => ['ИНФЕКТОЛОГИЈА'],
    ],
    [
        'group' => 'anesteziologija',
        'compatible' => [],
        'komora' => [
            'анестезиологија со реаниматологија', 'анестезиологија со интензивно лекување',
            'анестезиологија со интензивна медицина', 'анестезиологија, реаниматологија и интензивна терапија',
            'анестезиологија', 'анестезиологија со реанимација', 'педијатриска анестезиологија',
            'интензивно лекување на критично болни пациенти',
        ],
        'fzom' => [
            'АНЕСТЕЗИОЛОГИЈА СО РЕАНИМАЦИЈА И ИНТЕНЗИВНИ ЛЕКУВАЊЕ', 'АНЕСТЕЗИОЛОГИЈА СО ИНТЕНЗИВНА МЕДИЦИНА',
            'ИНТЕНЗИВНО ЛЕКУВАЊЕ НА КРИТИЧНО БОЛНИ ПАЦИЕНТИ',
        ],
    ],
    [
        'group' => 'psihijatrija',
        'compatible' => ['nevropsihijatrija'],
        'komora' => [
            'психијатрија', 'судска психијатрија', 'социјална психијатрија', 'болести на зависност', 'психотерапија',
            'детска адолесцентна психијатрија', 'детска и адолесцентна психијатрија',
        ],
        'fzom' => [
            'ПСИХИЈАТРИЈА', 'СУДСКА ПСИХИЈАТРИЈА', 'СОЦИЈАЛНА ПСИХИЈАТРИЈА', 'БОЛЕСТИ НА ЗАВИСНОСТ',
            'ДЕТСКА И АДОЛЕСЦЕНТНА ПСИХИЈАТРИЈА',
        ],
    ],
    [
        'group' => 'nevropsihijatrija',
        'compatible' => ['psihijatrija', 'nevrologija'],
        'komora' => ['невропсихијатрија'],
        'fzom' => ['НЕВРОПСИХИЈАТАР'],
    ],
    [
        'group' => 'nevrologija',
        'compatible' => ['nevropsihijatrija'],
        'komora' => ['неврологија', 'неврофизиологија'],
        'fzom' => ['НЕВРОЛОГИЈА'],
    ],
    [
        'group' => 'nevrologija',
        'compatible' => ['pedijatrija'],
        'komora' => ['детска неврологија'],
        'fzom' => [],
    ],
    [
        'group' => 'dermatologija',
        'compatible' => [],
        'komora' => ['дерматовенерологија'],
        'fzom' => ['ДЕРМАТОВЕНЕРОЛОГИЈА'],
    ],
    [
        'group' => 'oftalmologija',
        'compatible' => [],
        'komora' => ['офталмологија'],
        'fzom' => ['ОФТАЛМОЛОГИЈА'],
    ],
    [
        'group' => 'otorinolaringologija',
        'compatible' => [],
        'komora' => [
            'оториноларингологија', 'општа оториноларингологија', 'оториноларинголог - ринолог',
            'оториноларингологија-отолог', 'оториноларингологија-фаринголаринголог', 'аудиологија',
        ],
        'fzom' => ['ОТОРИНОЛАРИНГОЛОГИЈА', 'ОПШТА ОТОРИНОЛАРИНГОЛОГИЈА', 'АУДИОЛОГИЈА'],
    ],
    [
        'group' => 'ortopedija',
        'compatible' => ['traumatologija'],
        'komora' => ['ортопедија'],
        'fzom' => ['ОРТОПЕДИЈА'],
    ],
    [
        'group' => 'traumatologija',
        'compatible' => ['ortopedija', 'opsta-hirurgija'],
        'komora' => ['трауматологија'],
        'fzom' => ['ТРАУМАТОЛОГИЈА'],
    ],
    [
        'group' => 'opsta-hirurgija',
        'compatible' => [],
        'komora' => ['општа хирургија'],
        'fzom' => ['ОПШТА ХИРУРГИЈА'],
    ],
    [
        'group' => 'abdominalna-hirurgija',
        'compatible' => ['opsta-hirurgija'],
        'komora' => ['абдоминална хирургија', 'дигестивна хирургија'],
        'fzom' => ['АБДОМИНАЛЕН ХИРУРГ', 'ДИГЕСТИВНА ХИРУРГИЈА'],
    ],
    [
        'group' => 'vaskularna-hirurgija',
        'compatible' => ['opsta-hirurgija', 'kardiohirurgija'],
        'komora' => ['васкуларна хирургија'],
        'fzom' => ['ВАСКУЛАРНА ХИРУРГИЈА'],
    ],
    [
        'group' => 'kardiohirurgija',
        'compatible' => ['vaskularna-hirurgija', 'gradna-hirurgija', 'opsta-hirurgija'],
        'komora' => ['кардиохирургија', 'детска кардиохирургија', 'кардиоваскуларна хирургија', 'кардиоторакална хирургија'],
        'fzom' => ['КАРДИОХИРУРГИЈА', 'КАРДИОВАСКУЛАРНА ХИРУРГИЈА'],
    ],
    [
        'group' => 'gradna-hirurgija',
        'compatible' => ['opsta-hirurgija', 'kardiohirurgija'],
        'komora' => ['градна хирургија', 'торакална хирургија'],
        'fzom' => ['ГРАДЕН ХИРУРГ', 'ТОРАКАЛОВАСКУЛАРНА ХИРУРГИЈА'],
    ],
    [
        'group' => 'plasticna-hirurgija',
        'compatible' => ['opsta-hirurgija'],
        'komora' => ['пластична и реконструктивна хирургија', 'пластична, реконструктивна и естетска хирургија'],
        'fzom' => ['ПЛАСТИЧНА И РЕКОНСТРУКТИВНА ХИРУРГИЈА'],
    ],
    [
        'group' => 'detska-hirurgija',
        'compatible' => ['opsta-hirurgija'],
        'komora' => ['детска хирургија'],
        'fzom' => ['ДЕТСКА ХИРУРГИЈА'],
    ],
    [
        'group' => 'nevrohirurgija',
        'compatible' => [],
        'komora' => ['неврохирургија'],
        'fzom' => ['НЕВРОХИРУРГИЈА'],
    ],
    [
        'group' => 'maksilofacijalna-hirurgija',
        'compatible' => [],
        'komora' => ['максилофацијална хирургија'],
        'fzom' => ['МАКСИЛОФАЦИЈАЛНА ХИРУРГИЈА'],
    ],
    [
        'group' => 'urologija',
        'compatible' => [],
        'komora' => ['урологија', 'уролошка хирургија'],
        'fzom' => ['УРОЛОГИЈА', 'УРОЛОШКА ХИРУРГИЈА'],
    ],
    [
        'group' => 'radiologija',
        'compatible' => [],
        'komora' => [
            'радиологија', 'радиодијагностика', 'интервентна радиологија', 'уролошка радиодијагностика',
            'гинеколошка и мамарадиодијагностика', 'дигестивна радиологија', 'неврорадиологија',
            'остеоартикуларна радиологија', 'торакална радиологија',
        ],
        'fzom' => [
            'РАДИОЛОГИЈА', 'РАДИОДИЈАГНОСТИКА', 'ГИНЕКОЛОШКА И МАМА РАДИОДИЈАГНОСТИКА', 'УРОЛОШКА РАДИОДИЈАГНОСТИКА',
            'НЕВРОРАДИОЛОГИЈА', 'ДИГЕСТИВНА РАДИОЛОГИЈА',
        ],
    ],
    [
        'group' => 'onkologija-radioterapija',
        'compatible' => [],
        'komora' => ['радиотерапија и онкологија', 'онкологија и радиотерапија', 'радиотерапија', 'онкологија'],
        'fzom' => ['РАДИОТЕРАПИЈА И ОНКОЛОГИЈА', 'РАДИОТЕРАПИЈА'],
    ],
    [
        'group' => 'nuklearna-medicina',
        'compatible' => [],
        'komora' => ['нуклеарна медицина'],
        'fzom' => ['НУКЛЕАРНА МЕДИЦИНА'],
    ],
    [
        'group' => 'fizikalna-medicina',
        'compatible' => [],
        'komora' => ['физикална медицина и рехабилитација', 'физикална и рехабилитациона медицина'],
        'fzom' => ['ФИЗИКАЛНА МЕДИЦИНА И РЕХАБИЛИТАЦИЈА', 'ФИЗИКАЛНА И РЕХАБИЛИТАЦИОНА МЕДИЦИНА'],
    ],
    [
        'group' => 'medicinska-biohemija',
        'compatible' => [],
        'komora' => ['медицинска биохемија'],
        'fzom' => ['МЕДИЦИНСКА БИОХЕМИЈА'],
    ],
    [
        'group' => 'mikrobiologija',
        'compatible' => [],
        'komora' => ['микробиологија', 'медицинска микробиологија со паразитологија', 'медицинска микробиологија'],
        'fzom' => ['МЕДИЦИНСКА МИКРОБИОЛОГИЈА СО ПАРАЗИТОЛОГИЈА', 'МИКРОБИ СО ПАРАЗИТ'],
    ],
    [
        'group' => 'epidemiologija',
        'compatible' => [],
        'komora' => ['епидемиологија'],
        'fzom' => ['ЕПИДЕМИОЛОГИЈА'],
    ],
    [
        'group' => 'patologija',
        'compatible' => [],
        'komora' => ['патолошка анатомија', 'патологија'],
        'fzom' => ['ПАТОЛОШКА АНАТОМИЈА', 'ПАТОЛОГИЈА'],
    ],
    [
        'group' => 'sudska-medicina',
        'compatible' => ['patologija'],
        'komora' => ['судска медицина'],
        'fzom' => ['СУДСКА МЕДИЦИНА'],
    ],
    [
        'group' => 'transfuziologija',
        'compatible' => [],
        'komora' => ['трансфузиологија', 'трансфузиона медицина', 'трансфузиска медицина'],
        'fzom' => ['ТРАНСФУЗИСКА МЕДИЦИНА'],
    ],
    [
        'group' => 'higiena',
        'compatible' => [],
        'komora' => ['хигиена', 'хигиена и здравствена екологија'],
        'fzom' => ['ХИГИЕНА СО ЗДРАВСТВЕНА ЕКОЛОГИЈА'],
    ],
    [
        'group' => 'socijalna-medicina',
        'compatible' => [],
        'komora' => [
            'социјална медицина и јавно здравје', 'социјална медицина со организација на здр. дејност',
            'социјална медицина со организација на здр. заштита',
        ],
        'fzom' => ['СОЦИЈАЛНА МЕДИЦИНА СО ОРГАНИЗАЦИЈА НА ЗДРАВСТВЕНАТА ДЕЈНОСТ'],
    ],
    [
        'group' => 'klinicka-genetika',
        'compatible' => [],
        'komora' => ['клиничка генетика'],
        'fzom' => ['КЛИНИЧКА ГЕНЕТИКА', 'МЕДИЦИНСКА ГЕНЕТИКА'],
    ],
    [
        'group' => null,
        'ignored' => true,
        'komora' => [],
        'fzom' => [
            // Pharmacists and dentists (other chambers; no licence on this list).
            'ФАРМАЦЕВТ', 'ФАРМАЦЕВТСКА ТЕХНОЛОГИЈА', 'ИСПИТУВАНЈЕ И КОНТРОЛА НА ЛЕКОВИ', 'ОПШТА СТОМАТОЛОГИЈА', 'СТОМАТОЛОГ',
            'ОРТОДОНЦИЈА', 'СТОМАТОЛОШКА ПРОТЕТИКА', 'ОРАЛНА ХИУРГИЈА', 'ОРАЛНА МЕДИЦИНА',
            'ДЕТСКА И ПРЕВЕНТИВНА СТОМАТОЛОГИЈА', 'БОЛЕСТИ НА УСТА И ПАРАДОНТОТ', 'БОЛЕСТИ НА ЗАБИТЕ И ЕНДОДОНТОТ',
            'ЕНДОДОНЦИЈА И РЕСТАВРАТИВНА СТОМАТОЛОГИЈА',
            // Other professions.
            'ДИПЛОМИРАН ИНЖЕНЕР ПО БИОЛОГИЈА', 'ДИПЛОМИРАН ИНЖЕНЕР ПО ХЕМИЈА', 'ДИПЛОМИРАН МОЛЕКУЛАРЕН БИОЛОГ',
            'ДИПЛОМИРАН ПСИХОЛОГ', 'МЕДИЦИНСКА ПСИХОЛОГИЈА', 'ДИПЛОМИРАН ЛОГОПЕД', 'ДИПЛОМИРАН ДЕФЕКТОЛОГ',
            'ДИПЛОМИРАН ДЕФЕКТОЛОГ - ЛОГОПЕД', 'ДИПЛОМИРАН СОЦИЈАЛЕН РАБОТНИК', 'ДИПЛОМИРАН ПЕДАГОГ',
            'РАДИОЛОШКИ ТЕХНОЛОГ', 'САНИТАРНА ХЕМИЈА', 'МОЛЕКУЛАРНА БИОЛОГИЈА И ГЕНЕТИКА',
            'МЕДИЦИНСКА ГЕНЕТИКА И МОЛЕКУЛАРНА БИОЛОГИЈА',
            'истражувачки центар за генетско инзенерство и биотехнологија',
        ],
    ],
];
