<?php

namespace App\Console\Commands;

use App\Enums\UserKind;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Deletes client sign-ups whose address was never verified.
 *
 * Only plain sign-ups: client accounts holding no role beyond Member (the
 * same test AuthController::notifyExistingAccount() uses for "pending").
 * Age counts from the later of the sign-up and the last contest
 * (registration_contested_at): a contest mails the owner a fresh link, and
 * pruning the account under it would break that link the owner may be about
 * to use. A pruned address can simply sign up again.
 */
class PruneUnverifiedAccountsCommand extends Command
{
    protected $signature = 'accounts:prune-unverified
                            {--days= : Override zdravje.accounts.unverified_prune_days}
                            {--dry-run : Report the count without deleting}';

    protected $description = 'Delete client sign-ups whose e-mail address was never verified';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('zdravje.accounts.unverified_prune_days', 7)));
        $cutoff = Carbon::now()->subDays($days);

        $query = User::query()
            ->where('user_kind', UserKind::Client->value)
            ->whereNull('email_verified_at')
            ->whereNull('anonymised_at')
            ->where('created_at', '<', $cutoff)
            ->where(fn ($q) => $q
                ->whereNull('registration_contested_at')
                ->orWhere('registration_contested_at', '<', $cutoff))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', '!=', RoleCatalog::MEMBER));

        if ($this->option('dry-run')) {
            $this->warn('Would delete '.(clone $query)->count()." never-verified account(s) older than {$days} days.");

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->chunkById(200, function ($users) use (&$deleted): void {
            foreach ($users as $user) {
                $user->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} never-verified account(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
