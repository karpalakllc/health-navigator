<?php

namespace App\Support\Import\Facilities;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Support\Import\Website\InstitutionsJsonImporter;
use Illuminate\Support\Facades\DB;

/**
 * Finds the register facility a website-only facility duplicates (the
 * website import created a draft because the register spells the name
 * differently: abbreviations, a former or brand name, Latin script).
 * Deterministic: the same data always gives the same proposals.
 *
 * Signals, each compared with every register facility of the same town
 * (or of the town the website's name or address mentions), the website and
 * phone also across towns:
 *
 * - `name`: the distinctive name words agree (FacilityKeys) — `strong`
 *   when every distinctive word of the shorter name is in the longer one and
 *   they share at least half of the longer one's, or the names are the
 *   same glued („Saradent“ / „Сара Дент“), or the register name is only
 *   generic words („Клиничка болница“) all in the website's name;
 *   `partial` when half of the shorter name's words agree;
 * - `address`: the same street and house number (`street` without the number);
 * - `phone`: a phone number in common;
 * - `domain`: the same website host.
 *
 * Confidence:
 * - `high`: at least two independent signals among a strong name, the exact
 *   address, a phone and the website host — or the host or a phone in the
 *   same town — and no other register facility of that town as good;
 * - `medium`: one of those signals (with a partial name or the street at most);
 * - `low`: a partial name or the address alone.
 *
 * Nothing here writes: FacilityMerger merges, after staff (or the planned
 * high-confidence run) decided.
 */
final class FacilityDuplicateMatcher
{
    public const HIGH = 'high';

    public const MEDIUM = 'medium';

    public const LOW = 'low';

    /** Candidates kept per website facility. */
    private const KEEP = 3;

    /** @var array<int, array<string, mixed>> register facility id => prepared keys */
    private array $pool = [];

    /** @var array<string, list<int>> */
    private array $byTown = [];

    /** @var array<string, list<int>> */
    private array $byPhone = [];

    /** @var array<string, list<int>> */
    private array $byHost = [];

    /** @var array<string, int> "town|street|number" => register facilities there */
    private array $addressCount = [];

    private bool $loaded = false;

    /**
     * The register side: every clinical facility not created by the
     * website import, not deleted, not merged.
     */
    public function load(): self
    {
        $this->pool = $this->byTown = $this->byPhone = $this->byHost = $this->addressCount = [];

        Facility::query()
            ->where('type', '!=', FacilityType::Pharmacy->value)
            ->where(fn ($query) => $query->whereNull('import_source')->orWhere('import_source', '!=', InstitutionsJsonImporter::SOURCE))
            ->when(FacilityMerger::hasMergeColumn(), fn ($query) => $query->whereNull('merged_into_id'))
            ->orderBy('id')
            ->get(['id', 'name', 'city', 'address', 'phone', 'website', 'type', 'is_published'])
            ->each(function (Facility $facility): void {
                $this->addToPool($facility);
            });

        $this->loaded = true;

        return $this;
    }

    private function addToPool(Facility $facility): void
    {
        $id = (int) $facility->getKey();
        $town = FacilityKeys::town($facility->city);
        $townWords = FacilityKeys::townWords($facility->city);
        $address = FacilityKeys::address($facility->address, $townWords);
        $phones = FacilityKeys::phones([$facility->phone]);
        $host = FacilityKeys::host($facility->website);

        $this->pool[$id] = [
            'id' => $id,
            'name' => (string) $facility->name,
            'city' => $facility->city,
            'address_text' => $facility->address,
            'published' => (bool) $facility->is_published,
            'type' => $facility->type->value,
            'town' => $town,
            'townWords' => $townWords,
            'variants' => FacilityKeys::nameVariants((string) $facility->name, $townWords),
            'address' => $address,
            'phones' => $phones,
            'host' => $host,
        ];

        $this->byTown[$town][] = $id;

        foreach ($phones as $phone) {
            $this->byPhone[$phone][] = $id;
        }

        if ($host !== null) {
            $this->byHost[$host][] = $id;
        }

        if ($address !== null && $address['number'] !== null) {
            $key = $town.'|'.implode(' ', $address['street']).'|'.$address['number'];
            $this->addressCount[$key] = ($this->addressCount[$key] ?? 0) + 1;
        }
    }

