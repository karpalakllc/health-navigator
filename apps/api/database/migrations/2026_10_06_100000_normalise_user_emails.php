<?php

use App\Support\EmailAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses are now stored trimmed and lowercased (User::email(), and the auth
 * requests normalise before lookup). Rows written before that would no longer
 * match their owner's sign-in, so they are brought into line here.
 *
 * Normalisation happens in PHP, through the same EmailAddress::normalize() the
 * application uses, rather than with SQL LOWER(): SQLite's LOWER() folds ASCII
 * only while Postgres folds per collation, and either would drift from what the
 * application compares against.
 *
 * If two accounts normalise to the same address (Foo@x and foo@x), this refuses
 * to run rather than pick a winner. Merging accounts is a decision about whose
 * reviews, posts and roles survive, and it has to be made by a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        $changes = [];
        $owners = [];

        foreach (DB::table('users')->select(['id', 'email'])->orderBy('id')->cursor() as $row) {
            $normalised = EmailAddress::normalize((string) $row->email);
            $owners[$normalised][] = $row->id;

            if ($normalised !== $row->email) {
                $changes[$row->id] = $normalised;
            }
        }

        $collisions = array_filter($owners, fn (array $ids): bool => count($ids) > 1);

        if ($collisions !== []) {
            $detail = implode('; ', array_map(
                fn (string $email, array $ids): string => $email.' (user ids '.implode(', ', $ids).')',
                array_keys($collisions),
                $collisions,
            ));

            throw new RuntimeException(
                'Cannot normalise user emails: these accounts differ only by case or whitespace '
                .'and would collide. Merge or rename them by hand, then re-run the migration. '
                .$detail,
            );
        }

        DB::transaction(function () use ($changes): void {
            foreach ($changes as $id => $email) {
                DB::table('users')->where('id', $id)->update(['email' => $email]);
            }

            // Outstanding reset tokens are keyed by the old spelling and would never
            // match again. They expire within the hour anyway; the owner can ask for
            // a new link.
            if (Schema::hasTable('password_reset_tokens')) {
                foreach (DB::table('password_reset_tokens')->pluck('email') as $email) {
                    if (EmailAddress::normalize((string) $email) !== $email) {
                        DB::table('password_reset_tokens')->where('email', $email)->delete();
                    }
                }
            }
        });

        if ($changes !== []) {
            echo '  Normalised '.count($changes).' user email address(es).'.PHP_EOL;
        }
    }

    /**
     * Not reversible: the original spelling is not recorded, and nothing depends
     * on it.
     */
    public function down(): void
    {
        //
    }
};
