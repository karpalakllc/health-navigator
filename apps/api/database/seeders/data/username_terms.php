<?php

/*
| Seed lists for usernames (App\Models\UsernameTerm), compiled for this project
| from general knowledge of English, Macedonian and Albanian usage. The English
| list is extended with LDNOOBW (username_terms_ldnoobw_en.php, CC BY 4.0).
| Staff curate the lists in the admin panel afterwards; the migration only
| inserts terms that are not there yet.
|
| Each group: kind (blocked | reserved), category (UsernameTerm::CATEGORIES),
| language (en | mk | sq | any), and its terms by match type:
|
| - contains: refused anywhere in a username. Only terms of at least four
|   letters that do not occur inside real Macedonian or Albanian names or
|   ordinary words — UsernameRealNamesTest guards a long list of names.
| - exact: refused as the whole username or as one of its words
|   („dr.marko“, „marko_dr“). Short terms, and words that also occur inside
|   names („nazi“ in Nazif, „slag“ in Slagjana, „heroin“ in heroine).
| - prefix: as exact, and also at the start of a word before a consonant
|   („drmarko“, „profivanov“), never before a vowel (Dragan, Mjeku). For
|   titles glued to a name.
| - allowed: exceptions, masked out before `contains` terms are looked for
|   („therapist“ contains „rapist“).
|
| Macedonian terms are listed in Cyrillic and in the Latin spellings people
| type without diacritics (c for ч, s for ш). Both sides are folded the same
| way (Cyrillic to Latin, look-alikes, leetspeak, separators), so one spelling
| per sound covers „пичка“, „pichka“, „p1chka“ and „пи-чка“.
|
| Deliberately NOT listed: ethnonyms and identity words used neutrally
| (Turk, Vlach, Bulgarian, gay, queer, autistic, invalid), names of political
| parties and historical movements, and ordinary first names and surnames
| (Nancy, Mick, Osama, Karin, Shota, Vlada, the Albanian surname Mjeku).
*/