    /**
     * Website-only facilities: created by the website import, never matched
     * to a register record, not deleted or merged.
     *
     * @return list<int>
     */
    public static function websiteOnlyIds(): array
    {
        return Facility::query()
            ->where('import_source', InstitutionsJsonImporter::SOURCE)
            ->where('type', '!=', FacilityType::Pharmacy->value)
            ->when(FacilityMerger::hasMergeColumn(), fn ($query) => $query->whereNull('merged_into_id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('source_records')
                ->whereColumn('source_records.subject_id', 'facilities.id')
                ->where('source_records.subject_type', FieldProvenance::SUBJECT_FACILITY)
                ->where('source_records.source', '!=', InstitutionsJsonImporter::SOURCE))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<array<string, mixed>> one proposal per website-only facility
     */
    public function matchAll(): array
    {
        $ids = self::websiteOnlyIds();
        $proposals = [];

        foreach (array_chunk($ids, 200) as $chunk) {
            foreach (Facility::query()->whereIn('id', $chunk)->orderBy('id')->get() as $facility) {
                $proposals[] = $this->match($facility);
            }
        }

        return $proposals;
    }

    /**
     * The proposal for one website facility: its best candidates, the
     * confidence of the first, and why.
     *
     * @return array{facility_id: int, name: string, city: string|null, address: string|null, published: bool,
     *     confidence: string|null, suggested_facility_id: int|null, reasons: list<string>,
     *     candidates: list<array<string, mixed>>}
     */
    public function match(Facility $facility): array
    {
        if (! $this->loaded) {
            $this->load();
        }

        $source = $this->websiteSide($facility);
        $ids = [];

        foreach (array_unique([$source['town'], ...$source['mentionedTowns']]) as $town) {
            foreach ($this->byTown[$town] ?? [] as $id) {
                $ids[$id] = true;
            }
        }

        foreach ($source['phones'] as $phone) {
            foreach ($this->byPhone[$phone] ?? [] as $id) {
                $ids[$id] = true;
            }
        }

        foreach ($source['hosts'] as $host) {
            foreach ($this->byHost[$host] ?? [] as $id) {
                $ids[$id] = true;
            }
        }

        unset($ids[(int) $facility->getKey()]);
        $candidates = [];

        foreach (array_keys($ids) as $id) {
            $candidate = $this->compare($source, $this->pool[$id]);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, fn (array $a, array $b): int => [$b['rank'], $b['score'], $a['facility_id']] <=> [$a['rank'], $a['score'], $b['facility_id']]);
        $reasons = [];

        if ($candidates !== [] && $candidates[0]['confidence'] === self::HIGH) {
            // Another register facility fits as well — also high, or with
            // the name too at the same address, phone or host (or the name
            // inside its own, „Дуо Дент“ in „Дуодент-Про“, unless the first
            // carries exactly the name): a person decides.
            $top = $candidates[0];

            foreach (array_slice($candidates, 1) as $rival) {
                $shared = array_intersect(array_diff($rival['signals'], ['name']), $top['signals']);

                $related = $rival['strong_name'] || ($rival['name_inside'] && ! $top['exact_name'])
                    // Part of the name at an address few facilities share
                    // („Мојсоска“ / „Мојсовска“ at one door), unless the first
                    // carries exactly the name: a person decides.
                    || ($rival['partial_name'] && in_array('address', $shared, true) && ! $top['exact_name']
                        && ! $top['crowded_address'] && ! $rival['crowded_address']);

                if ($rival['confidence'] === self::HIGH || ($related && $shared !== [])) {
                    $candidates[0]['confidence'] = self::MEDIUM;
                    $reasons[] = 'ambiguous: facility '.$rival['facility_id'].' fits as well';

                    break;
                }
            }
        }

        $candidates = array_slice($candidates, 0, self::KEEP);

        foreach ($candidates as &$candidate) {
            unset($candidate['rank'], $candidate['exact_name'], $candidate['strong_name'], $candidate['name_inside'], $candidate['partial_name'], $candidate['crowded_address']);
        }
        unset($candidate);

        return [
            'facility_id' => (int) $facility->getKey(),
            'name' => (string) $facility->name,
            'city' => $facility->city,
            'address' => $facility->address,
            'published' => (bool) $facility->is_published,
            'confidence' => $candidates[0]['confidence'] ?? null,
            'suggested_facility_id' => isset($candidates[0]) ? (int) $candidates[0]['facility_id'] : null,
            'reasons' => [...($candidates[0]['reasons'] ?? []), ...$reasons],
            'candidates' => $candidates,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteSide(Facility $facility): array
    {
        $townWords = FacilityKeys::townWords($facility->city);
        $phones = [$facility->phone];
        $websites = [$facility->website];

        $payloads = DB::table('source_records')
            ->where('source', InstitutionsJsonImporter::SOURCE)
            ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
            ->where('subject_id', $facility->getKey())
            ->pluck('payload');

        foreach ($payloads as $payload) {
            $data = is_string($payload) ? json_decode($payload, true) : (array) $payload;

            if (is_array($data)) {
                array_push($phones, ...array_map(fn ($phone): string => is_scalar($phone) ? (string) $phone : '', (array) ($data['phones'] ?? [])));
                $websites[] = is_string($data['website'] ?? null) ? $data['website'] : null;

                if (is_string($data['municipality'] ?? null)) {
                    $townWords = array_values(array_unique([...$townWords, ...FacilityKeys::townWords($data['municipality'])]));
                }
            }
        }

        // The towns the name or address mentions („Јасеново, Велес“,
        // „Катланово – Скопје“): register facilities there are compared too.
        $mentioned = [];
        $text = FacilityKeys::townWords($facility->name.' '.$facility->address);

        foreach (array_keys($this->byTown) as $town) {
            if ($town !== '' && ! str_contains($town, ' ') && in_array($town, $text, true)) {
                $mentioned[] = $town;
            }
        }

        $allTownWords = array_values(array_unique([...$townWords, ...$mentioned]));

        return [
            'id' => (int) $facility->getKey(),
            'town' => FacilityKeys::town($facility->city),
            'mentionedTowns' => $mentioned,
            'townWords' => $allTownWords,
            'variants' => FacilityKeys::nameVariants((string) $facility->name, $allTownWords),
            'address' => FacilityKeys::address($facility->address, $allTownWords),
            'phones' => FacilityKeys::phones($phones),
            'hosts' => array_values(array_unique(array_filter(array_map(fn (?string $url): ?string => FacilityKeys::host($url), $websites)))),
            'type' => $facility->type->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>|null
     */
    private function compare(array $source, array $target): ?array
    {
        $sameTown = $source['town'] !== '' && $source['town'] === $target['town'];
        $mentionedTown = ! $sameTown && in_array($target['town'], $source['mentionedTowns'], true);
        $townAgrees = $sameTown || $mentionedTown;
        $name = $this->compareNames($source['variants'], $target['variants'], [...$source['townWords'], ...$target['townWords']]);
        $address = FacilityKeys::compareAddresses($source['address'], $target['address']);
        $campus = $address === null ? self::sameCampus($source, $target) : null;
        $phone = array_values(array_intersect($source['phones'], $target['phones'])) !== [];
        $domain = $target['host'] !== null && in_array($target['host'], $source['hosts'], true);

        if ($name['level'] === null && $address === null && $campus === null && ! $phone && ! $domain) {
            return null;
        }

        $signals = [];
        $reasons = [];
        $rank = 0;
        $shared = 0;

        if ($name['level'] === 'strong') {
            $signals[] = 'name';
            $reasons[] = 'name: '.$name['why'];
            $rank += 15;
        } elseif ($name['level'] === 'partial') {
            $reasons[] = 'name partly: '.$name['why'];
            $rank += 6;
        }

        if ($address === 'exact') {
            $signals[] = 'address';
            $key = $target['town'].'|'.implode(' ', $target['address']['street'] ?? []).'|'.($target['address']['number'] ?? '');
            $shared = ($this->addressCount[$key] ?? 0) - 1;
            $reasons[] = 'same address'.($shared > 0 ? ' (shared with '.$shared.' other register facilities)' : '');
            $rank += $shared > 0 ? 6 : 10;
        } elseif ($campus !== null) {
            $signals[] = 'address';
            $reasons[] = 'same campus ('.$campus.')';
            $rank += 6;
        } elseif ($address === 'street') {
            $reasons[] = 'same street, number differs or missing';
            $rank += 4;
        }

        if ($phone) {
            $signals[] = 'phone';
            $reasons[] = 'same phone';
            $rank += 12;
        }

        if ($domain) {
            $signals[] = 'domain';
            $reasons[] = 'same website host';
            $rank += 12;
        }

        $reasons[] = $sameTown ? 'same town' : ($mentionedTown ? 'town named in the website name or address' : 'other town');

        if ($source['type'] !== null && $target['type'] !== null && $source['type'] !== $target['type']) {
            $reasons[] = 'type differs ('.$source['type'].' / '.$target['type'].')';
        }

        $count = count($signals);
        $contact = $phone || $domain;
        $strongName = $name['level'] === 'strong';
        $located = $address === 'exact' || $campus !== null;

        $confidence = match (true) {
            $townAgrees && ($count >= 2 || ($contact && $sameTown) || ($name['exact'] && $address === 'street')) => self::HIGH,
            ($strongName && $townAgrees) || $contact || ($located && $name['level'] === 'partial' && $townAgrees) || $count >= 2 => self::MEDIUM,
            ($name['level'] === 'partial' && $townAgrees) || $located || $strongName => self::LOW,
            default => null,
        };

        if ($confidence === null) {
            return null;
        }

        return [
            'facility_id' => $target['id'],
            'name' => $target['name'],
            'city' => $target['city'],
            'address' => $target['address_text'],
            'published' => $target['published'],
            'confidence' => $confidence,
            'score' => round($name['score'], 3),
            'signals' => $signals,
            'exact_name' => $name['exact'],
            'partial_name' => $name['level'] === 'partial',
            'crowded_address' => $campus !== null || $shared >= 2,
            'strong_name' => $strongName,
            'name_inside' => self::nameInside($source['variants'], $target['variants']),
            'reasons' => $reasons,
            'rank' => ['high' => 300, 'medium' => 200, 'low' => 100][$confidence] + $rank,
        ];
    }

    /**
     * Whether the distinctive words of one name, glued, are inside the
     * other's („ДУО“+„ДЕНТ“ is generic, so „ДУО“ in „ДУОДЕНТПРО“).
     *
     * @param  list<array{distinctive: list<string>, generic: list<string>, compact: string}>  $left
     * @param  list<array{distinctive: list<string>, generic: list<string>, compact: string}>  $right
     */
    private static function nameInside(array $left, array $right): bool
    {
        $glued = fn (array $variant): string => implode('', array_map(fn (string $word): string => rtrim($word, '.'), $variant['distinctive']));

        foreach ($left as $a) {
            foreach ($right as $b) {
                [$x, $y] = [$glued($a), $glued($b)];
                $short = mb_strlen($x) <= mb_strlen($y) ? $x : $y;
                $long = $short === $x ? $y : $x;

                if (mb_strlen($short) >= 5 && str_contains($long, $short)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Several addresses of one hospital campus (the clinics of the
     * University Clinical Centre „Мајка Тереза“ give either street). Only
     * places where the register and the websites are known to differ.
     *
     * @var list<array{town: string, label: string, addresses: list<array{0: string, 1: string}>}>
     */
    private const CAMPUSES = [
        ['town' => 'СКОПЈЕ', 'label' => 'Клинички центар „Мајка Тереза“: Водњанска 17 / Мајка Тереза 17', 'addresses' => [['ВОДЊАНСКА', '17'], ['МАЈКА ТЕРЕЗА', '17']]],
    ];

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     */
    private static function sameCampus(array $source, array $target): ?string
    {
        foreach (self::CAMPUSES as $campus) {
            if ($source['town'] !== $campus['town'] || $target['town'] !== $campus['town']) {
                continue;
            }

            $in = function (?array $address) use ($campus): bool {
                foreach ($campus['addresses'] as [$street, $number]) {
                    if ($address !== null && $address['number'] === $number
                        && FacilityKeys::compareAddresses($address, ['street' => explode(' ', $street), 'number' => $number]) === 'exact') {
                        return true;
                    }
                }

                return false;
            };

            if ($in($source['address']) && $in($target['address'])) {
                return $campus['label'];
            }
        }

        return null;
    }

    /**
     * The best agreement among the name variants of both sides.
     *
     * @param  list<array{distinctive: list<string>, generic: list<string>, compact: string}>  $left
     * @param  list<array{distinctive: list<string>, generic: list<string>, compact: string}>  $right
     * @param  list<string>  $townWords
     * @return array{level: 'strong'|'partial'|null, exact: bool, score: float, why: string}
     */
    private function compareNames(array $left, array $right, array $townWords): array
    {
        $best = ['level' => null, 'exact' => false, 'score' => 0.0, 'why' => ''];
        $order = ['strong' => 2, 'partial' => 1];

        foreach ($left as $a) {
            foreach ($right as $b) {
                $result = $this->compareVariant($a, $b, $townWords);
                $better = [($order[$result['level']] ?? 0), (int) $result['exact'], $result['score']] <=> [($order[$best['level']] ?? 0), (int) $best['exact'], $best['score']];

                if ($better > 0) {
                    $best = $result;
                }
            }
        }

        return $best;
    }

    /**
     * @param  array{distinctive: list<string>, generic: list<string>, compact: string}  $a
     * @param  array{distinctive: list<string>, generic: list<string>, compact: string}  $b
     * @param  list<string>  $townWords
     * @return array{level: 'strong'|'partial'|null, exact: bool, score: float, why: string}
     */
    private function compareVariant(array $a, array $b, array $townWords): array
    {
        $none = ['level' => null, 'exact' => false, 'score' => 0.0, 'why' => ''];
        $da = array_values(array_diff($a['distinctive'], $townWords));
        $db = array_values(array_diff($b['distinctive'], $townWords));

        // The same name glued or spaced („Saradent“ / „Сара Дент“).
        if (mb_strlen($a['compact']) >= 6 && $a['compact'] === $b['compact']) {
            return ['level' => 'strong', 'exact' => true, 'score' => 1.0, 'why' => 'same name'];
        }

        // One letter apart once glued („Гарден Ин Медикал“ / „Гарден Инн Медикал“).
        if (mb_strlen($a['compact']) >= 10 && FacilityKeys::nearlySame($a['compact'], $b['compact'])) {
            return ['level' => 'strong', 'exact' => false, 'score' => 0.95, 'why' => 'the same name but for a letter'];
        }

        if ($da === [] || $db === []) {
            // Only generic words on one side („Клиничка болница“ in its
            // town): all of them in the other name.
            $onlyGeneric = $da === [] ? $a : $b;
            $other = $da === [] ? $b : $a;
            $generic = $onlyGeneric['generic'];

            if (count($generic) >= 2 && FacilityKeys::matchedWords($generic, $other['generic'], strict: true) === count($generic)
                && ($da !== [] || $db !== [] || count($generic) === count($other['generic']))) {
                return ['level' => 'strong', 'exact' => false, 'score' => 0.9, 'why' => 'the generic name „'.implode(' ', $generic).'“ in the other name'];
            }

            return $none;
        }

        $matched = min(FacilityKeys::matchedWords($da, $db), FacilityKeys::matchedWords($db, $da));
        $strict = min(FacilityKeys::matchedWords($da, $db, strict: true), FacilityKeys::matchedWords($db, $da, strict: true));
        $shorter = min(count($da), count($db));
        $longer = max(count($da), count($db));
        $score = 0.7 * ($matched / $shorter) + 0.3 * ($matched / $longer);
        $why = sprintf('%d of %d distinctive words agree', $matched, $longer).($strict < $matched ? ' ('.($matched - $strict).' one letter apart)' : '');

        if ($strict === $longer && $longer >= 2) {
            return ['level' => 'strong', 'exact' => true, 'score' => 1.0, 'why' => 'same name'];
        }

        // Every word of the shorter name in the longer one, at least half of
        // the longer one, and one of them certain (not only a near miss).
        // A single word against two needs a long one (a surname, a brand):
        // a given name („Илија“) is in too many names.
        $longWord = $shorter > 1 || $longer === 1 || max(array_map('mb_strlen', $shorter === count($da) ? $da : $db)) >= 6;

        if ($matched === $shorter && $matched * 2 >= $longer && $strict >= 1 && $longWord) {
            return ['level' => 'strong', 'exact' => false, 'score' => $score, 'why' => $why];
        }

        // Long names the register abbreviates or shortens: most words agree.
        if ($matched >= 4 && $matched * 4 >= $shorter * 3 && $strict >= $matched - 1) {
            return ['level' => 'strong', 'exact' => false, 'score' => $score, 'why' => $why];
        }

        if ($matched * 2 >= $shorter && $matched >= 1) {
            return ['level' => 'partial', 'exact' => false, 'score' => $score, 'why' => $why];
        }

        return ['score' => $score] + $none;
    }
}
