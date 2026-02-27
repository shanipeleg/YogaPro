<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;

class PruneShortVideosCommand extends Command
{
    protected $signature   = 'videos:prune-short {--threshold=300 : Minimum duration in seconds (default 300 = 5 min)}';
    protected $description = 'Soft-delete videos shorter than the threshold (default: 5 minutes)';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        $count = Video::where('duration_seconds', '<', $threshold)->count();

        if ($count === 0) {
            $this->info("No videos under {$threshold}s found.");
            return self::SUCCESS;
        }

        $this->info("Found {$count} videos under {$threshold}s:");

        Video::where('duration_seconds', '<', $threshold)
            ->get(['id', 'title', 'duration_seconds'])
            ->each(function ($v) {
                $this->line("  [{$v->id}] {$v->title} ({$v->duration_seconds}s)");
            });

        if (! $this->confirm("Soft-delete these {$count} videos?", true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        Video::where('duration_seconds', '<', $threshold)->delete();

        $this->info("Done. {$count} videos soft-deleted.");

        return self::SUCCESS;
    }
}
