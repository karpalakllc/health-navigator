<?php

namespace Database\Seeders;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use App\Support\DeploymentEnvironment;
use App\Support\DisplayName;
use App\Support\ReviewAggregates;
use App\Support\RoleCatalog;
use App\Support\TaxonomyCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Realistic-volume data for measuring query plans (docs/performance.md).
 *
 * Opt-in only — it is not part of DatabaseSeeder — and refused in any
 * deployment. Run it on a disposable database:
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=PerfSeeder
 *
 * Writes with chunked multi-row inserts (no model events, no Scout sync), so
 * ~250k rows land in well under a minute. Deterministic: the same seed yields
 * the same data, which keeps before/after EXPLAINs comparable.
 */
class PerfSeeder extends Seeder
{
    public const DOCTORS = 5000;

    public const CLINICAL_FACILITIES = 1500;

    public const PHARMACIES = 800;

    public const PRODUCTS = 1500;

    public const PRODUCTS_PER_PHARMACY = 120;

    public const MEMBERS = 2500;

    public const REVIEWS = 50000;

    public const FORUM_TOPICS = 3000;

    public const FORUM_POSTS = 20000;

    private const CHUNK = 1000;

    private const EMAIL_DOMAIN = 'perf.zdravje360.test';

    private const FIRST_NAMES = [
        'Ана', 'Марија', 'Елена', 'Ивана', 'Билјана', 'Снежана', 'Весна', 'Јасмина', 'Катерина', 'Мартина',
        'Сара', 'Теодора', 'Маја', 'Наташа', 'Даниела', 'Ќиро', 'Ѓорѓи', 'Марко', 'Никола', 'Александар',
        'Стефан', 'Димитар', 'Бојан', 'Горан', 'Зоран', 'Дејан', 'Игор', 'Филип', 'Петар', 'Љупчо',
        'Влатко', 'Тони', 'Кире', 'Благој', 'Џемаил', 'Ѕвезданка', 'Љубица', 'Наум', 'Методија', 'Трајче',
    ];

    private const SURNAMES = [
        'Петровски', 'Стојанов', 'Николовски', 'Трајковски', 'Јованов', 'Ристески', 'Димитриевски', 'Ангеловски',
        'Георгиев', 'Илиевски', 'Митревски', 'Спасовски', 'Костадинов', 'Атанасов', 'Велјановски', 'Ѓорѓиевски',
        'Пауновски', 'Наумовски', 'Тодоров', 'Цветковски', 'Јакимовски', 'Ќосевски', 'Јанкуловски', 'Каранфиловски',
        'Лазаревски', 'Манев', 'Најдовски', 'Шаревски', 'Чаушев', 'Џамбазов', 'Жежовски', 'Бошковски',
    ];

    /** [Cyrillic, Latin, weight] — Skopje dominates as it does in reality. */
    private const CITIES = [
        ['Скопје', 'Skopje', 30], ['Битола', 'Bitola', 8], ['Куманово', 'Kumanovo', 8], ['Прилеп', 'Prilep', 6],
        ['Тетово', 'Tetovo', 6], ['Велес', 'Veles', 4], ['Штип', 'Shtip', 4], ['Охрид', 'Ohrid', 5],
        ['Гостивар', 'Gostivar', 4], ['Струмица', 'Strumica', 4], ['Кавадарци', 'Kavadarci', 3], ['Кочани', 'Kochani', 3],
        ['Кичево', 'Kichevo', 2], ['Струга', 'Struga', 3], ['Радовиш', 'Radovish', 2], ['Гевгелија', 'Gevgelija', 2],
        ['Дебар', 'Debar', 1], ['Крива Паланка', 'Kriva Palanka', 1], ['Свети Николе', 'Sveti Nikole', 1],
        ['Неготино', 'Negotino', 1], ['Делчево', 'Delchevo', 1], ['Ресен', 'Resen', 1], ['Берово', 'Berovo', 1],
        ['Крушево', 'Krushevo', 1], ['Валандово', 'Valandovo', 1], ['Демир Хисар', 'Demir Hisar', 1],
    ];

