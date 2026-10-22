<?php

namespace App\Support\Import\Pharmacies;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\PharmacyDutyShift;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\ImportContext;
use App\Support\Import\NameKey;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Support\Facades\DB;

/**
 * Links the pharmacies of ФЗОМ's on-duty schedule (pharmacy_duty_shifts) to
 * directory pharmacies. Used by import:on-duty-pharmacies for each month it
 * writes, and by import:pharmacies for every month held, after new
 * pharmacies arrive.
 *
 * In one town (the town part of „Скопје - Карпош“), the first rule that
 * finds exactly one pharmacy links it:
 *
 * 1. `alias` — staff linked this schedule name before (Import review „Link
 *    to pharmacy“): kept as a source record of the on-duty source;
 * 2. `name` — the same name key (without „ПЗУ“, „Аптека“, quotes and the
 *    town at the end) as the profile's name or the register's branch or
 *    legal name; several such pharmacies are told apart by the address;
 * 3. `name_without_town` — the same once every word of the town is dropped
 *    wherever it stands („Моја Аптека Велес 3“ and „Моја Аптека 3 Велес“),
 *    numbers kept;
 * 4. `name_variant` — the same once spacing, „Фарм“ and glued legal words
 *    are set aside, numbers kept („ВИАФАРМ 13“ = „Виа Фарм 13“, „ЗЕГИН“ =
 *    „Зегин Фарм“);
 * 5. `words_and_address` — every word of the schedule name is in the
 *    profile's name and the street address is the same.
 *
 * Otherwise nothing is linked. A single pharmacy whose name holds every
 * word of the schedule's (numbers equal), but without an address to
 * confirm it, is only suggested: a review item staff accept in bulk
 * („Link suggested pharmacy“). Several, or none, are queued as well — none
 * only once the town has any directory pharmacy (before that, it is in the
 * run report only).
 *
 * A linked pharmacy without a phone gets the schedule's phone number (as the
 * on-duty source, with provenance; a number someone else entered is never
 * overwritten).
 */
final class OnDutyLinker
{
    public const ALIAS_PREFIX = 'alias:';

    public const REASON_SUGGESTED = 'pharmacy_suggested_match';

    public const REASON_AMBIGUOUS = 'ambiguous_pharmacy';

    public const REASON_MISSING = 'pharmacy_not_in_directory';

    /** Numbers written out („Хедера Фарм Два“, „Жива-Фарм Четири“). */
    private const NUMBER_WORDS = ['ЕДЕН' => '1', 'ДВА' => '2', 'ТРИ' => '3', 'ЧЕТИРИ' => '4', 'ПЕТ' => '5', 'ШЕСТ' => '6', 'СЕДУМ' => '7', 'ОСУМ' => '8', 'ДЕВЕТ' => '9', 'ДЕСЕТ' => '10'];

    /** Words that do not tell pharmacies apart, beyond OnDutyNames' noise. */
    private const ADDRESS_NOISE = ['УЛ', 'УЛИЦА', 'БР', 'БРОЈ', 'БУЛ', 'БУЛЕВАР', 'НМ', 'С', 'Т', 'Ц', 'ТЦ', 'ЛОКАЛ', 'ЛАМЕЛА', 'ВЛЕЗ'];

    /** @var array<string, list<array{id: int, keys: array<string, true>, loose: array<string, true>, compact: array<string, true>, words: list<string>, address: string}>>|null by town key */
    private ?array $index = null;

    /** @var array<int, array<string, true>> facility id => schedule phone numbers seen by settle() */
    private array $phones = [];

    /** @var array<string, int>|null "town|name" => facility id */
    private ?array $aliases = null;

