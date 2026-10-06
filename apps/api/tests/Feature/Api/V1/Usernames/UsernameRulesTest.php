<?php

namespace Tests\Feature\Api\V1\Usernames;

use App\Enums\UsernameMatchType;
use App\Enums\UsernameTermKind;
use App\Models\User;
use App\Models\UsernameTerm;
use App\Support\Usernames\UsernameTermMatcher;
use App\Support\Usernames\UsernameValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What a username may be (UsernameValidator), against the shipped lists the
 * migration seeds.
 */
class UsernameRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function badFormats(): array
    {
        return [
            'too short' => ['ab', 'length'],
            'too long' => [str_repeat('a', 31), 'length'],
            'space' => ['ana marija', 'alphabet'],
            'emoji' => ['ana😀', 'alphabet'],
            'at sign' => ['@marija', 'alphabet'],
            'non-Macedonian Cyrillic я' => ['Наталя', 'alphabet'],
            'non-Macedonian Cyrillic й' => ['Андрей', 'alphabet'],
            'Serbian ђ' => ['Ђорђе', 'alphabet'],
            'Greek alpha' => ['Αdmin', 'alphabet'],
            'combining mark' => ["Ad\u{034F}min", 'alphabet'],
            'starts with a digit' => ['1marko', 'start'],
            'starts with a separator' => ['_marko', 'start'],
            'two separators' => ['ana__marija', 'separators'],
            'dot and dash' => ['ana.-marija', 'separators'],
            'Cyrillic and Latin mixed' => ['Аdmin', 'mixed_script'],
            'mixed in a name' => ['Mарија', 'mixed_script'],
        ];
    }

    #[DataProvider('badFormats')]
    public function test_the_format_is_enforced(string $username, string $problem): void
    {
        $this->assertSame($problem, UsernameValidator::problem($username));
    }

    public function test_good_formats_pass(): void
    {
        foreach (['ana', 'Ана', 'marko_s', 'Марко.С', 'ana-marija', 'jovan1990', 'Çelë', 'Šaban', 'Ѕвездан', 'Ѓорѓи', 'a1b', str_repeat('а', 30)] as $username) {
            $this->assertNull(UsernameValidator::problem($username), $username);
        }
    }

    /**
     * A sample per category and language; the lists hold far more.
     *
     * @return array<string, array{string}>
     */
    public static function blockedNames(): array
    {
        return [
            // profanity and sexual — English, with evasions
            'en profanity' => ['fuck'],
            'en profanity inside' => ['ana_fucker'],
            'en leetspeak' => ['sh1thead'],
            'en separators' => ['f.u.c.k'],
            'en repeated letters' => ['fuuuuuck'],
            'en upper case' => ['MOTHERFUCKER'],
            'en sexual' => ['pornstar'],
            'en look-alike Cyrillic' => ['СОСК'],
            // Macedonian, both scripts
            'mk Cyrillic' => ['пичка'],
            'mk Latin' => ['pichka'],
            'mk Latin without diacritics' => ['picka_ti'],
            'mk leetspeak' => ['p1chka'],
            'mk inside a name' => ['ebam_ti_mamicata'],
            'mk exact word' => ['kur'],
            'mk sexual' => ['порно_ана'],
            // Albanian
            'sq profanity' => ['qifsha'],
            'sq exact word' => ['pidh'],
            'sq sexual' => ['seksi_vajza'],
            // slurs
            'ethnic en' => ['n1gg3r'],
            'ethnic mk' => ['шиптар'],
            'ethnic mk Latin' => ['shiptari'],
            'ethnic sq' => ['magjup'],
            'ethnic sq word' => ['shkau'],
            'religious mk' => ['потурица'],
            'homophobic en' => ['faggot'],
            'homophobic mk' => ['педер'],
            'ableist en' => ['retard'],
            'ableist mk' => ['малоумник'],
            // hate, drugs, scams
            'hate en' => ['hitler_fan'],
            'hate mk' => ['фашист'],
            'hate number' => ['marko_hh88'],
            'drugs en' => ['cocaine_dealer'],
            'drugs mk' => ['марихуана'],
            'drugs sq' => ['kokainë'],
            'drugs number word' => ['weed_420'],
            'scam en' => ['bitcoin_profit'],
            'scam mk' => ['брзкредит'],
            'scam sq' => ['parafalas'],
            'contact farming' => ['whatsapp_me'],
            // LDNOOBW (CC BY 4.0)
            'LDNOOBW phrase' => ['bluewaffle'],
        ];
    }

    #[DataProvider('blockedNames')]
    public function test_blocked_terms_are_refused(string $username): void
    {
        $this->assertSame('not_allowed', UsernameValidator::problem($username));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedNames(): array
    {
        return [
            'admin' => ['admin'],
            'admin inside' => ['SuperAdmin2'],
            'admin Cyrillic' => ['Администратор'],
            'admin full-width' => ['ａｄｍｉｎ'],
            'moderator' => ['moderator_ana'],
            'mod as a word' => ['mod.ana'],
            'support' => ['podrska'],
            'staff' => ['staff'],
            'system' => ['system_bot'],
            'official' => ['oficijalen'],
            'platform' => ['zdravje360'],
            'platform Cyrillic' => ['Здравје'],
            'platform inside' => ['moe_zdravje'],
            'doctor title as a word' => ['dr.marko'],
            'doctor title after the name' => ['marko_dr'],
            'doctor title glued to a digit' => ['Dr1'],
            'Cyrillic title' => ['д-р.петар'],
            'doctor' => ['doktor_ana'],
            'doctor Cyrillic' => ['Доктор'],
            'physician mk' => ['лекар_марко'],
            'physician sq' => ['mjek'],
            'nurse mk' => ['сестра'],
            'nurse sq' => ['infermierja_ana'],
            'professor' => ['prof.ana'],
            'specialist' => ['specijalist_kardio'],
            'pharmacist' => ['farmacevt_bt'],
            'ministry' => ['ministerstvo'],
            'ministry Cyrillic' => ['Министерство'],
            'health fund' => ['fzom_info'],
            'chamber' => ['lekarska_komora'],
            'police' => ['policija'],
            'police Cyrillic' => ['Полиција'],
            'route login' => ['login'],
            'route forum' => ['forum'],
            'route doctors' => ['doctors'],
            'placeholder look-alike' => ['clen-abc123'],
            'deleted user' => ['izbrishan_korisnik'],
            // Titles glued to a name by case or by nothing at all.
            'camelCase DrMarko' => ['DrMarko'],
            'camelCase drPetrov' => ['drPetrov'],
            'camelCase ДрМарко' => ['ДрМарко'],
            'camelCase ProfIvanov' => ['ProfIvanov'],
            'underscore Dr_Marko' => ['Dr_Marko'],
            'hyphenated д-рМарко' => ['д-рМарко'],
            'glued drmarko' => ['drmarko'],
            'glued дрмарко' => ['дрмарко'],
            'glued profpetrov' => ['profpetrov'],
            'glued профстојанов' => ['профстојанов'],
            'glued docpetrov' => ['docpetrov'],
            'glued mjekfatmir' => ['mjekfatmir'],
            'glued in a second word' => ['ana.DrMarko'],
        ];
    }

    #[DataProvider('reservedNames')]
    public function test_reserved_terms_are_refused(string $username): void
    {
        $this->assertSame('not_allowed', UsernameValidator::problem($username));
    }

    /**
     * The Scunthorpe problem: names and words that contain a listed term.
     *
     * @return array<string, array{string}>
     */
    public static function innocentNames(): array
    {
        return [
            'Scunthorpe (allowed exception)' => ['Scunthorpe'],
            'therapist (allowed exception)' => ['therapist_ana'],
            'Penistone (allowed exception)' => ['Penistone'],
            'Albanian shitore — shop (allowed exception)' => ['shitore_ana'],
            'Dragan — dr is a word only' => ['Dragan'],
            'Drita — a vowel after dr' => ['Drita'],
            'Драган — a vowel after др' => ['Драган'],
            'Drenusha' => ['Drenusha'],
            'Profi — a vowel after prof' => ['profi_ana'],
            'Docevski' => ['Docevski'],
            'Kurtishi' => ['Kurtishi'],
            'Cocev' => ['Cocev'],
            'Analena — anal is a word only' => ['Analena'],
            'Nazif — nazi is a word only' => ['Nazif'],
            'Slagjana — slag is a word only' => ['Slagjana'],
            'Vladan' => ['Vladan'],
            'Golabovski' => ['Golabovski'],
            'Zdravevski — not the platform' => ['Zdravevski'],
            'Semenov' => ['Semenov'],
            'Montenegro' => ['Montenegro'],
            'classic' => ['classic_ana'],
            'number that folds to ss' => ['marko55'],
            'cc is not Cyrillic сс' => ['ana.cc'],
            'Mjeku — Albanian surname' => ['Arben_Mjeku'],
            'Shpend' => ['Shpend'],
            'Sokol' => ['Sokol'],
            'Ermira' => ['Ermira'],
            'Fatmir' => ['Fatmir'],
            'Анастасија' => ['Анастасија'],
            'Ристо' => ['Ристо'],
            'Ѓорѓи' => ['Ѓорѓи'],
            'Бујар' => ['Бујар'],
            'Сашо' => ['Сашо'],
            'Ана' => ['Ана'],
        ];
    }

    #[DataProvider('innocentNames')]
    public function test_names_that_merely_contain_a_term_are_allowed(string $username): void
    {
        $this->assertNull(UsernameValidator::problem($username));
    }

    public function test_an_allowed_exception_excuses_only_itself(): void
    {
        $this->assertNull(UsernameValidator::problem('scunthorpe'));
        $this->assertSame('not_allowed', UsernameValidator::problem('scunthorpe_cunt'));
        $this->assertSame('not_allowed', UsernameValidator::problem('therapist_rapist'));
    }

    public function test_existing_title_rows_become_word_start_terms(): void
    {
        // A database seeded before titles were `prefix` terms.
        UsernameTerm::query()->where('match_type', UsernameMatchType::Prefix->value)->update(['match_type' => UsernameMatchType::Exact->value]);
        UsernameTermMatcher::forget();
        $this->assertNull(UsernameValidator::problem('drmarko'));

        (require database_path('migrations/2026_10_14_150001_match_doctor_titles_at_word_start.php'))->up();

        $this->assertSame(UsernameMatchType::Prefix, UsernameTerm::query()->where('term', 'dr')->sole()->match_type);
        $this->assertSame('not_allowed', UsernameValidator::problem('drmarko'));
        $this->assertNull(UsernameValidator::problem('Dragan'));
    }

    public function test_staff_exceptions_take_effect(): void
    {
        $this->assertSame('not_allowed', UsernameValidator::problem('lekarovski'));

        UsernameTerm::query()->create([
            'term' => 'Lekarovski',
            'kind' => UsernameTermKind::Reserved,
            'match_type' => UsernameMatchType::Allowed,
            'language' => 'any',
            'category' => 'false_positive',
        ]);

        $this->assertNull(UsernameValidator::problem('lekarovski'));
    }

    /**
     * Real Macedonian, Albanian, Turkish, Roma, Bosniak and Serbian names in
     * North Macedonia, and words that contain a listed term. Every one must
     * be accepted (the list is tests/…/Usernames/real_names.php).
     */
    public function test_real_names_pass(): void
    {
        /** @var list<string> $names */
        $names = require __DIR__.'/real_names.php';
        $this->assertGreaterThan(400, count($names));

        $refused = [];
        foreach ($names as $name) {
            if (UsernameValidator::problem($name) !== null) {
                $refused[] = $name;
            }
        }

        $this->assertSame([], $refused, 'Real names refused: '.implode(', ', $refused));
    }

    public function test_uniqueness_is_on_the_folded_forms(): void
    {
        User::factory()->create(['username' => 'Marko_S']);
        User::factory()->create(['username' => 'papa']);

        foreach (['marko_s', 'MARKO.S', 'Марко_С', 'm4rk0s', 'marko-s', 'markos'] as $same) {
            $this->assertSame('taken', UsernameValidator::problem($same), $same);
        }

        // Cyrillic „рара“ reads "rara" but looks like Latin „papa“.
        $this->assertSame('taken', UsernameValidator::problem('рара'));
        $this->assertNull(UsernameValidator::problem('marko_t'));
    }

    public function test_the_message_never_says_which_list_matched(): void
    {
        $blocked = $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/usernames/availability?username=pichka')
            ->assertOk()
            ->json('data.message');
        $reserved = $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/usernames/availability?username=admin')
            ->assertOk()
            ->json('data.message');

        $this->assertSame('Ова корисничко име не е дозволено.', $blocked);
        $this->assertSame($blocked, $reserved);
    }

    public function test_the_shipped_lists_cover_every_language_and_category(): void
    {
        foreach (['en', 'mk', 'sq'] as $language) {
            foreach (['profanity', 'sexual', 'slur_ethnic', 'hate', 'drugs', 'scam'] as $category) {
                $this->assertTrue(
                    UsernameTerm::query()->where('language', $language)->where('category', $category)->exists(),
                    "No {$category} terms in {$language}",
                );
            }
        }

        foreach (['staff', 'brand', 'medical', 'authority', 'route'] as $category) {
            $this->assertTrue(UsernameTerm::query()->where('kind', 'reserved')->where('category', $category)->exists(), $category);
        }

        // Scunthorpe guard: nothing shorter than four letters is matched inside words.
        $short = UsernameTerm::query()
            ->where('match_type', UsernameMatchType::Contains)
            ->get()
            ->filter(fn (UsernameTerm $term): bool => strlen($term->term_normalized) < UsernameMatchType::CONTAINS_MIN_LENGTH)
            ->pluck('term')
            ->all();
        $this->assertSame([], $short);
    }
}