    private const SPECIALTIES = [
        'Кардиологија', 'Дерматологија', 'Педијатрија', 'Гинекологија', 'Ортопедија', 'Неврологија', 'Офталмологија',
        'Оториноларингологија', 'Ендокринологија', 'Гастроентерологија', 'Пулмологија', 'Урологија', 'Психијатрија',
        'Општа медицина', 'Нефрологија', 'Ревматологија', 'Онкологија', 'Физикална медицина', 'Стоматологија',
        'Хирургија',
    ];

    private const DEPARTMENTS = [
        'Ургентен центар', 'Интерно одделение', 'Хируршко одделение', 'Радиологија', 'Лабораторија',
        'Педијатриско одделение', 'Гинекологија и акушерство', 'Кардиологија', 'Физикална терапија',
        'Офталмологија', 'Стоматологија', 'Дијагностика',
    ];

    private const FORUM_CATEGORIES = [
        'Општо здравје', 'Срце и крвни садови', 'Деца и бременост', 'Ментално здравје', 'Исхрана', 'Лекови и аптеки',
        'Хронични болести', 'Искуства со лекари',
    ];

    private const FACILITY_PREFIXES = [
        'clinic' => ['ПЗУ', 'Поликлиника', 'Специјалистичка ординација', 'Клиника', 'Ordinacija'],
        'hospital' => ['Општа болница', 'Клиничка болница', 'Специјална болница', 'Bolnica'],
        'laboratory' => ['Лабораторија', 'Биохемиска лабораторија', 'Дијагностички центар', 'Laboratorija'],
    ];

    private const FACILITY_NAMES = [
        'Здравје', 'Медика', 'Вита', 'Сигма', 'Хипократ', 'Панацеа', 'Нова медика', 'Медикус', 'Феникс', 'Алфа',
        'Аџибадем', 'Кардиомед', 'Сана', 'Ремедика', 'Плус', 'Дентал', 'Оптима', 'Неуромед', 'Bios', 'Medicus',
    ];

    private const PHARMACY_NAMES = ['Аптека Зегин', 'Аптека Прима', 'Аптека Еуролек', 'Аптека Неофарм', 'Аптека Здравје', 'Apteka Vita', 'Аптека Ремедика'];

    private const PRODUCT_STEMS = [
        'Парацетамол', 'Ибупрофен', 'Аспирин', 'Витамин Ц', 'Витамин Д3', 'Магнезиум', 'Цинк', 'Омега 3', 'Пробиотик',
        'Лоратадин', 'Пантопразол', 'Амоксицилин', 'Диклофенак гел', 'Сируп за кашлица', 'Капки за нос', 'Термометар',
        'Маска за лице', 'Крема за сончање', 'Paracetamol', 'Ibuprofen', 'Vitamin C', 'Magnezium',
    ];

    private const PRODUCT_FORMS = ['500 mg таблети', '200 mg таблети', '1000 mg шумливи', '30 капсули', '60 капсули', '100 ml', '20 g', 'спреј 15 ml', 'кесички'];

    private const PRODUCT_CATEGORIES = ['Лекови без рецепт', 'Витамини и минерали', 'Козметика', 'Медицински помагала', 'Бебешка нега'];

    private const TOPIC_TITLES = [
        'Кој кардиолог препорачувате во %s?', 'Искуство со ПЗУ во %s', 'Висок притисок — што да правам?',
        'Алергија на полен, совети?', 'Детето има температура три дена', 'Каде да направам крвна слика во %s?',
        'Бессоние и анксиозност', 'Дијабетес тип 2 и исхрана', 'Болка во грбот по работа', 'Вакцинација за деца',
        'Kade ima dezhurna apteka vo %s?', 'Мигрена секоја недела', 'Искуства со физикална терапија',
    ];

    private string $now;

    private int $nextSlugSuffix = 1;