    /**
     * @return array{facility_id: int|null, rule: string|null, reason: string|null, candidates: list<int>, suggested: int|null}
     */
    public function decide(string $townKey, string $nameKey, ?string $address): array
    {
        $this->load();
        $result = fn (?int $id, ?string $rule, ?string $reason = null, array $candidates = [], ?int $suggested = null): array => [
            'facility_id' => $id, 'rule' => $rule, 'reason' => $reason, 'candidates' => array_values($candidates), 'suggested' => $suggested,
        ];

        if (isset($this->aliases[$townKey.'|'.$nameKey])) {
            return $result($this->aliases[$townKey.'|'.$nameKey], 'alias');
        }

        $pharmacies = $this->index[$townKey] ?? [];

        if ($pharmacies === []) {
            return $result(null, null, self::REASON_MISSING);
        }

        $addressKey = self::addressKey($address);
        $townWords = explode(' ', $townKey);

        $byName = array_values(array_filter($pharmacies, fn (array $p): bool => isset($p['keys'][$nameKey])));

        if (count($byName) === 1) {
            return $result($byName[0]['id'], 'name');
        }

        if (count($byName) > 1) {
            $atAddress = $addressKey !== '' ? array_values(array_filter($byName, fn (array $p): bool => $p['address'] === $addressKey)) : [];

            return count($atAddress) === 1
                ? $result($atAddress[0]['id'], 'name')
                : $result(null, null, self::REASON_AMBIGUOUS, array_column($byName, 'id'));
        }

        $loose = self::looseKey($nameKey, $townWords);
        $byLoose = $loose !== '' ? array_values(array_filter($pharmacies, fn (array $p): bool => isset($p['loose'][$loose]))) : [];

        if (count($byLoose) === 1) {
            return $result($byLoose[0]['id'], 'name_without_town');
        }

        if (count($byLoose) > 1) {
            return $result(null, null, self::REASON_AMBIGUOUS, array_column($byLoose, 'id'));
        }

        $compact = self::compactKey($nameKey, $townWords);
        $byCompact = $compact !== '' ? array_values(array_filter($pharmacies, fn (array $p): bool => isset($p['compact'][$compact]))) : [];

        if (count($byCompact) === 1) {
            return $result($byCompact[0]['id'], 'name_variant');
        }

        // „Магна Фарм 1“ is the register's „Магна Фарм“ when no „… 1“ exists.
        if ($byCompact === [] && str_ends_with($compact, '|1')) {
            $first = substr($compact, 0, -2);
            $byFirst = array_values(array_filter($pharmacies, fn (array $p): bool => isset($p['compact'][$first])));

            if (count($byFirst) === 1) {
                return $result($byFirst[0]['id'], 'name_variant');
            }
        }

        // Every word of the schedule's name (without the town) in the
        // profile's, the same numbers.
        $words = array_values(array_filter(explode(' ', $loose), fn (string $word): bool => $word !== ''));
        $numbers = self::numbers($words);
        $named = array_filter($words, fn (string $word): bool => preg_match('/^\d+$/', $word) !== 1) !== [];
        $containing = ! $named ? [] : array_values(array_filter(
            $pharmacies,
            fn (array $p): bool => array_diff($words, $p['words']) === [] && self::numbers($p['words']) === $numbers,
        ));

        if ($addressKey !== '') {
            $confirmed = array_values(array_filter($containing, fn (array $p): bool => $p['address'] === $addressKey));

            if (count($confirmed) === 1) {
                return $result($confirmed[0]['id'], 'words_and_address');
            }
        }

        return match (count($containing)) {
            0 => $result(null, null, self::REASON_MISSING),
            1 => $result(null, null, self::REASON_SUGGESTED, [$containing[0]['id']], $containing[0]['id']),
            default => $result(null, null, self::REASON_AMBIGUOUS, array_column($containing, 'id')),
        };
    }

    /** Whether the town has any directory pharmacy (drafts too). */
    public function townHasPharmacies(string $townKey): bool
    {
        $this->load();

        return ($this->index[$townKey] ?? []) !== [];
    }

