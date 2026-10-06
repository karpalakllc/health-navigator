<?php

namespace App\Console\Commands;

use App\Enums\ProfileCorrectionType;
use App\Filament\Resources\ProfileCorrections\ProfileCorrectionResource;
use App\Mail\NewProfileCorrectionsMail;
use App\Models\ProfileCorrection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Tells staff that profile corrections or objections are waiting, the way
 * reports:alert-staff does for content reports. Scheduled every 10 minutes:
 * each run sends one summary of the open requests nobody has been alerted
 * about yet and marks them, so a burst is one email and a quiet queue sends
 * nothing. Counts and dates only: no message, no contact, no profile name.
 */
class SendProfileCorrectionAlertCommand extends Command
{
    protected $signature = 'corrections:alert-staff';

    protected $description = 'Email staff a summary of profile corrections and objections that arrived since the last alert';

    public function handle(): int
    {
        $requests = ProfileCorrection::query()
            ->open()
            ->whereNull('staff_alerted_at')
            ->get(['id', 'type', 'due_at']);

        if ($requests->isEmpty()) {
            $this->info('No new correction requests.');

            return self::SUCCESS;
        }

        $types = collect(ProfileCorrectionType::cases())
            ->map(fn (ProfileCorrectionType $type): array => [
                'label' => $type->macedonianLabel(),
                'count' => $requests->where('type', $type)->count(),
                'days' => $type->dueDays(),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values()
            ->all();

        $mail = fn (): NewProfileCorrectionsMail => new NewProfileCorrectionsMail(
            newRequests: $requests->count(),
            openRequests: ProfileCorrection::query()->open()->count(),
            overdueRequests: ProfileCorrection::query()->overdue()->count(),
            types: $types,
            queueUrl: URL::to(ProfileCorrectionResource::getUrl('index')),
            // Profiles, not reports: distinct subjects over the threshold.
            priorityProfiles: DB::query()
                ->fromSub(
                    ProfileCorrection::query()
                        ->open()
                        ->where('type', ProfileCorrectionType::Report->value)
                        ->onPriorityProfiles()
                        ->select(['subject_type', 'subject_id'])
                        ->distinct(),
                    'priority_profiles',
                )
                ->count(),
        );

        $recipients = $this->recipients();

        foreach ($recipients as $address) {
            Mail::to($address)->queue($mail());
        }

        // By id: a request that arrives while this runs waits for the next run.
        ProfileCorrection::query()
            ->whereKey($requests->modelKeys())
            ->update(['staff_alerted_at' => now()]);

        $this->info("Alerted {$recipients->count()} recipient(s) about {$requests->count()} new request(s).");

        return self::SUCCESS;
    }

    /**
     * Everyone who can open the queue, plus the optional shared inbox.
     *
     * @return Collection<int, non-empty-string>
     */
    private function recipients(): Collection
    {
        // Only holders of the permission (directly or through a role) are
        // loaded — not every member — then checked like any gate.
        $staff = User::permission('profile_corrections.view')
            ->whereNull('anonymised_at')
            ->whereNull('suspended_at')
            ->get()
            ->filter(fn (User $user): bool => $user->can('profile_corrections.view'))
            ->map(fn (User $user): string => (string) $user->email);

        $shared = config('zdravje.corrections.alert_email');

        if (is_string($shared) && trim($shared) !== '') {
            $staff->push(trim($shared));
        }

        return $staff
            ->filter(fn (string $email): bool => $email !== '')
            ->unique(fn (string $email): string => mb_strtolower($email))
            ->values();
    }
}
