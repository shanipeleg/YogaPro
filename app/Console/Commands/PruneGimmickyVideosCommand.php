<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneGimmickyVideosCommand extends Command
{
    protected $signature = 'videos:prune-gimmicks
                            {--dry-run : Show candidates without deleting (default)}
                            {--confirm : Actually soft-delete the identified videos}';

    protected $description = 'Soft-delete gimmicky non-solo-yoga videos (kids, face massage, gear reviews, etc.)';

    /**
     * Patterns to identify gimmicky videos.
     * Each entry: [label, SQL LIKE/REGEXP pattern array]
     * All patterns are checked against the title column (utf8mb4_unicode_ci = case-insensitive).
     */
    private array $gimmickPatterns = [
        'Kids / parent yoga'       => ['%ילדים%', '%להורים%'],       // BOTH must match (AND)
        'Kids / parent yoga (alt)' => ['%לילדים%', '%הורים%'],
        'Face massage'             => ['%עיסוי לפנים%'],
        'Gear review (mat)'        => ['%לבחור מזרן%'],
        'Gear tip (mat)'           => ['%להפוך מזרן%'],
        'Craft tutorial (pillow)'  => ['%לתפור%'],
        'Course announcement'      => ['%קורס יוגה חצי שנתי%'],
    ];

    public function handle(): int
    {
        $dryRun = ! $this->option('confirm');

        if ($dryRun) {
            $this->warn('DRY RUN — use --confirm to soft-delete these videos.');
            $this->newLine();
        }

        $toDelete = $this->findCandidates();

        if ($toDelete->isEmpty()) {
            $this->info('No gimmicky videos found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$toDelete->count()} gimmicky video(s) to remove:");
        $this->newLine();

        foreach ($toDelete as $video) {
            $mins = round($video->duration_seconds / 60, 1);
            $this->line("  [{$video->id}] ({$mins} min, {$video->analysis_status}) {$video->title}");
        }

        $this->newLine();

        if ($dryRun) {
            $this->comment("Re-run with --confirm to soft-delete these {$toDelete->count()} videos.");
            return self::SUCCESS;
        }

        $ids = $toDelete->pluck('id');
        Video::whereIn('id', $ids)->delete(); // SoftDeletes

        $this->info("✓ Soft-deleted {$toDelete->count()} videos.");
        return self::SUCCESS;
    }

    private function findCandidates()
    {
        $candidateIds = collect();

        foreach ($this->gimmickPatterns as $label => $patterns) {
            // Build query: all patterns must match (AND within a group)
            $query = Video::whereNull('deleted_at');
            foreach ($patterns as $pattern) {
                $query->where('title', 'like', $pattern);
            }
            $matches = $query->get(['id', 'title', 'duration_seconds', 'analysis_status']);
            if ($matches->isNotEmpty()) {
                $this->line("  Pattern \"{$label}\": {$matches->count()} match(es)");
                $candidateIds = $candidateIds->merge($matches->pluck('id'));
            }
        }

        if ($candidateIds->isEmpty()) {
            return collect();
        }

        return Video::whereIn('id', $candidateIds->unique()->values())
            ->whereNull('deleted_at')
            ->get(['id', 'title', 'duration_seconds', 'analysis_status']);
    }
}