    /**
     * Re-links every shift held (all months) and queues what stays
     * unlinked. $context is a context of the on-duty source.
     */
    public function relinkAll(ImportContext $context): void
    {
        $groups = PharmacyDutyShift::query()
            ->select(['town_key', 'name_key'])
            ->selectRaw('max(month) as last_month')
            ->groupBy('town_key', 'name_key')
            ->orderBy('town_key')->orderBy('name_key')
            ->get();

        foreach ($groups as $group) {
            $latest = PharmacyDutyShift::query()
                ->where('town_key', $group->town_key)->where('name_key', $group->name_key)->where('month', $group->getAttribute('last_month'))
                ->orderBy('duty_date')->first();

            if ($latest === null) {
                continue;
            }

            $decision = $this->decide((string) $group->town_key, (string) $group->name_key, $latest->address);
            $changed = PharmacyDutyShift::query()
                ->where('town_key', $group->town_key)->where('name_key', $group->name_key)
                ->where(fn ($query) => $decision['facility_id'] === null
                    ? $query->whereNotNull('facility_id')
                    : $query->whereNull('facility_id')->orWhere('facility_id', '!=', $decision['facility_id']))
                ->update(['facility_id' => $decision['facility_id'], 'updated_at' => now()]);

            $context->increment('pharmacies_seen');
            $context->increment('shifts_relinked', $changed);
            $this->settle($context, [
                'town' => (string) $latest->town,
                'town_key' => (string) $group->town_key,
                'name' => (string) $latest->pharmacy_name,
                'name_key' => (string) $group->name_key,
                'municipality' => $latest->municipality,
                'phone' => $latest->phone,
                'address' => $latest->address,
            ], $decision, null);
        }

        $this->fillPhones($context);
        $context->increment('shifts_linked_total', PharmacyDutyShift::query()->whereNotNull('facility_id')->count());
        $context->increment('shifts_unlinked_total', PharmacyDutyShift::query()->whereNull('facility_id')->count());
    }

    /**
     * Counts, queues and fills the phone for one schedule pharmacy.
     *
     * @param  array{town: string, town_key: string, name: string, name_key: string, municipality: string|null, phone: string|null, address: string|null}  $pharmacy
     * @param  array{facility_id: int|null, rule: string|null, reason: string|null, candidates: list<int>, suggested: int|null}  $decision
     */
    public function settle(ImportContext $context, array $pharmacy, array $decision, ?string $sourceUrl): void
    {
        $itemKey = self::itemKey($pharmacy['town_key'], $pharmacy['name_key']);

        if ($decision['facility_id'] !== null) {
            $context->increment('pharmacies_matched');
            $context->increment('pharmacies_matched_by_'.$decision['rule']);
            ImportReviewItem::autoResolve(OnDutyPharmacyImporter::SOURCE, ImportReviewKind::Unmatched, $itemKey, 'linked');

            if ($pharmacy['phone'] !== null && trim($pharmacy['phone']) !== '') {
                $this->phones[$decision['facility_id']][trim($pharmacy['phone'])] = true;
            }

            return;
        }

        if ($decision['reason'] === self::REASON_MISSING && ! $this->townHasPharmacies($pharmacy['town_key'])) {
            $context->increment('pharmacies_unmatched');
            $context->increment('pharmacies_unmatched_not_queued');
            $context->record('pharmacy', 'unmatched', null, $pharmacy['name'].', '.$pharmacy['town'], note: 'Not in the directory; no pharmacy in this town yet, so not queued for review.');

            return;
        }

        $context->increment(match ($decision['reason']) {
            self::REASON_AMBIGUOUS => 'pharmacies_ambiguous',
            self::REASON_SUGGESTED => 'pharmacies_suggested',
            default => 'pharmacies_unmatched',
        });

        $suggested = $decision['suggested'] !== null ? Facility::query()->find($decision['suggested'], ['id', 'name', 'city', 'address']) : null;
        $title = match ($decision['reason']) {
            self::REASON_AMBIGUOUS => 'Several pharmacies match the on-duty pharmacy '.$pharmacy['name'].', '.$pharmacy['town'],
            self::REASON_SUGGESTED => 'On-duty pharmacy '.$pharmacy['name'].', '.$pharmacy['town'].' is probably '.($suggested->name ?? '?'),
            default => 'On-duty pharmacy not in the directory: '.$pharmacy['name'].', '.$pharmacy['town'],
        };

        $context->review(ImportReviewKind::Unmatched, $itemKey, $title, array_filter([
            'reason' => $decision['reason'],
            'name' => $pharmacy['name'],
            'town' => $pharmacy['town'],
            'town_key' => $pharmacy['town_key'],
            'name_key' => $pharmacy['name_key'],
            'municipality' => $pharmacy['municipality'],
            'phone' => $pharmacy['phone'],
            'address' => $pharmacy['address'],
            'candidate_facility_ids' => $decision['candidates'],
            'suggested_facility_id' => $decision['suggested'],
            'suggested_name' => $suggested !== null ? $suggested->name.($suggested->address !== null ? ', '.$suggested->address : '') : null,
            'source_url' => $sourceUrl,
            'hint' => match ($decision['reason']) {
                self::REASON_SUGGESTED => 'Same name words, no address to confirm. “Link suggested pharmacy” links it (bulk action available); “Dismiss” if it is a different pharmacy.',
                self::REASON_AMBIGUOUS => 'Pick the right one with “Link to pharmacy”; the link is remembered for every month.',
                default => 'Not in ФЗОМ\'s pharmacy list under this name. “Link to pharmacy” if it is listed under another name; otherwise dismiss.',
            },
        ], fn ($value): bool => $value !== null && $value !== []));
    }

