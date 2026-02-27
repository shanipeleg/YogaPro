<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeVideoJob;
use App\Models\Video;
use App\Services\GeminiService;
use App\Services\VideoAnalysisParser;
use Illuminate\Console\Command;

class AnalyzeVideosCommand extends Command
{
    protected $signature = 'videos:analyze
                            {--limit=   : Number of pending videos to process (default: ANALYSIS_BATCH_SIZE from .env)}
                            {--video-id= : Analyze a specific video ID (ignores limit/pending filter)}
                            {--sync     : Run synchronously (no queue)}
                            {--requeue  : Also dispatch jobs for "failed" videos (not just "pending")}';

    protected $description = 'Dispatch Gemini analysis jobs for pending videos';

    public function handle(GeminiService $gemini, VideoAnalysisParser $parser): int
    {
        // Single video mode
        if ($videoId = $this->option('video-id')) {
            return $this->analyzeSingle((int) $videoId, $gemini, $parser);
        }

        $limit    = (int) ($this->option('limit') ?: config('services.gemini.analysis_batch', 5));
        $statuses = ['pending'];

        if ($this->option('requeue')) {
            $statuses[] = 'failed';
        }

        $videos = Video::whereIn('analysis_status', $statuses)
            ->whereBetween('duration_seconds', [600, 3000])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($videos->isEmpty()) {
            $this->info('No pending videos to analyze.');
            return self::SUCCESS;
        }

        $this->info("Found {$videos->count()} video(s) to analyze. Mode: " . ($this->option('sync') ? 'sync' : 'queued'));

        if ($this->option('sync')) {
            return $this->runSync($videos, $gemini, $parser);
        }

        foreach ($videos as $video) {
            AnalyzeVideoJob::dispatch($video->id);
        }

        $this->info("Dispatched {$videos->count()} AnalyzeVideoJob(s) to the queue.");
        $this->line('Run: php artisan queue:work');

        return self::SUCCESS;
    }

    private function analyzeSingle(int $videoId, GeminiService $gemini, VideoAnalysisParser $parser): int
    {
        $video = Video::find($videoId);

        if (! $video) {
            $this->error("Video {$videoId} not found.");
            return self::FAILURE;
        }

        $this->info("Analyzing: [{$video->id}] {$video->title}");

        if ($this->option('sync')) {
            try {
                $job = new AnalyzeVideoJob($video->id);
                $job->handle($gemini, $parser);
                $this->info('Done.');
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error("Failed: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        AnalyzeVideoJob::dispatch($video->id);
        $this->info("Dispatched AnalyzeVideoJob for video {$video->id}.");
        return self::SUCCESS;
    }

    private function runSync($videos, GeminiService $gemini, VideoAnalysisParser $parser): int
    {
        $success = 0;
        $failed  = 0;

        foreach ($videos as $video) {
            $this->line("  [{$video->id}] {$video->title}");

            try {
                $job = new AnalyzeVideoJob($video->id);
                $job->handle($gemini, $parser);
                $success++;
                $this->info("    ✓ analyzed");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("    ✗ failed: {$e->getMessage()}");
            }
        }

        $this->info("Done. Success: {$success}  Failed: {$failed}");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
