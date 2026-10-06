<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * One run of an importer. A dry run goes through exactly the same code as an
 * apply and is rolled back at the end; its row (counts and diff summary) is
 * written outside that transaction, so it survives.
 *
 * @property ImportRunStatus $status
 * @property array<string, int>|null $counts
 * @property array<string, mixed>|null $source_meta
 */
class ImportRun extends Model
{
    protected $fillable = [
        'source',
        'dry_run',
        'status',
        'triggered_by_id',
        'started_at',
        'finished_at',
        'source_meta',
        'counts',
        'diff_path',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'status' => ImportRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'source_meta' => 'array',
            'counts' => 'array',
        ];
    }

    public static function start(string $source, bool $dryRun, ?User $by = null): self
    {
        return static::query()->create([
            'source' => $source,
            'dry_run' => $dryRun,
            'status' => ImportRunStatus::Running,
            'triggered_by_id' => $by?->getKey(),
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, int>  $counts
     */
    public function finish(array $counts, ImportRunStatus $status = ImportRunStatus::Succeeded): void
    {
        $this->forceFill([
            'status' => $status,
            'counts' => $counts,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * The message only: a stack trace could quote a source row.
     */
    private static function sqlState(QueryException $error): string
    {
        if (is_string($error->errorInfo[0] ?? null)) {
            return $error->errorInfo[0];
        }

        return preg_match('/SQLSTATE\[(\w+)\]/', $error->getMessage(), $match) === 1 ? $match[1] : (string) $error->getCode();
    }

    public function fail(Throwable|string $error): void
    {
        // A database error's message carries the SQL and its bound values
        // (names, licence numbers): only the class and SQLSTATE are kept.
        $message = match (true) {
            $error instanceof QueryException => $error::class.': SQLSTATE['.self::sqlState($error).'] (query and values not stored; see the server log)',
            $error instanceof Throwable => $error::class.': '.$error->getMessage(),
            default => $error,
        };

        $this->forceFill([
            'status' => ImportRunStatus::Failed,
            'error' => mb_substr($message, 0, 2000),
            'finished_at' => now(),
        ])->save();
    }

    public function count(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_id');
    }

    /**
     * @return HasMany<ImportReviewItem, $this>
     */
    public function reviewItems(): HasMany
    {
        return $this->hasMany(ImportReviewItem::class);
    }
}