    /**
     * Staff link a schedule pharmacy to a directory pharmacy: remembered
     * (alias), every month's shifts linked, the item resolved.
     */
    public function linkByStaff(ImportReviewItem $item, int $facilityId, User $by): bool
    {
        $details = $item->details ?? [];
        $townKey = (string) ($details['town_key'] ?? '');
        $nameKey = (string) ($details['name_key'] ?? '');

        if (($townKey === '' || $nameKey === '') && preg_match('/^on-duty-pharmacy:([^:]*):(.*)$/u', $item->item_key, $m) === 1) {
            [$townKey, $nameKey] = [$m[1], $m[2]];
        }

        $facility = Facility::query()->pharmacy()->find($facilityId);

        if ($facility === null || $townKey === '' || $nameKey === '') {
            return false;
        }

        DB::transaction(function () use ($item, $facility, $townKey, $nameKey, $details, $by): void {
            $record = SourceRecord::query()->firstOrNew(['source' => OnDutyPharmacyImporter::SOURCE, 'external_key' => self::aliasKey($townKey, $nameKey)]);
            $payload = ['town_key' => $townKey, 'name_key' => $nameKey, 'name' => $details['name'] ?? null, 'linked_by_id' => $by->getKey()];
            $record->forceFill([
                'subject_type' => FieldProvenance::SUBJECT_FACILITY,
                'subject_id' => $facility->getKey(),
                'payload' => $payload,
                'hash' => hash('sha256', (string) json_encode($payload)),
                'first_seen_at' => $record->first_seen_at ?? now(),
                'last_seen_at' => now(),
            ])->save();

            PharmacyDutyShift::query()->where('town_key', $townKey)->where('name_key', $nameKey)
                ->update(['facility_id' => $facility->getKey(), 'updated_at' => now()]);

            $item->resolve(ImportReviewStatus::Resolved, 'linked', $by);
        });

        $this->aliases = null;

        return true;
    }

    /**
     * Pharmacies in the item's town, for the „Link to pharmacy“ picker.
     *
     * @return array<int, string> id => "name, address"
     */
    public function optionsFor(ImportReviewItem $item): array
    {
        $townKey = (string) ($item->details['town_key'] ?? OnDutyNames::townKey((string) ($item->details['town'] ?? '')));
        $this->load();
        $ids = array_column($this->index[$townKey] ?? [], 'id');

        return Facility::query()->whereKey($ids)->orderBy('name')->get(['id', 'name', 'address', 'is_published'])
            ->mapWithKeys(fn (Facility $f): array => [(int) $f->getKey() => $f->name.($f->address !== null ? ', '.$f->address : '').($f->is_published ? '' : ' (draft)')])
            ->all();
    }

