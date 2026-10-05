<?php

namespace Tests\Support;

use BackedEnum;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\MeilisearchEngine;
use Meilisearch\Client;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Scout's real MeilisearchEngine with only the HTTP calls replaced by an
 * in-memory index. Everything Scout does around the request — building the
 * `filter` string from ->where()/->whereIn(), mapping hits back to models,
 * totals — is the production code. The index is fed by the real model
 * observers, so shouldBeSearchable() and toSearchableArray() are exercised too.
 *
 * Every filtered field must be listed in the index's filterableAttributes in
 * config/scout.php, or the real server would reject the search.
 *
 * What it does NOT verify: Meilisearch's own ranking or typo tolerance.
 * Matching here is a case-insensitive substring test.
 */
class FakeMeilisearchEngine extends MeilisearchEngine
{
    /** @var array<string, array<int|string, array<string, mixed>>> */
    public array $indexes = [];

    /** @var list<array{index: string, query: string, params: array<string, mixed>}> */
    public array $searches = [];

    /** @var array<string, array<string, mixed>> index name => settings pushed by updateIndexSettings() */
    public array $indexSettings = [];

    public ?string $failIndexSettingsWith = null;

    public ?string $failSearchesWith = null;

    public function __construct()
    {
        parent::__construct(new Client('http://127.0.0.1:1'));
    }

    public static function install(): self
    {
        $engine = new self;

        config(['scout.driver' => 'meilisearch']);
        $manager = app(EngineManager::class);
        $manager->extend('meilisearch', fn () => $engine);
        $manager->forgetDrivers();
        Cache::put('meilisearch.health', true, 300);

        return $engine;
    }

    public function update($models)
    {
        foreach ($models as $model) {
            $document = $model->toSearchableArray();

            if ($document !== []) {
                $this->indexes[$model->indexableAs()][$model->getScoutKey()] = array_merge(
                    $document,
                    [$model->getScoutKeyName() => $model->getScoutKey()],
                );
            }
        }
    }

    public function delete($models)
    {
        foreach ($models as $model) {
            unset($this->indexes[$model->indexableAs()][$model->getScoutKey()]);
        }
    }

    public function flush($model)
    {
        $this->indexes[$model->indexableAs()] = [];
    }

    public function updateIndexSettings($name, array $settings = [])
    {
        if ($this->failIndexSettingsWith !== null) {
            throw new RuntimeException($this->failIndexSettingsWith);
        }

        $this->indexSettings[$name] = $settings;
    }

    /**
     * @param  array<string, mixed>  $searchParams
     * @return array<string, mixed>
     */
    protected function performSearch(Builder $builder, array $searchParams = [])
    {
        $index = $builder->index ?: $builder->model->searchableAs();
        $this->searches[] = ['index' => $index, 'query' => $builder->query, 'params' => $searchParams];

        if ($this->failSearchesWith !== null) {
            throw new RuntimeException($this->failSearchesWith);
        }

        $this->assertFiltersAreFilterable($builder);

        $needle = mb_strtolower($builder->query);
        $documents = $this->indexes[$index] ?? [];
        ksort($documents);

        $hits = array_values(array_filter(
            $documents,
            fn (array $document): bool => $this->matchesQuery($document, $needle)
                && $this->matchesFilters($document, $builder),
        ));

        $perPage = (int) ($searchParams['hitsPerPage'] ?? 20);
        $page = (int) ($searchParams['page'] ?? 1);

        return [
            'hits' => array_slice($hits, ($page - 1) * $perPage, $perPage),
            'totalHits' => count($hits),
            'page' => $page,
            'hitsPerPage' => $perPage,
        ];
    }

    public function lastSearch(string $index): array
    {
        $matches = array_values(array_filter(
            $this->searches,
            fn (array $search): bool => $search['index'] === $index,
        ));

        return end($matches) ?: [];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function matchesQuery(array $document, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        foreach ($document as $value) {
            foreach ((array) $value as $item) {
                if (is_string($item) && str_contains(mb_strtolower($item), $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The settings search:reindex pushes, keyed the way it resolves them: a
     * model class key goes to that model's index, a plain name gets the prefix.
     */
    private function assertFiltersAreFilterable(Builder $builder): void
    {
        $filterable = null;

        foreach ((array) config('scout.meilisearch.index-settings', []) as $name => $settings) {
            $matches = class_exists($name)
                ? $builder->model instanceof $name
                : config('scout.prefix').$name === $builder->model->searchableAs();

            if ($matches) {
                $filterable = $settings['filterableAttributes'] ?? [];
            }
        }

        $fields = array_merge(
            array_column($builder->wheres, 'field'),
            array_keys($builder->whereIns),
            array_keys($builder->whereNotIns),
        );

        foreach ($fields as $field) {
            Assert::assertContains(
                $field,
                $filterable ?? [],
                sprintf('[%s] is filtered on but not in its filterableAttributes (config/scout.php); Meilisearch would reject the search.', $field),
            );
        }
    }

    /**
     * Mirrors the subset of filter syntax Scout generates: `field = value`
     * from ->where() and `field IN [...]` from ->whereIn(). Inside a quoted
     * value Meilisearch's filter parser unescapes only an escaped quote (\");
     * any other backslash, \\ included, stays as it is.
     *
     * @param  array<string, mixed>  $document
     */
    private function matchesFilters(array $document, Builder $builder): bool
    {
        foreach ($builder->wheres as $where) {
            $value = $where['value'] instanceof BackedEnum ? $where['value']->value : $where['value'];

            if (($document[$where['field']] ?? null) != $value) {
                return false;
            }
        }

        foreach ($builder->whereIns as $field => $values) {
            $values = array_map(fn ($value) => is_string($value) ? str_replace('\\"', '"', $value) : $value, $values);

            if (! in_array($document[$field] ?? null, $values, false)) {
                return false;
            }
        }

        return true;
    }
}
