<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\Facility;
use App\Support\Media\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearDirectoryAvatars extends Command
{
    protected $signature = 'directory:clear-avatars
                            {--dry-run : Show what would be cleared without writing}';

    protected $description = 'Remove doctor/facility/pharmacy avatar URLs so the site uses admin placeholders';

    public function handle(ImageOptimizer $optimizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(config('media.disk'));

        $cleared = 0;
        $filesDeleted = 0;

        foreach ([Doctor::query(), Facility::query()] as $query) {
            $query->whereNotNull('avatar_url')->each(function (Doctor|Facility $record) use ($dryRun, $disk, $optimizer, &$cleared, &$filesDeleted): void {
                $path = $record->avatar_url;

                if ($path === null || $path === '') {
                    return;
                }

                $this->line(sprintf('%s %s: %s', class_basename($record), $record->slug, $path));

                if ($dryRun) {
                    $cleared++;

                    return;
                }

                if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
                    if ($disk->exists($path)) {
                        $optimizer->delete($path);
                        $filesDeleted++;
                    }
                }

                $record->forceFill(['avatar_url' => null])->save();
                $cleared++;
            });
        }

        $this->info($dryRun
            ? "Would clear {$cleared} avatar(s)."
            : "Cleared {$cleared} avatar(s); deleted {$filesDeleted} stored file(s).");

        return self::SUCCESS;
    }
}