    public static function itemKey(string $townKey, string $nameKey): string
    {
        return 'on-duty-pharmacy:'.$townKey.':'.$nameKey;
    }

    public static function aliasKey(string $townKey, string $nameKey): string
    {
        return mb_substr(self::ALIAS_PREFIX.$townKey.'|'.$nameKey, 0, 191);
    }

    /**
     * Street address as a key: words and numbers sorted, „ул.“, „бр.“ and
     * punctuation dropped („УЛ.МАРШАЛ ТИТО БР.16“ = „Маршал Тито 16“).
     */
    public static function addressKey(?string $address): string
    {
        if ($address === null || trim($address) === '') {
            return '';
        }

        $words = array_filter(
            explode(' ', NameKey::for((string) preg_replace('/[.,\/\\\\]+/u', ' ', $address))),
            fn (string $word): bool => $word !== '' && ! in_array($word, self::ADDRESS_NOISE, true),
        );
        sort($words, SORT_STRING);

        return implode(' ', $words);
    }

    /**
     * @param  list<string>  $townWords
     */
    public static function looseKey(string $nameKey, array $townWords): string
    {
        return implode(' ', array_values(array_filter(
            explode(' ', $nameKey),
            fn (string $word): bool => $word !== '' && ! in_array($word, $townWords, true),
        )));
    }

    /**
     * The name with spacing, „Фарм“ and glued legal words set aside, the
     * numbers apart: „ВИАФАРМ 13“ = „Виа Фарм 13“, „Зегин Фарм1“ = „Зегин
     * Фарм 1“, „ЗЕГИН“ = „Зегин Фарм“, „ПЗУ”АПТЕКА ЕУРОФАРМ“ = „Еурофарм“.
     * Only ever used when exactly one pharmacy of the town has it.
     *
     * @param  list<string>  $townWords
     */
    public static function compactKey(string $nameKey, array $townWords): string
    {
        $letters = '';
        $numbers = [];

        foreach (explode(' ', $nameKey) as $word) {
            if ($word === '' || in_array($word, $townWords, true)) {
                continue;
            }

            $word = self::NUMBER_WORDS[$word] ?? $word;

            preg_match_all('/\d+/u', $word, $digits);
            $numbers = [...$numbers, ...array_map(fn (string $n): string => ltrim($n, '0') ?: '0', $digits[0])];
            $letters .= (string) preg_replace('/\d+/u', '', $word);
        }

        foreach ($townWords as $town) {
            if (mb_strlen($town) > 3 && mb_strlen($letters) > mb_strlen($town)) {
                $letters = str_replace($town, '', $letters);
            }
        }

        $letters = (string) preg_replace('/^(?:ПЗУ|ЈЗУ|АПТЕКА)+/u', '', $letters);
        $letters = (string) preg_replace('/ФАРМ(?:АЦИЈА|АЦИ|А)?|ПОДРУЖНИЦА|ПОДР/u', '', $letters);

        // „Б3“ (letter + number) is a name; a short word alone is not.
        if ($letters === '' || (mb_strlen($letters) < 3 && $numbers === [])) {
            return '';
        }

        sort($numbers);

        return $letters.($numbers !== [] ? '|'.implode(',', $numbers) : '');
    }

    /**
     * The schedule's phone on each pharmacy settle() linked. Two schedule
     * names on one pharmacy with different numbers: only an empty phone is
     * filled (with the lowest number), so runs do not flip between them.
     */
    public function fillPhones(ImportContext $context): void
    {
        $phones = $this->phones;
        $this->phones = [];

        if ($phones === []) {
            return;
        }

        $provenance = new ProvenanceWriter($context);

        // Phones the schedule itself wrote earlier: the only non-empty ones
        // it may update. Any other number was entered by someone else and is
        // left alone — no conflict item either (formats differ too much).
        $own = FieldProvenance::query()
            ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)->where('field', 'phone')
            ->where('source', $context->source)->whereIn('subject_id', array_keys($phones))
            ->pluck('subject_id')->map(fn ($id): int => (int) $id)->flip()->all();