return [

    // ---------------------------------------------------------------- blocked

    [
        'kind' => 'blocked', 'category' => 'profanity', 'language' => 'en',
        'contains' => [
            'fuck', 'fucker', 'fukker', 'fvck', 'phuck', 'shit', 'bullshit', 'shithead', 'shitface', 'dipshit',
            'cunt', 'asshole', 'arsehole', 'asshat', 'asswipe', 'jackass', 'dumbass', 'smartass', 'bitch', 'biatch',
            'bastard', 'motherfucker', 'muthafucka', 'wanker', 'tosser', 'bollocks', 'bellend', 'dickhead',
            'dickface', 'cocksucker', 'cockface', 'pussy', 'twat', 'piss', 'prick', 'douchebag', 'douche',
            'scumbag', 'shitbag', 'goddamn', 'damnit', 'crap', 'skank', 'jerkoff', 'jizz', 'killyourself',
        ],
        'exact' => [
            'ass', 'arse', 'asses', 'dick', 'dicks', 'cock', 'cocks', 'damn', 'hell', 'suck', 'sucks', 'sucker',
            'wank', 'knob', 'tit', 'tits', 'shat', 'butt', 'turd', 'slag', 'loser', 'idiot', 'moron', 'stupid',
            'dumb', 'fu', 'fk', 'fck', 'fcuk', 'sht', 'btch', 'wtf', 'stfu', 'gtfo', 'kys', 'die', 'killer', 'murder',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'sexual', 'language' => 'en',
        'contains' => [
            'porn', 'pr0n', 'xxxx', 'blowjob', 'handjob', 'rimjob', 'boobies', 'titties', 'nipple', 'dildo',
            'vibrator', 'orgasm', 'erection', 'horny', 'hornie', 'sexx', 'sexting', 'sextape', 'nudes', 'naked',
            'masturbat', 'jerkingoff', 'cumshot', 'creampie', 'gangbang', 'bukkake', 'deepthroat', 'milf', 'dilf',
            'hentai', 'onlyfans', 'camgirl', 'camwhore', 'webcamsex', 'stripper', 'prostitut', 'whore', 'hooker',
            'slut', 'penis', 'vagina', 'vulva', 'clitoris', 'scrotum', 'testicle', 'bdsm', 'fetish', 'bondage',
            'pedophil', 'paedophil', 'pedofil', 'childporn', 'zoophil', 'bestiality', 'incest', 'rapist', 'molest',
            'lolicon', 'shotacon', 'upskirt', 'voyeur', 'sugardaddy', 'sugarbaby', 'onenightstand', 'fuckbuddy',
            'escortgirl', 'callgirl',
        ],
        'exact' => [
            'sex', 'sexy', 'sexo', 'xxx', 'xx', 'anal', 'anus', 'boob', 'boobs', 'nude', 'cum', 'semen', 'sperm',
            'rape', 'raped', 'raping', 'orgy', 'escort', 'pimp', 'kinky', 'thong', 'bra', 'panty', 'bj', 'nsfw',
            'smut', 'lewd', 'nympho', 'clit', 'pedo', 'loli', 'yiff', 'hump', 'fap', 'cumming', 'grope',
            'hookup',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'profanity', 'language' => 'mk',
        'contains' => [
            // пичка
            'пичка', 'пичко', 'пичкин', 'пичкета', 'пичкамајка', 'pichka', 'picka', 'pichko', 'picko', 'pichkin',
            // курац, курва
            'курац', 'курчина', 'курцот', 'курвар', 'курвин', 'kurac', 'kurcina', 'kurchina', 'kurcot',
            'курва', 'курво', 'курвите', 'kurva', 'kurvo', 'kurvar', 'kurvin',
            // ебе и изведенки
            'ебам', 'ебем', 'ебеш', 'ебење', 'ебање', 'ебач', 'ебига', 'ебиго', 'ебисе', 'еботе', 'ебивет',
            'ебимајка', 'јебем', 'јебам', 'јебига', 'јебено', 'јебач', 'ebam', 'ebem', 'ebenje', 'ebanje', 'ebach',
            'ebiga', 'ebise', 'ebote', 'jebem', 'jebam', 'jebiga', 'jebeno', 'jebac', 'jebach', 'jebanje', 'jebote',
            'мамицата', 'mamicata', 'мајкати', 'majkati', 'мајкатеебам', 'ебатемајка',
            // пизда, гомно, срање
            'пизда', 'пиздо', 'пиздата', 'pizda', 'pizdo', 'pizdata', 'гомно', 'гомнар', 'гомното', 'говно',
            'govno', 'gomno', 'gomnar', 'гомнојад', 'срање', 'серање', 'сераш', 'серем', 'sranje', 'seranje',
            'seram', 'serem', 'seresh',
            // шупак, кучка, дркач, чмар
            'шупак', 'шупачки', 'shupak', 'supak', 'шупчина', 'кучка', 'кучкин', 'кучкина', 'kuchka', 'kuchkin',
            'дркач', 'дркаш', 'дркање', 'drkach', 'drkac', 'drkash', 'drkanje', 'пушикурац', 'пушикур', 'pushikur',
            'пушиго', 'pushigo', 'pusigo', 'чмар', 'чмарот', 'chmar', 'чмаро', 'смрдливец', 'одвратник',
        ],
        'exact' => [
            'кур', 'курот', 'kur', 'kurot', 'еби', 'ebi', 'ебе', 'ebe', 'ебан', 'eban', 'јеби', 'jebi', 'сере',
            'sere', 'газ', 'газот', 'gaz', 'gazot', 'дупе', 'dupe', 'дупето', 'dupeto', 'глупак', 'glupak',
            'будала', 'budala', 'кретен', 'kreten', 'идиот', 'говедо', 'govedo', 'магаре', 'magare', 'свиња',
            'svinja', 'мрсул', 'mrsul', 'смрдливко', 'smrdlivko', 'тупан', 'tupan', 'кенеф', 'kenef', 'ѓубре',
            'gjubre', 'gubre', 'олош', 'olosh', 'дебил', 'debil', 'сисе', 'sise', 'сиси', 'цицки', 'cicki', 'цице',
            'cice', 'курви', 'kurvi', 'пиче', 'piche', 'мрш', 'mrsh', 'дркам', 'drkam', 'дрка', 'drka', 'kucka',
            'kuckin', 'cmar', 'ebac',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'sexual', 'language' => 'mk',
        'contains' => [
            'порно', 'секси', 'seksi', 'сексуал', 'seksual', 'проститут', 'курветина', 'kurvetina', 'орален',
            'oralen', 'орално', 'анално', 'oralno', 'analno', 'оргија', 'orgija', 'мастурб',
            'masturb', 'еротика', 'erotika', 'еротски', 'erotski', 'дупенце', 'dupence', 'голотија', 'golotija',
            'педофил', 'инцест', 'силувач', 'siluvach', 'siluvac', 'силување', 'siluvanje', 'клиторис', 'klitoris',
            'пенис', 'вагина', 'тестис', 'ескорт', 'eskort', 'кондом', 'kondom', 'вибратор',
        ],
        'exact' => [
            'секс', 'seks', 'анален', 'analen', 'гол', 'gol', 'голи', 'goli', 'гола', 'gola', 'минет', 'minet',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'profanity', 'language' => 'sq',
        'contains' => [
            // qij (to f…), kar (penis), pidh (vulva), bythë (arse), mut (shit), kurvë (whore)
            'qifsha', 'qifshin', 'qifsh', 'qiremotren', 'qiremamen', 'qijemen', 'tqifsha', 'taqifsha', 'kariot',
            'karilesh', 'kariqen', 'pidhi', 'pidhin', 'pidhqir', 'bythqir', 'bythqim', 'bythak', 'bythëqir',
            'mutqen', 'kurvëri', 'kurvar', 'kurvare', 'lavire', 'lavirja', 'shkërdhat', 'shkerdhat', 'kopil',
            'budalla', 'qelbsirë', 'qelbsire', 'pederr',
        ],
        'exact' => [
            'qij', 'qi', 'qir', 'qirje', 'kar', 'kari', 'karë', 'pidh', 'pidhë', 'byth', 'bytha', 'bythë', 'mut',
            'mutë', 'muta', 'muti', 'mutin', 'kurvë', 'kurve', 'kurva', 'pis', 'pisi', 'gomar', 'gomari', 'derr',
            'derri', 'qen', 'qeni', 'lopë', 'dreq', 'dreqi', 'debil', 'kreten', 'shurrë', 'shurre', 'mutor', 'trap',
            'pall', 'pallim', 'rrot',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'sexual', 'language' => 'sq',
        'contains' => [
            'porno', 'seksi', 'seksual', 'lakuriq', 'cicat', 'sisat', 'gjinjtë', 'pedofil', 'incest', 'përdhunues',
            'perdhunues', 'përdhunim', 'perdhunim', 'masturb', 'orgazm', 'prezervativ', 'eskort', 'prostitut',
            'vagin', 'penis',
        ],
        'exact' => [
            'seks', 'sex', 'cica', 'sisa', 'gji', 'pallo',
        ],
    ],

    // Slurs

    [
        'kind' => 'blocked', 'category' => 'slur_ethnic', 'language' => 'en',
        'contains' => [
            'nigger', 'nigga', 'niggah', 'negroid', 'sandnigger', 'porchmonkey', 'junglebunny', 'jigaboo', 'jiggaboo',
            'spearchucker', 'wetback', 'beaner', 'raghead', 'towelhead', 'cameljockey', 'zipperhead', 'slanteye',
            'chinky', 'gooks', 'kraut', 'redskin', 'halfbreed', 'gypsies', 'gyppo', 'pikey', 'darkie', 'whitetrash',
            'cracker', 'honkey', 'honky', 'kikes', 'hymie',
        ],
        'exact' => [
            'nig', 'nigg', 'negro', 'negros', 'coon', 'coons', 'spic', 'spick', 'chink', 'gook', 'jap', 'japs',
            'kike', 'paki', 'pakis', 'wop', 'dago', 'gypsy', 'gyp', 'squaw', 'abo', 'wog', 'polack', 'yid',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_ethnic', 'language' => 'mk',
        'contains' => [
            'шиптар', 'shiptar', 'siptar', 'шкијата', 'shkijata', 'арнаутин', 'arnautin', 'циганиште',
            'ciganishte', 'циганчиња', 'бугараш', 'bugarash', 'bugaras', 'гркоман', 'grkoman', 'србоман',
            'srboman', 'бугароман', 'bugaroman', 'грчиште', 'чифутин', 'chifutin', 'балијас', 'balijas', 'туркуш',
            'turkush',
        ],
        'exact' => [
            'шкија', 'shkija', 'skija', 'шкии', 'shkii', 'skii', 'арнаут', 'arnaut', 'арнаути', 'arnauti',
            'циган', 'cigan', 'цигани', 'cigani', 'циганка', 'ciganka', 'циганин', 'ciganin', 'циганче', 'ciganche',
            'манго', 'mango', 'мангови', 'mangovi', 'балија', 'balija', 'чифут', 'chifut', 'cifut', 'жид', 'zhid',
            'жидов', 'zhidov', 'гркан', 'grkan',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_ethnic', 'language' => 'sq',
        'contains' => [
            'magjup', 'magjypë', 'magjype', 'shkijet', 'shkinë', 'shkine', 'shkaut', 'shkavë', 'shkave', 'çifut',
            'chifut', 'gabelë', 'gabele', 'gabeli', 'maxhup', 'turkoshak', 'grekoman',
        ],
        'exact' => [
            'shka', 'shkau', 'shkie', 'shkije', 'shki', 'gabel', 'jevg', 'jevgu', 'cigan', 'kaur', 'kaurr', 'kauri',
            'kaurri', 'cifuti', 'shkinja',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_religious', 'language' => 'en',
        'contains' => [
            'christkiller', 'muzzie', 'mozzie', 'kafir', 'kaffir', 'jewboy', 'papist',
        ],
        'exact' => [
            'muzzy', 'infidel', 'heathen', 'kuffar', 'heeb',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_religious', 'language' => 'mk',
        'contains' => [
            'потурица', 'poturica', 'потурчен', 'poturchen', 'неверник', 'nevernik', 'ѓаур', 'gjaur',
        ],
        'exact' => [
            'кауре', 'kaure', 'ѓауре', 'турко', 'turko',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_religious', 'language' => 'sq',
        'contains' => [
            'pabesimtar', 'kaurrët', 'qafir',
        ],
        'exact' => [
            'kafir', 'qafiri', 'kauret',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_homophobic', 'language' => 'en',
        'contains' => [
            'faggot', 'faggit', 'fagget', 'fagot', 'dyke', 'tranny', 'trannie', 'shemale', 'ladyboy', 'sodomite',
            'homofag', 'queerbait', 'fudgepacker', 'buttpirate', 'poofter',
        ],
        'exact' => [
            'fag', 'fags', 'faggy', 'homo', 'homos', 'poof', 'lesbo', 'lezzie', 'lezbo', 'heshe',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_homophobic', 'language' => 'mk',
        'contains' => [
            'педер', 'peder', 'педерчина', 'pederchina', 'педерски', 'pederski', 'педериште', 'pederishte',
            'педерштина', 'pedershtina', 'педерко', 'pederko', 'топлија', 'toplija', 'лезбача', 'lezbacha',
            'lezbaca', 'трансвестит', 'transvestit', 'педераст', 'pederast',
        ],
        'exact' => [
            'педо', 'хомо', 'гејче', 'gejche',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_homophobic', 'language' => 'sq',
        'contains' => [
            'pederast', 'pederr', 'pedera', 'buthtar', 'transvestit',
        ],
        'exact' => [
            'pederi', 'homo',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_ableist', 'language' => 'en',
        'contains' => [
            'retard', 'mongoloid', 'spastic', 'spazz', 'cripple', 'imbecile', 'cretin', 'lunatic', 'psycho',
            'schizo', 'downie', 'midget', 'windowlicker',
        ],
        'exact' => [
            'tard', 'spaz', 'spas', 'mong', 'lame', 'dumbo', 'nutjob', 'nutter', 'loony', 'loon', 'freak', 'mental',
            'insane', 'gimp',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_ableist', 'language' => 'mk',
        'contains' => [
            'ретард', 'ретардиран', 'retardiran', 'монголоид', 'дебилче', 'debilche', 'кретенче', 'kretenche',
            'малоумен', 'maloumen', 'малоумник', 'maloumnik', 'сакатко', 'sakatko', 'лудак', 'ludak', 'шизо',
            'shizo', 'психопат', 'psihopat', 'олигофрен', 'oligofren', 'ќорав', 'kjorav',
        ],
        'exact' => [
            'луд', 'lud', 'луда', 'luda', 'лудо', 'ludo', 'сакат', 'sakat', 'ментален', 'mentalen', 'шизик',
            'shizik', 'ќор', 'kjor', 'монгол', 'mongol', 'дебили', 'debili',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'slur_ableist', 'language' => 'sq',
        'contains' => [
            'imbecil', 'mongoloid', 'çmendur', 'cmendur', 'sakatë', 'leshko', 'trubull', 'retardat', 'psikopat',
        ],
        'exact' => [
            'idiot', 'debil', 'sakat', 'qorr', 'qorrë', 'shurdh', 'memec', 'budall',
        ],
    ],

    // Hate and extremism

    [
        'kind' => 'blocked', 'category' => 'hate', 'language' => 'en',
        'contains' => [
            'hitler', 'heilhitler', 'siegheil', 'naziparty', 'neonazi', 'nazism', 'nazist', 'thirdreich',
            'swastika', 'holocaustdenial', 'gaschamber', 'gasthejews', 'whitepower', 'whitepride', 'whitesupremacy',
            'supremacist', 'aryannation', 'kukluxklan', 'skinhead', 'stormfront', 'fourteenwords',
            'greatreplacement', 'daesh', 'alqaeda', 'alqaida', 'taliban', 'jihad', 'mujahideen', 'terrorist',
            'terrorism', 'suicidebomber', 'bombmaker', 'hezbollah', 'boogaloo', 'proudboys', 'oathkeepers',
            'killalljews', 'killjews', 'killmuslims', 'killgays', 'genocide', 'ethniccleansing', 'massshooter',
            'schoolshooter', 'breivik', 'unabomber', 'mussolini', 'osamabinladen', 'binladen', 'baghdadi',
            'zarqawi', 'whitegenocide', 'racewar',
        ],
        'exact' => [
            'nazi', 'nazis', 'aryan', 'isis', 'hamas', 'kkk', 'ss', 'hh', 'hh88', 'h88', 'sieg', 'heil', 'reich',
            'fuhrer', 'fuehrer', 'isil', 'nsdap', 'gestapo', 'waffenss', 'ustasha', 'ustasa', 'chetnik', 'cetnik',
            'goyim', 'stalin', 'gulag',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'hate', 'language' => 'mk',
        'contains' => [
            'хитлер', 'нацист', 'nacist', 'неонацист', 'neonacist', 'фашист', 'fashist', 'fasist', 'нацизам',
            'nacizam', 'фашизам', 'fashizam', 'кукасткрст', 'kukastkrst', 'свастика', 'svastika', 'усташа',
            'ustasha', 'усташи', 'четник', 'chetnik', 'четници', 'chetnici', 'терорист', 'terorist', 'тероризам',
            'terorizam', 'џихад', 'dzhihad', 'исламскадржава', 'islamskadrzhava', 'геноцид', 'genocid',
            'убијалбанци', 'ubijalbanci', 'убијсрби', 'убијмакедонци', 'ubijmakedonci', 'смртнашиптари',
            'smrtnashiptari', 'смртнамакедонци', 'smrtnamakedonci', 'смртнасрби', 'смртнабугари', 'смртнаалбанци',
            'smrtnaalbanci', 'убијциганите', 'гасникомори',
        ],
        'exact' => [
            'наци', 'naci', 'фашо', 'fasho', 'ккк', 'сс', 'хх', 'хајл', 'hajl', 'зиг', 'zig', 'рајх', 'rajh',
            'фирер', 'firer', 'смрт', 'smrt',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'hate', 'language' => 'sq',
        'contains' => [
            'hitleri', 'nazist', 'neonazist', 'fashist', 'nazizëm', 'nazizem', 'fashizëm', 'fashizem', 'svastika',
            'terrorist', 'terrorizëm', 'terrorizem', 'xhihad', 'shtetiislamik', 'gjenocid', 'spastrimetnik',
            'vdekjeshkijeve', 'vdekjeshkieve', 'vritshkijet', 'vritshkiet', 'vritmaqedonasit', 'vdekjemaqedonasve',
            'vdekjeserbeve', 'vdekjeshqiptareve', 'vritshqiptaret', 'çetnik', 'ustash',
        ],
        'exact' => [
            'nazi', 'kkk', 'ss', 'hh', 'heil',
        ],
    ],

    // Drugs

    [
        'kind' => 'blocked', 'category' => 'drugs', 'language' => 'en',
        'contains' => [
            'cocaine', 'cocain', 'kokain', 'methamphetamine', 'methhead', 'crystalmeth', 'amphetamine', 'speedball',
            'marijuana', 'marihuana', 'cannabis', 'hashish', 'stoner', 'weedman', 'weeddealer', 'drugdealer',
            'dopedealer', 'buyweed', 'buydrugs', 'sellweed', 'selldrugs', 'ecstasy', 'xtcpills', 'mdma', 'ketamine',
            'fentanyl', 'fentanil', 'oxycodone', 'oxycontin', 'percocet', 'xanax', 'valium', 'tramadol', 'codeine',
            'opiate', 'opioid', 'morphine', 'psilocybin', 'magicmushroom', 'shrooms', 'mescaline', 'crackhead',
            'junkie', 'pothead', 'cokehead', 'dopehead', 'narcotic', 'narcos', 'cartel', 'buypills', 'pillshop',
            'onlinepharmacy', 'withoutprescription', 'steroidshop', 'anabolic', 'steroids',
        ],
        'exact' => [
            'heroin', 'meth', 'coke', 'crack', 'weed', 'pot', 'hash', 'kush', 'ganja', 'dope', 'lsd', 'acid', 'pcp',
            'ghb', 'xtc', 'molly', 'speed', 'pills', 'oxy', 'opium', 'joint', 'blunt', 'bong', 'dealer', 'plug',
            'trap', 'stoned', 'high', '420', 'lean', 'krokodil', 'spice', 'smack',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'drugs', 'language' => 'mk',
        'contains' => [
            'кокаин', 'марихуана', 'канабис', 'kanabis', 'хашиш', 'амфетамин', 'amfetamin',
            'метамфетамин', 'metamfetamin', 'екстази', 'ekstazi', 'кетамин', 'ketamin', 'фентанил', 'морфиум',
            'morfium', 'метадон', 'metadon', 'трамадол', 'наркотик', 'narkotik', 'наркоман', 'narkoman',
            'дилердрога', 'dilerdroga', 'продавамдрога', 'prodavamdroga', 'купидрога', 'kupidroga', 'дрогадилер',
            'drogadiler', 'џоинт', 'dzhoint', 'вутра', 'vutra', 'стероиди', 'steroidi', 'анаболици', 'anabolici',
            'таблетибезрецепт', 'tabletibezrecept', 'лековибезрецепт', 'lekovibezrecept', 'ксанакс', 'ksanaks',
            'апаурин', 'apaurin', 'диазепам', 'diazepam',
        ],
        'exact' => [
            'дрога', 'droga', 'дроги', 'drogi', 'дилер', 'diler', 'трева', 'treva', 'трава', 'trava', 'кока', 'koka',
            'спид', 'spid', 'хаш', 'лсд', 'дрогиран', 'drogiran', 'дувам', 'duvam', 'хероин', 'heroina',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'drugs', 'language' => 'sq',
        'contains' => [
            'kokainë', 'kokaine', 'marihuanë', 'marihuane', 'kanabis', 'hashash', 'amfetamin', 'metamfetamin',
            'ekstazi', 'ketaminë', 'fentanil', 'morfinë', 'narkotik', 'narkoman', 'shitësdroge', 'shitesdroge',
            'blejdroge', 'shesdroge', 'trafikant', 'drogaxhi', 'steroide', 'tabletapaarecete', 'barnapaarecete',
        ],
        'exact' => [
            'drogë', 'droge', 'droga', 'diler', 'xhoint',
        ],
    ],

    // Scams, spam and contact farming

    [
        'kind' => 'blocked', 'category' => 'scam', 'language' => 'en',
        'contains' => [
            'freemoney', 'easymoney', 'makemoney', 'moneyfast', 'getrich', 'giveaway', 'freegift', 'freeiphone',
            'bitcoin', 'cryptocurrency', 'cryptoinvest', 'cryptotrader', 'cryptowallet', 'binance', 'coinbase',
            'ethereum', 'dogecoin', 'forextrader', 'forexsignal', 'investment', 'investwith', 'doubleyourmoney',
            'casino', 'jackpot', 'betting', 'sportsbet', 'lottery', 'claimprize', 'claimyour', 'loanoffer',
            'quickloan', 'paydayloan', 'cashloan', 'creditrepair', 'paypal', 'cashapp', 'westernunion', 'moneygram',
            'giftcard', 'whatsapp', 'telegram', 'viber', 'snapchat', 'onlyfans', 'linktree', 'clickhere', 'visitmy',
            'followme', 'textme', 'promocode', 'discountcode', 'cheappills', 'cheapmeds', 'buymeds',
            'weightlossfast', 'miraclecure', 'curecancer', 'hacker', 'accountrecovery', 'refundservice',
            'techsupport', 'customercare', 'sugarmommy', 'seoservice', 'buyfollowers', 'cheapfollowers', 'phishing',
            'scammer', 'fraudster', 'moneylaunder', 'escrowservice', 'airdrop', 'nftdrop',
        ],
        'exact' => [
            'btc', 'eth', 'usdt', 'nft', 'crypto', 'forex', 'loan', 'loans', 'cash', 'money', 'prize', 'bonus',
            'refund', 'bet', 'bets', 'scam', 'spam', 'hack', 'fraud', 'promo', 'deal', 'deals', 'discount', 'shop',
            'store', 'buy', 'sell', 'seller', 'ads', 'advert', 'dmme', 'callme',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'scam', 'language' => 'mk',
        'contains' => [
            'бесплатно', 'besplatno', 'бесплатнипари', 'брзапара', 'brzapara', 'брзизаем', 'brzizaem', 'брзкредит',
            'brzkredit', 'заработка', 'zarabotka', 'заработувачка', 'zarabotuvachka', 'инвестиција', 'investicija',
            'инвестирај', 'investiraj', 'биткоин', 'bitkoin', 'крипто', 'kripto', 'казино', 'kazino', 'кладилница',
            'kladilnica', 'обложување', 'oblozhuvanje', 'лотарија', 'lotarija', 'наградна', 'nagradna', 'добитник',
            'dobitnik', 'освоивте', 'osvoivte', 'подарок', 'podarok', 'попуст', 'popust', 'продавам', 'prodavam',
            'купувам', 'kupuvam', 'чудотворен', 'chudotvoren', 'лекзарак', 'lekzarak', 'брзослабеење', 'хакер',
            'haker', 'измамник', 'izmamnik', 'вајбер', 'vajber', 'вотсап', 'votsap', 'телеграм',
        ],
        'exact' => [
            'заем', 'zaem', 'кредит', 'kredit', 'пари', 'pari', 'награда', 'nagrada', 'бонус', 'промо', 'промоција',
            'promocija', 'акција', 'akcija', 'продажба', 'prodazhba', 'кеш', 'kesh', 'реклама', 'reklama', 'измама',
            'izmama', 'спам', 'лото', 'loto',
        ],
    ],
    [
        'kind' => 'blocked', 'category' => 'scam', 'language' => 'sq',
        'contains' => [
            'parafalas', 'parepashpejt', 'parapashpejt', 'fitopara', 'fitimshpejt', 'investim', 'bitkoin',
            'kriptovalut', 'kazino', 'bastore', 'llotari', 'lotari', 'fitues', 'fitimtar', 'zbritje', 'kredirapid',
            'kredishpejt', 'huashpejt', 'mjekmrekullues', 'ilaçmrekullues', 'ilacmrekullues', 'mashtrues',
        ],
        'exact' => [
            'falas', 'kredi', 'hua', 'para', 'bast', 'baste', 'cmim', 'çmim', 'promo', 'reklamë', 'reklame',
            'mashtrim', 'shitje', 'blerje', 'dhuratë', 'dhurate',
        ],
    ],

    // Exceptions: words and names that contain a `contains` term.

    [
        'kind' => 'blocked', 'category' => 'false_positive', 'language' => 'any',
        'allowed' => [
            // English
            'scunthorpe', 'penistone', 'therapist', 'snigger', 'scrape', 'scrapbook', 'skyscraper', 'pussycat',
            'pussywillow', 'matsushita', 'shiitake', 'shitake', 'twatt', 'cracked', 'piste',
            // Albanian: shitje (sale), shitës (seller), shitore (shop)
            'shitje', 'shites', 'shitës', 'shitore', 'shitet',
            // names
            'pedersen', 'pederson',
        ],
    ],

    // --------------------------------------------------------------- reserved

    [
        'kind' => 'reserved', 'category' => 'staff', 'language' => 'any',
        'contains' => [
            'admin', 'админ', 'administrat', 'moderator', 'модератор', 'moderatori', 'support', 'поддршка',
            'podrshka', 'podrska', 'mbeshtetje', 'mbështetje', 'official', 'oficial', 'oficijal', 'официјал',
            'zyrtar', 'system', 'sysop', 'security', 'безбедност', 'siguria', 'noreply', 'donotreply', 'postmaster',
            'webmaster', 'hostmaster', 'superuser', 'helpdesk', 'verified', 'верификуван', 'verifikuvan',
            'verifikuar', 'staffmember', 'teammember', 'customerservice', 'корисничкаподдршка',
            'korisnichkapoddrshka', 'izbrishan', 'izbrisan', 'избришан', 'deletedaccount', 'deleteduser', 'anonymous', 'анонимен',
            'anonimen', 'anonim', 'moderacija', 'модерација', 'moderim', 'kontrolor', 'контролор',
        ],
        'exact' => [
            'mod', 'mods', 'мод', 'help', 'помош', 'pomosh', 'pomos', 'ndihmë', 'ndihme', 'info', 'инфо',
            'информации', 'informacii', 'root', 'staff', 'персонал', 'personel', 'team', 'тим', 'ekipa', 'екипа',
            'ekip', 'ekipi', 'sistem', 'систем', 'abuse', 'null', 'nil', 'none', 'undefined', 'nan', 'void',
            'unknown', 'непознат', 'deleted', 'removed', 'banned', 'suspended', 'корисник', 'korisnik', 'user',
            'users', 'member', 'members', 'член', 'clen', 'chlen', 'člen', 'членови', 'clenovi', 'anetar', 'anëtar',
            'guest', 'гостин', 'mysafir', 'test', 'тест', 'tester', 'owner', 'сопственик', 'pronar', 'bot', 'бот',
            'robot', 'everyone', 'сајт', 'site', 'web', 'here', 'all', 'сите', 'api', 'www', 'mail', 'email', 'smtp',
            'imap', 'contact', 'контакт', 'operator', 'оператор', 'host', 'server', 'localhost', 'dev',
            'developer', 'уредник', 'urednik', 'editor', 'redaktor', 'reviewer', 'рецензент', 'ombudsman',
        ],
    ],
    [
        'kind' => 'reserved', 'category' => 'brand', 'language' => 'any',
        'contains' => [
            'zdravje', 'здравје', 'zdravie', 'zdrawje', 'zdravje360', 'здравје360', 'shendeti360', 'shëndeti360', 'health360',
        ],
        'exact' => [
            'z360', 'zd360', 'zdr360', 'shendeti', 'shëndeti',
        ],
    ],
    [
        'kind' => 'reserved', 'category' => 'medical', 'language' => 'any',
        'contains' => [
            // titles
            'doktor', 'доктор', 'doctor', 'doktoresh', 'professor', 'profesor', 'професор', 'docent', 'доцент',
            'akademik', 'академик', 'primarius', 'примариус', 'primarijus',
            // professions
            'лекар', 'lekar', 'ljekar', 'specijalist', 'специјалист', 'specialist', 'specijalizant', 'специјализант',
            'specializant', 'medicinskasestra', 'медицинскасестра', 'medsestra', 'медсестра', 'infermier',
            'infermjer', 'hirurg', 'хирург', 'kirurg', 'surgeon', 'stomatolog', 'стоматолог', 'dentist', 'забоекар',
            'zaboekar', 'farmacevt', 'фармацевт', 'pharmacist', 'farmacist', 'apotekar', 'аптекар', 'aptekar',
            'psihijatar', 'психијатар', 'psychiatrist', 'psikiatër', 'psikiater', 'psiholog', 'психолог',
            'psychologist', 'psikolog', 'pedijatar', 'педијатар', 'pediatër', 'pediater', 'pediatrician',
            'ginekolog', 'гинеколог', 'gynecologist', 'gjinekolog', 'kardiolog', 'кардиолог', 'cardiologist',
            'nevrolog', 'невролог', 'neurolog', 'onkolog', 'онколог', 'oncologist', 'dermatolog', 'дерматолог',
            'oftalmolog', 'офталмолог', 'urolog', 'уролог', 'radiolog', 'радиолог', 'anesteziolog', 'анестезиолог',
            'internist', 'интернист', 'endokrinolog', 'ендокринолог', 'ortoped', 'ортопед', 'fizioterapevt',
            'физиотерапевт', 'physiotherapist', 'nutricionist', 'нутриционист', 'nutritionist', 'akusher',
            'акушер', 'paramedic', 'paramedik', 'парамедик', 'medicalteam', 'medicinskitim',
            // places that speak with authority
            'клиника', 'klinika', 'bolnica', 'болница', 'spital', 'hospital', 'zdravstvendom', 'здравствендом',
            'ambulanta', 'амбуланта', 'аптека', 'apteka', 'pharmacy', 'farmaci', 'barnatore',
        ],
        // Titles people glue to a name („drmarko“): also refused at the start
        // of a word before a consonant, never before a vowel (Dragan, Mjeku).
        'prefix' => [
            'dr', 'д-р', 'др', 'doc', 'prof', 'проф', 'mjek',
        ],
        'exact' => [
            'drs', 'док', 'md', 'mr', 'м-р', 'phd', 'dds', 'rn', 'np',
            'prim', 'прим', 'mjeke', 'mjekë', 'mjekja', 'mjekët', 'sestra', 'сестра', 'сестри',
            'nurse', 'nurses', 'infermierja', 'medic', 'медик', 'med', 'babica', 'бабица', 'mamia',
        ],
    ],
    [
        'kind' => 'reserved', 'category' => 'authority', 'language' => 'any',
        'contains' => [
            'ministerstvo', 'министерство', 'ministry', 'ministria', 'ministar', 'министер', 'minister', 'ministri',
            'фзом', 'fzom', 'фондзаздравство', 'fondzazdravstvo', 'komora', 'комора', 'odajemjekeve', 'policija',
            'полиција', 'police', 'policia', 'policja', 'policaj', 'полицаец', 'qeveria', 'government', 'sobranie',
            'собрание', 'parlament', 'parliament', 'kuvendi', 'pretsedatel', 'претседател', 'president',
            'presidenti', 'premier', 'премиер', 'kryeministr', 'народенправобранител', 'avokatipopullit',
            'inspektorat', 'инспекторат', 'malmed', 'малмед', 'azlp', 'азлп', 'dzlp', 'дзлп', 'agencija',
            'агенција', 'agjencia', 'crvenkrst', 'црвенкрст', 'redcross', 'kryqikuq', 'unicef', 'уницеф',
            'worldhealth', 'svetskazdravstvena', 'институтзајавноздравје', 'ijzrsm', 'ијзрсм', 'centarzajavnozdravje',
            'covid19info', 'koronainfo', 'gjykata', 'obvinitel', 'обвинител', 'prokuror', 'прокурор', 'интерпол',
            'interpol', 'europol', 'армија', 'armija', 'ushtria',
        ],
        'exact' => [
            'mvr', 'мвр', 'ujp', 'ујп', 'mzs', 'мзс', 'mz', 'мз', 'fzo', 'фзо', 'who', 'кзо', 'oon', 'оон', 'un',
            'ue', 'eu', 'еу', 'nato', 'нато', 'osce', 'оебс', 'sud', 'суд', 'drzhava', 'држава', 'shteti', 'state',
            'opshtina', 'општина', 'komuna', 'mvp', 'мвп', 'anb', 'анб', 'ubk', 'убк', 'vladata',
            'владата', 'army', 'цјз',
        ],
    ],
    [
        'kind' => 'reserved', 'category' => 'route', 'language' => 'any',
        'exact' => [
            'about', 'account', 'accounts', 'admin', 'api', 'auth', 'login', 'logout', 'signin', 'signout', 'signup',
            'register', 'registration', 'najava', 'најава', 'odjava', 'одјава', 'registracija', 'регистрација',
            'forum', 'форум', 'forums', 'doctors', 'doctor', 'lekari', 'лекари', 'facilities', 'facility',
            'ustanovi', 'установи', 'pharmacies', 'pharmacy', 'apteki', 'аптеки', 'products', 'product', 'proizvodi',
            'производи', 'search', 'baraj', 'барај', 'prebaruvanje', 'пребарување', 'guidance', 'nasoki', 'насоки',
            'privacy', 'privatnost', 'приватност', 'terms', 'uslovi', 'услови', 'disclaimer', 'verify',
            'verify-email', 'forgot-password', 'reset-password', 'password', 'lozinka', 'лозинка', 'design-system',
            'sitemap', 'robots', 'llms', 'feed', 'rss', 'settings', 'me', 'profile', 'profil', 'профил',
            'dashboard', 'doctor-dashboard', 'my-profile', 'moj-profil', 'мој-профил', 'transparency',
            'transparentnost', 'транспарентност', 'home', 'pocetna', 'pochetna', 'почетна', 'index', 'static',
            'assets', 'public', 'media', 'uploads', 'images', 'favicon', 'reviews', 'review', 'recenzii', 'рецензии',
            'reports', 'report', 'prijava', 'пријава', 'devices', 'data', 'export', 'new', 'edit', 'create',
            'delete', 'update', 'specialties', 'departments', 'languages', 'triage', 'health', 'status', 'faq',
            'blog', 'news', 'vesti', 'вести', 'notifications', 'messages', 'poraki', 'пораки', 'inbox', 'oauth',
            'callback', 'webhook', 'webhooks', 'csp', 'session', 'sessions', 'username', 'usernames',
        ],
    ],
];
