<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;

class BackfillMediaDimensions extends Command
{
    protected $signature = 'media:backfill-dimensions';

    protected $description = 'Fill in width/height for image rows created before dimensions were captured';

    /**
     * One-shot data fill for the 2026_09_29_120000 migration.
     *
     * New uploads capture dimensions on save (see Media::booted), so this
     * only covers rows that predate that hook. Rows whose file is missing
     * from disk are left null and reported, not failed: a missing old file
     * must not stop the rest from being measured.
     */
    public function handle(): int
    {
        $filled = 0;
        $skipped = 0;

        Media::query()
            ->where('type', 'image')
            ->whereNull('width')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$filled, &$skipped): void {
                foreach ($rows as $media) {
                    $dimensions = Media::storedDimensions($media->disk, $media->file_path);

                    if ($dimensions === null) {
                        $skipped++;

                        continue;
                    }

                    [$media->width, $media->height] = $dimensions;
                    // Quiet: this is maintenance, not an editorial change worth
                    // an activity-log row and a page-cache flush per image.
                    $media->saveQuietly();
                    $filled++;
                }
            });

        $this->info("Filled dimensions for {$filled} media row(s), skipped {$skipped} (file missing or unreadable).");

        return Command::SUCCESS;
    }
}