        foreach (Facility::query()->whereKey(array_keys($phones))->get() as $facility) {
            $numbers = array_keys($phones[(int) $facility->getKey()]);
            sort($numbers, SORT_STRING);

            if (trim((string) $facility->phone) !== '' && ! isset($own[(int) $facility->getKey()])) {
                $context->increment('phones_kept');

                continue;
            }

            if (count($numbers) > 1 && $facility->phone !== null) {
                $context->increment('phones_skipped_several');

                continue;
            }

            $label = $facility->name.($facility->city !== null ? ', '.$facility->city : '');

            if ($provenance->scalar($facility, 'phone', mb_substr($numbers[0], 0, 255), false, null, $label) === 'written') {
                $facility->save();
                $context->increment('phones_filled');
            }
        }

        $provenance->flushChanges();
    }

    /**
     * @param  list<string>  $words
     * @return list<string>
     */
    private static function numbers(array $words): array
    {
        $numbers = array_values(array_filter($words, fn (string $word): bool => preg_match('/^\d+$/', $word) === 1));
        sort($numbers);

        return $numbers;
    }

    private function load(): void
    {
        if ($this->index !== null && $this->aliases !== null) {
            return;
        }

        $this->index = [];
        $this->aliases = [];

        $records = SourceRecord::query()->where('source', PharmacyRegisterImporter::SOURCE)->whereNotNull('subject_id')
            ->get(['subject_id', 'payload'])->groupBy('subject_id');

        Facility::query()->pharmacy()->whereNotNull('city')->get(['id', 'name', 'city', 'address'])
            ->each(function (Facility $pharmacy) use ($records): void {
                $town = (string) $pharmacy->city;
                $townKey = OnDutyNames::townKey($town);
                $townWords = array_values(array_unique([...explode(' ', $townKey), ...explode(' ', NameKey::for($town))]));
                $names = [(string) $pharmacy->name];

                foreach ($records->get($pharmacy->getKey(), collect()) as $record) {
                    $payload = (array) $record->payload;
                    // The branch, never the legal entity's name: a chain's
                    // branches share it.
                    $names[] = (string) ($payload['branch'] ?? '');
                    $names[] = PharmacyName::clean((string) ($payload['branch'] ?? ''), (string) ($payload['legal_name'] ?? ''), $town)->value;
                }

                $keys = [];
                $loose = [];
                $compact = [];

                foreach (array_filter($names, fn (string $name): bool => trim($name) !== '') as $name) {
                    $key = OnDutyNames::pharmacyKey($name, $town);

                    if ($key !== '') {
                        $keys[$key] = true;
                        $looseKey = self::looseKey($key, $townWords);

                        if ($looseKey !== '') {
                            $loose[$looseKey] = true;
                        }

                        $compactKey = self::compactKey($key, $townWords);

                        if ($compactKey !== '') {
                            $compact[$compactKey] = true;
                        }
                    }
                }

                $this->index[$townKey][] = [
                    'id' => (int) $pharmacy->getKey(),
                    'keys' => $keys,
                    'loose' => $loose,
                    'compact' => $compact,
                    'words' => array_values(array_unique(explode(' ', implode(' ', array_keys($loose))))),
                    'address' => self::addressKey($pharmacy->address),
                ];
            });

        $known = array_flip(array_merge([], ...array_map(fn (array $town): array => array_column($town, 'id'), array_values($this->index))));

        SourceRecord::query()->where('source', OnDutyPharmacyImporter::SOURCE)->where('external_key', 'like', self::ALIAS_PREFIX.'%')
            ->whereNotNull('subject_id')->get(['external_key', 'subject_id'])
            ->each(function (SourceRecord $record) use ($known): void {
                // A link to a pharmacy deleted since is forgotten.
                if (! isset($known[(int) $record->subject_id])) {
                    return;
                }

                $this->aliases[substr($record->external_key, strlen(self::ALIAS_PREFIX))] = (int) $record->subject_id;
            });
    }
}
