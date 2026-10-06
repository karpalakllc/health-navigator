<?php

namespace App\Models;

use App\Enums\UsernameMatchType;
use App\Enums\UsernameTermKind;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameTermMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of the blocked or reserved username lists, curated by staff
 * (Filament „Username rules“, permission usernames.manage). The seed lists
 * live in database/data/username_terms.php.
 *
 * `term_normalized` is derived on save with the folding a username gets
 * (UsernameNormalizer::key), so a term written in either script, with or
 * without diacritics, matches every spelling of itself. `term_skeleton` is
 * the skeleton of that Latin reading (l = i), so „SIUT“ written with a capital
 * I still meets „slut“. It is deliberately not the skeleton of the term as
 * written: Cyrillic „сс“ (SS) looks like Latin „cc“, which is harmless.
 *
 * @property UsernameTermKind $kind
 * @property UsernameMatchType $match_type
 * @property string $term
 * @property string $term_normalized
 * @property string $term_skeleton
 * @property bool $active
 */
class UsernameTerm extends Model
{
    /** The languages a term can be filed under; `any` for names, brands and routes. */
    public const LANGUAGES = ['en', 'mk', 'sq', 'any'];

    /**
     * Seed categories. Staff may file new terms under any of these.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'profanity' => 'Profanity',
        'sexual' => 'Sexual',
        'slur_ethnic' => 'Slur (ethnic)',
        'slur_religious' => 'Slur (religious)',
        'slur_homophobic' => 'Slur (homophobic / transphobic)',
        'slur_ableist' => 'Slur (ableist)',
        'hate' => 'Hate / extremism',
        'drugs' => 'Drugs',
        'scam' => 'Scam / spam',
        'staff' => 'Staff and system roles',
        'brand' => 'Platform name',
        'medical' => 'Medical titles and roles',
        'authority' => 'Government and authorities',
        'route' => 'Site addresses',
        'false_positive' => 'Exception for real names and words',
    ];

    protected $fillable = [
        'term',
        'kind',
        'language',
        'match_type',
        'category',
        'note',
        'active',
    ];

    protected $attributes = [
        'active' => true,
        'language' => 'any',
    ];

    protected function casts(): array
    {
        return [
            'kind' => UsernameTermKind::class,
            'match_type' => UsernameMatchType::class,
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (UsernameTerm $term): void {
            $term->term = mb_strtolower(UsernameNormalizer::prepare((string) $term->term));
            $term->term_normalized = UsernameNormalizer::key($term->term);
            $term->term_skeleton = UsernameNormalizer::termSkeleton($term->term_normalized);
        });

        // The matcher caches the active lists; any change must be visible to
        // the next check.
        static::saved(fn () => UsernameTermMatcher::forget());
        static::deleted(fn () => UsernameTermMatcher::forget());
    }

    /**
     * @param  Builder<UsernameTerm>  $query
     * @return Builder<UsernameTerm>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