    public function run(): void
    {
        if (DeploymentEnvironment::isDeployed()) {
            $this->command?->error('PerfSeeder refused: it only runs in '.implode('/', DeploymentEnvironment::NON_DEPLOYED).'.');

            return;
        }

        if (DB::table('users')->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            $this->command?->warn('PerfSeeder skipped: volume data is already present (run migrate:fresh first).');

            return;
        }

        mt_srand(360);
        $this->now = now()->toDateTimeString();
        $started = microtime(true);

        DB::connection()->disableQueryLog();

        $members = $this->seedMembers();
        $specialties = $this->ensureTaxonomy('specialties', self::SPECIALTIES);
        $departments = $this->ensureTaxonomy('departments', self::DEPARTMENTS);
        $categories = $this->ensureForumCategories();

        $clinical = $this->seedFacilities(self::CLINICAL_FACILITIES, pharmacy: false);
        $pharmacies = $this->seedFacilities(self::PHARMACIES, pharmacy: true);
        $doctors = $this->seedDoctors($specialties, $clinical);
        $this->seedDepartments($clinical, $departments);
        $this->seedPharmacyCatalog($pharmacies);
        $this->seedReviews($members, $doctors, $clinical, $pharmacies);
        $this->seedForum($members, $categories);

        if (Schema::hasColumn('doctors', 'reviews_count')) {
            ReviewAggregates::recomputeAll();
        }

        // Fresh planner statistics, so EXPLAINs taken right after seeding are
        // not planned against an empty-table estimate. The aggregate backfill
        // and the replies_count pass rewrote every doctor, facility and topic
        // row; VACUUM FULL drops those dead versions so a seq scan is costed
        // at the table's real size rather than double it.
        // VACUUM cannot run inside a transaction block, which is where the seeder
        // runs under RefreshDatabase; ANALYZE alone still refreshes statistics.
        if (DB::connection()->getDriverName() === 'pgsql') {
            if (DB::transactionLevel() === 0) {
                foreach (['doctors', 'facilities', 'forum_topics'] as $table) {
                    DB::statement('VACUUM (FULL, ANALYZE) '.$table);
                }
            }

            foreach (['users', 'specialties', 'departments', 'forum_categories', 'doctor_specialty', 'doctor_facility', 'department_facility', 'products', 'pharmacy_product', 'reviews', 'forum_posts'] as $table) {
                DB::statement('ANALYZE '.$table);
            }
        }

        // Taxonomies were written with the query builder (no model events), so
        // the cached lists and their doctor/facility counts would otherwise be
        // served stale for up to TaxonomyCache::TTL_SECONDS.
        TaxonomyCache::flush(TaxonomyCache::SPECIALTIES, TaxonomyCache::DEPARTMENTS, TaxonomyCache::FORUM_CATEGORIES);

        $this->command?->info(sprintf('PerfSeeder finished in %.1fs.', microtime(true) - $started));
    }

