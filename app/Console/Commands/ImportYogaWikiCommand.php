<?php

namespace App\Console\Commands;

use App\Models\YogaMove;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportYogaWikiCommand extends Command
{
    protected $signature   = 'yoga:import-wiki {--force : Re-import even if yoga_moves already has rows}';
    protected $description = 'Import base yoga pose data from yoga-api into yoga_moves';

    /**
     * Map yoga-api category names → our category enum.
     * Gemini enrichment (Task 4) will correct/refine these.
     */
    private const CATEGORY_MAP = [
        'Core Yoga'          => 'seated',
        'Seated Yoga'        => 'seated',
        'Strengthening Yoga' => 'standing',
        'Chest Opening Yoga' => 'prone',
        'Backbend Yoga'      => 'prone',
        'Forward Bend Yoga'  => 'standing',
        'Hip Opening Yoga'   => 'seated',
        'Standing Yoga'      => 'standing',
        'Restorative Yoga'   => 'restorative',
        'Arm Balance Yoga'   => 'balancing',
        'Balancing Yoga'     => 'balancing',
        'Inversion Yoga'     => 'inversion',
    ];

    private const API_BASE = 'https://yoga-api-nzy4.onrender.com/v1';

    public function handle(): int
    {
        $existing = YogaMove::count();

        if ($existing > 0 && ! $this->option('force')) {
            $this->warn("yoga_moves already has {$existing} rows. Pass --force to re-import.");
            return self::SUCCESS;
        }

        $this->info('Fetching pose categories...');
        $categoryData = $this->fetchJson('/categories');
        if ($categoryData === null) {
            return self::FAILURE;
        }

        // Build map: pose_id → { category_enum, extra_categories[] }
        // First category win; extras stored as aliases context
        $poseCategories = [];
        foreach ($categoryData as $cat) {
            $catEnum = self::CATEGORY_MAP[$cat['category_name']] ?? null;
            foreach ($cat['poses'] ?? [] as $pose) {
                $id = $pose['id'];
                if (! isset($poseCategories[$id])) {
                    $poseCategories[$id] = $catEnum;
                }
            }
        }

        $this->info('Fetching full pose list...');
        $poses = $this->fetchJson('/poses');
        if ($poses === null) {
            return self::FAILURE;
        }

        $this->info('Importing ' . count($poses) . ' poses...');
        $bar = $this->output->createProgressBar(count($poses));
        $bar->start();

        $inserted = 0;
        $skipped  = 0;

        foreach ($poses as $pose) {
            $id   = $pose['id'];
            $name = trim($pose['english_name'] ?? '');

            if (! $name) {
                $skipped++;
                $bar->advance();
                continue;
            }

            YogaMove::updateOrCreate(
                ['name' => $name],
                [
                    'sanskrit_name'      => $pose['sanskrit_name_adapted'] ?? null,
                    'description'        => $pose['pose_description'] ?? null,
                    'category'           => $poseCategories[$id] ?? null,
                    'image_url'          => $pose['url_svg'] ?? $pose['url_png'] ?? null,
                    'source_url'         => self::API_BASE . '/poses',
                    'data_source'        => 'api',
                    'enrichment_status'  => 'pending',
                ]
            );

            $inserted++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $total = YogaMove::count();
        $this->info("Done. Imported: {$inserted}  Skipped: {$skipped}  Total in DB: {$total}");

        // Verify all are pending
        $pending = YogaMove::where('enrichment_status', 'pending')->count();
        $this->info("Enrichment status = pending: {$pending} / {$total}");

        return self::SUCCESS;
    }

    private function fetchJson(string $path): ?array
    {
        $response = Http::timeout(30)->get(self::API_BASE . $path);

        if ($response->failed()) {
            $this->error("Request failed for {$path}: HTTP {$response->status()}");
            return null;
        }

        return $response->json();
    }
}
