<?php

namespace App\Console\Commands;

use App\Enums\BulkOperationStatus;
use App\Models\BulkOperation;
use App\Models\User;
use App\Support\Import\BulkPublish;
use App\Support\Import\BulkPublishAlreadyRunning;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use Illuminate\Console\Command;

/**
 * The review queue's bulk publishes from the terminal („Објави ги сите
 * верификувани“ / „Објави ги и неверификуваните од ФЗОМ“), chunk by chunk
 * through the same BulkPublish operation as the panel: progress shows on the
 * Import review page too, and running it again after a stop continues where
 * it stopped. Prints counts only — never names.
 */
class ImportPublishCommand extends Command
{
    protected $signature = 'import:publish
        {set : verified | fzom-unverified}
        {--by= : Email of the staff member publishing (recorded on every item, notified when done)}
        {--dry-run : Count what would be published, change nothing}';

    protected $description = 'Publish every verified (or every unverified ФЗОМ) imported draft in the review queue, in resumable chunks';

    public function handle(BulkPublish $bulk, VerifiedDraftPublisher $publisher): int
    {
        $set = (string) $this->argument('set');

        if (! in_array($set, [BulkPublish::TYPE_VERIFIED, BulkPublish::TYPE_FZOM_UNVERIFIED], true)) {
            $this->error('The set is „verified“ or „fzom-unverified“.');

            return self::INVALID;
        }

        $by = null;

        if (is_string($this->option('by')) && $this->option('by') !== '') {
            $by = User::query()->where('email', $this->option('by'))->first();

            if ($by === null || ! $by->can('imports.manage')) {
                $this->error('No staff member with that email may publish imports (imports.manage).');

                return self::FAILURE;
            }
        }

        $query = $set === BulkPublish::TYPE_VERIFIED ? $publisher->pending() : $publisher->pendingFzomUnverified();
        $unfinished = $bulk->unfinished();

        if ($this->option('dry-run')) {
            $this->line(sprintf('Would publish %d drafts (%s).', $query->count(), $set));

            if ($unfinished !== null) {
                $this->line(sprintf('Unfinished bulk publish #%d (%s): %d of %d processed — running this command continues it.', $unfinished->getKey(), $unfinished->type, $unfinished->processed, $unfinished->total));
            }

            return self::SUCCESS;
        }

        if ($unfinished !== null && $unfinished->type === $set) {
            $operation = $bulk->resume($unfinished, BulkOperation::DRIVER_CLI);
            $this->line(sprintf('Continuing bulk publish #%d: %d of %d already processed.', $operation->getKey(), $operation->processed, $operation->total));
        } else {
            try {
                $operation = $bulk->start($set, $by, (int) $query->max('id'), null, BulkOperation::DRIVER_CLI);
            } catch (BulkPublishAlreadyRunning $exception) {
                $this->error($exception->getMessage().' Wait for it, or cancel it on the Import review page.');

                return self::FAILURE;
            }

            $this->line(sprintf('Bulk publish #%d: %d drafts.', $operation->getKey(), $operation->total));
        }

        $bar = $this->output->createProgressBar(max(1, $operation->total));
        $bar->setProgress(min($operation->processed, max(1, $operation->total)));

        while ($operation->isRunning()) {
            $before = $operation->processed;
            $operation = $bulk->drive($operation, 60.0);
            $bar->setProgress(min($operation->processed, max(1, $operation->total)));

            if ($operation->isRunning() && $operation->processed === $before) {
                // Another driver (a worker, the review page) holds this step.
                sleep(2);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->line(BulkPublish::summary($operation));

        return $operation->status === BulkOperationStatus::Completed && $operation->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