    /**
     * @return list<int>
     */
    private function seedMembers(): array
    {
        // One hash for everyone: bcrypt per row would dominate the runtime.
        $password = Hash::make('password');
        $rows = [];

        for ($i = 1; $i <= self::MEMBERS; $i++) {
            $name = $this->personName();
            $rows[] = [
                'name' => $name,
                // Public surfaces render display_name; leaving it null would
                // measure the accessor's fallback rather than the real column.
                'display_name' => DisplayName::suggest($name),
                'email' => "member{$i}@".self::EMAIL_DOMAIN,
                'email_verified_at' => $this->now,
                'password' => $password,
                'user_kind' => 'client',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        $ids = $this->insertReturningIds('users', $rows);

        // Members are Spatie Member-role holders as registration makes them
        // (AuthController); the deprecated users.role column is left null.
        $memberRole = RoleCatalog::ensure(RoleCatalog::MEMBER)->getKey();
        $morphClass = (new User)->getMorphClass();

        $this->insertChunked(config('permission.table_names.model_has_roles'), array_map(
            fn (int $id): array => [
                'role_id' => $memberRole,
                'model_type' => $morphClass,
                'model_id' => $id,
            ],
            $ids,
        ));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $ids;
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function ensureTaxonomy(string $table, array $names): array
    {
        foreach ($names as $index => $name) {
            $slug = Str::slug($name);

            if (! DB::table($table)->where('slug', $slug)->exists()) {
                DB::table($table)->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'sort_order' => $index,
                    'is_published' => true,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }

        return DB::table($table)->where('is_published', true)->whereNull('deleted_at')->pluck('id')->all();
    }

    /**
     * @return list<int>
     */
    private function ensureForumCategories(): array
    {
        foreach (self::FORUM_CATEGORIES as $index => $name) {
            $slug = Str::slug($name);

            if (! DB::table('forum_categories')->where('slug', $slug)->exists()) {
                DB::table('forum_categories')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'sort_order' => 100 + $index,
                    'is_published' => true,
                    'published_at' => $this->now,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }

        return DB::table('forum_categories')->where('is_published', true)->pluck('id')->all();
    }

    /**
     * @return list<int>
     */
    private function seedFacilities(int $count, bool $pharmacy): array
    {
        $rows = [];
        $clinicalTypes = ['clinic', 'clinic', 'clinic', 'laboratory', 'hospital'];

        for ($i = 0; $i < $count; $i++) {
            [$city, $latinCity] = $this->city();
            $type = $pharmacy ? FacilityType::Pharmacy->value : $this->pick($clinicalTypes);
            $name = $pharmacy
                ? $this->pick(self::PHARMACY_NAMES).' '.mt_rand(1, 60)
                : $this->pick(self::FACILITY_PREFIXES[$type]).' '.$this->pick(self::FACILITY_NAMES).' '.mt_rand(1, 99);
            $displayCity = mt_rand(1, 100) <= 10 ? $latinCity : $city;

            $rows[] = [
                'slug' => $this->slug($name.' '.$latinCity),
                'name' => $name,
                'type' => $type,
                'description' => 'Здравствена установа во '.$city.'.',
                'city' => $displayCity,
                'address' => 'ул. '.$this->pick(self::SURNAMES).' бр. '.mt_rand(1, 200),
                'phone' => '+389 2 '.mt_rand(100, 999).' '.mt_rand(1000, 9999),
                'is_published' => mt_rand(1, 100) <= 95,
                'published_at' => $this->now,
                'is_featured' => mt_rand(1, 100) <= 2,
                'has_emergency_services' => $type === 'hospital' && mt_rand(0, 1) === 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        return $this->insertReturningIds('facilities', $rows);
    }

    /**
     * @param  list<int>  $specialties
     * @param  list<int>  $facilities
     * @return list<int>
     */
    private function seedDoctors(array $specialties, array $facilities): array
    {
        $rows = [];

        for ($i = 0; $i < self::DOCTORS; $i++) {
            [$city, $latinCity] = $this->city();
            $latin = mt_rand(1, 100) <= 15;
            $name = $this->personName();
            $fullName = $latin ? 'dr. '.$this->toLatin($name) : 'д-р '.$name;

            $rows[] = [
                'slug' => $this->slug($name),
                'full_name' => $fullName,
                'title' => $this->pick(['Специјалист', 'Субспецијалист', 'Доктор на медицина', 'Примариус']),
                'bio' => 'Долгогодишно искуство во '.$city.'.',
                'city' => $latin ? $latinCity : $city,
                'years_experience' => mt_rand(1, 40),
                'is_published' => mt_rand(1, 100) <= 95,
                'published_at' => $this->now,
                'accepts_new_patients' => mt_rand(1, 100) <= 80,
                'is_featured' => mt_rand(1, 100) <= 2,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        $ids = $this->insertReturningIds('doctors', $rows);

        $specialtyRows = [];
        $facilityRows = [];

        foreach ($ids as $doctorId) {
            foreach ($this->pickMany($specialties, mt_rand(1, 2)) as $index => $specialtyId) {
                $specialtyRows[] = [
                    'doctor_id' => $doctorId,
                    'specialty_id' => $specialtyId,
                    'is_primary' => $index === 0,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }

            foreach ($this->pickMany($facilities, mt_rand(1, 2)) as $index => $facilityId) {
                $facilityRows[] = [
                    'doctor_id' => $doctorId,
                    'facility_id' => $facilityId,
                    'is_primary' => $index === 0,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        $this->insertChunked('doctor_specialty', $specialtyRows);
        $this->insertChunked('doctor_facility', $facilityRows);

        return $ids;
    }

    /**
     * @param  list<int>  $facilities
     * @param  list<int>  $departments
     */
    private function seedDepartments(array $facilities, array $departments): void
    {
        $rows = [];

        foreach ($facilities as $facilityId) {
            foreach ($this->pickMany($departments, mt_rand(2, 6)) as $departmentId) {
                $rows[] = [
                    'facility_id' => $facilityId,
                    'department_id' => $departmentId,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        $this->insertChunked('department_facility', $rows);
    }

    /**
     * @param  list<int>  $pharmacies
     */
    private function seedPharmacyCatalog(array $pharmacies): void
    {
        $rows = [];

        for ($i = 0; $i < self::PRODUCTS; $i++) {
            $name = $this->pick(self::PRODUCT_STEMS).' '.$this->pick(self::PRODUCT_FORMS).' '.$this->pick(['Алкалоид', 'Replek', 'Хемофарм', 'Bayer', 'Сандоз', 'Krka']);

            $rows[] = [
                'slug' => $this->slug($name),
                'name' => $name,
                'description' => 'Производ за '.$this->pick(['возрасни', 'деца', 'целото семејство']).'.',
                'category' => $this->pick(self::PRODUCT_CATEGORIES),
                'is_published' => mt_rand(1, 100) <= 95,
                'published_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        $products = $this->insertReturningIds('products', $rows);

        $offers = [];

        foreach ($pharmacies as $pharmacyId) {
            foreach ($this->pickMany($products, self::PRODUCTS_PER_PHARMACY) as $productId) {
                $offers[] = [
                    'facility_id' => $pharmacyId,
                    'product_id' => $productId,
                    'price' => mt_rand(5000, 250000) / 100,
                    'currency' => 'MKD',
                    'is_available' => mt_rand(1, 100) <= 90,
                    'price_updated_at' => $this->now,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        $this->insertChunked('pharmacy_product', $offers);
    }

    /**
     * Each member reviews a distinct set of targets, so the
     * (user, reviewable) unique key never collides.
     *
     * @param  list<int>  $members
     * @param  list<int>  $doctors
     * @param  list<int>  $clinical
     * @param  list<int>  $pharmacies
     */
    private function seedReviews(array $members, array $doctors, array $clinical, array $pharmacies): void
    {
        $perMember = intdiv(self::REVIEWS, count($members));
        $rows = [];
        $bodies = [
            'Одлично искуство, внимателен пристап и јасни објаснувања.',
            'Долго чекање, но услугата беше добра.',
            'Љубезен персонал, чисто и уредно.',
            'Не сум задоволен од третманот.',
            'Препорачувам, многу професионално.',
            null,
        ];

        foreach ($members as $userId) {
            $doctorCount = (int) round($perMember * 0.7);
            $facilityCount = (int) round($perMember * 0.22);
            $targets = [];

            foreach ($this->pickMany($doctors, $doctorCount) as $id) {
                $targets[] = [Doctor::class, $id];
            }

            foreach ($this->pickMany($clinical, $facilityCount) as $id) {
                $targets[] = [Facility::class, $id];
            }

            foreach ($this->pickMany($pharmacies, $perMember - $doctorCount - $facilityCount) as $id) {
                $targets[] = [Facility::class, $id];
            }

            foreach ($targets as [$type, $id]) {
                $roll = mt_rand(1, 100);
                $status = $roll <= 88 ? 'approved' : ($roll <= 96 ? 'pending' : 'rejected');
                $publishedAt = now()->subMinutes(mt_rand(1, 60 * 24 * 365))->toDateTimeString();

                $rows[] = [
                    'user_id' => $userId,
                    'reviewable_type' => $type,
                    'reviewable_id' => $id,
                    'rating' => $this->pick([5, 5, 5, 4, 4, 4, 3, 2, 1]),
                    'body' => $this->pick($bodies),
                    'status' => $status,
                    'published_at' => $status === 'approved' ? $publishedAt : null,
                    'moderated_at' => $status === 'pending' ? null : $publishedAt,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ];
            }
        }

        $this->insertChunked('reviews', $rows);
    }

    /**
     * Topics and posts are generated together so replies_count and
     * last_post_at agree with the approved posts, as the app maintains them.
     *
     * @param  list<int>  $members
     * @param  list<int>  $categories
     */
    private function seedForum(array $members, array $categories): void
    {
        $topics = [];
        $topicMeta = [];

        for ($i = 0; $i < self::FORUM_TOPICS; $i++) {
            [$city] = $this->city();
            $title = sprintf($this->pick(self::TOPIC_TITLES), $city);
            $approved = mt_rand(1, 100) <= 90;
            $publishedAt = now()->subMinutes(mt_rand(60, 60 * 24 * 365));

            $topics[] = [
                'forum_category_id' => $this->pick($categories),
                'user_id' => $this->pick($members),
                'slug' => $this->slug($title),
                'title' => $title,
                'body' => 'Би сакал/а да слушнам искуства од други корисници.',
                'status' => $approved ? 'approved' : 'pending',
                'is_pinned' => mt_rand(1, 1000) <= 5,
                'published_at' => $approved ? $publishedAt->toDateTimeString() : null,
                'last_post_at' => $approved ? $publishedAt->toDateTimeString() : null,
                'created_at' => $publishedAt->toDateTimeString(),
                'updated_at' => $publishedAt->toDateTimeString(),
            ];
            $topicMeta[] = ['approved' => $approved, 'published' => $publishedAt];
        }

        $topicIds = $this->insertReturningIds('forum_topics', $topics);
        $replies = array_fill(0, count($topicIds), 0);
        $lastPost = array_fill(0, count($topicIds), null);
        $posts = [];

        for ($i = 0; $i < self::FORUM_POSTS; $i++) {
            // Squared roll: a few hot threads collect most replies, like a real forum.
            $index = (int) floor((mt_rand() / mt_getrandmax()) ** 2 * (count($topicIds) - 1));
            $meta = $topicMeta[$index];
            $approved = $meta['approved'] && mt_rand(1, 100) <= 92;
            $at = $meta['published']->copy()->addMinutes(mt_rand(1, 60 * 24 * 30));
            $stamp = $at->toDateTimeString();

            $posts[] = [
                'forum_topic_id' => $topicIds[$index],
                'user_id' => $this->pick($members),
                'body' => $this->pick(['И јас го имав истото.', 'Посетете лекар што побрзо.', 'Мене ми помогна физикална терапија.', 'Fala za sovetot!']),
                'status' => $approved ? 'approved' : 'pending',
                'published_at' => $approved ? $stamp : null,
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ];

            if ($approved) {
                $replies[$index]++;
                $lastPost[$index] = max($lastPost[$index] ?? '', $stamp);
            }
        }

        $this->insertChunked('forum_posts', $posts);

        foreach ($topicIds as $index => $topicId) {
            if ($replies[$index] > 0) {
                DB::table('forum_topics')->where('id', $topicId)->update([
                    'replies_count' => $replies[$index],
                    'last_post_at' => $lastPost[$index],
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    private function insertReturningIds(string $table, array $rows): array
    {
        $before = (int) DB::table($table)->max('id');
        $this->insertChunked($table, $rows);

        return DB::table($table)->where('id', '>', $before)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        $started = microtime(true);

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        $this->command?->line(sprintf('  %s: %d rows in %.1fs', $table, count($rows), microtime(true) - $started));
    }

    private function personName(): string
    {
        return $this->pick(self::FIRST_NAMES).' '.$this->pick(self::SURNAMES);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function city(): array
    {
        static $total = null;
        $total ??= array_sum(array_column(self::CITIES, 2));
        $roll = mt_rand(1, $total);

        foreach (self::CITIES as [$cyrillic, $latin, $weight]) {
            $roll -= $weight;

            if ($roll <= 0) {
                return [$cyrillic, $latin];
            }
        }

        return [self::CITIES[0][0], self::CITIES[0][1]];
    }

    private function toLatin(string $cyrillic): string
    {
        return Str::title(str_replace('-', ' ', Str::slug($cyrillic)));
    }

    private function slug(string $name): string
    {
        return Str::slug($name).'-'.$this->nextSlugSuffix++;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    /**
     * Distinct random picks without shuffling the whole list.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function pickMany(array $items, int $count): array
    {
        $count = min($count, count($items));
        $picked = [];

        while (count($picked) < $count) {
            $picked[mt_rand(0, count($items) - 1)] = true;
        }

        return array_map(fn (int $index) => $items[$index], array_keys($picked));
    }
}
