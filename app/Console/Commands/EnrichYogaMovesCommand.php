<?php

namespace App\Console\Commands;

use App\Jobs\EnrichYogaMoveJob;
use App\Models\YogaMove;
use App\Services\GeminiService;
use Illuminate\Console\Command;

class EnrichYogaMovesCommand extends Command
{
    protected $signature = 'yoga:enrich
                            {--limit=0   : Max poses to process (0 = all pending)}
                            {--sync      : Run synchronously instead of queuing jobs}
                            {--sleep=1   : Seconds to sleep between calls in sync mode}';

    protected $description = 'Enrich pending yoga_moves rows with Gemini (body areas, benefits, contraindications)';

    public function handle(GeminiService $gemini): int
    {
        $limit = (int) $this->option('limit');
        $sync  = $this->option('sync');
        $sleep = (int) $this->option('sleep');

        $query = YogaMove::where('enrichment_status', 'pending')->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $moves = $query->get();

        if ($moves->isEmpty()) {
            $this->info('No pending yoga moves to enrich.');
            return self::SUCCESS;
        }

        $this->info("Found {$moves->count()} poses to enrich. Mode: " . ($sync ? 'sync' : 'queued'));

        if ($sync) {
            return $this->runSync($moves, $gemini, $sleep);
        }

        return $this->dispatchJobs($moves);
    }

    private function runSync($moves, GeminiService $gemini, int $sleep): int
    {
        $bar      = $this->output->createProgressBar($moves->count());
        $bar->start();

        $enriched = 0;
        $failed   = 0;

        foreach ($moves as $move) {
            try {
                $job = new EnrichYogaMoveJob($move->id);
                $job->handle($gemini);
                $enriched++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("  Failed: {$move->name} — {$e->getMessage()}");
            }

            $bar->advance();

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Done. Enriched: {$enriched}  Failed: {$failed}");

        $pending = YogaMove::where('enrichment_status', 'pending')->count();
        $this->info("Still pending: {$pending}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dispatchJobs($moves): int
    {
        $count = 0;

        foreach ($moves as $move) {
            EnrichYogaMoveJob::dispatch($move->id);
            $count++;
        }

        $this->info("Dispatched {$count} EnrichYogaMoveJob jobs to the queue.");
        $this->line("Run: php artisan queue:work");

        return self::SUCCESS;
    }
}
