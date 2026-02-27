<?php

namespace App\Console\Commands;

use App\Models\YogaMove;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GeneratePoseImages extends Command
{
    protected $signature   = 'yoga:generate-images
                                {--limit=50 : Max poses to generate via API (default 50 = ~$1)}
                                {--skip-match : Skip Phase 1 exact name-matching}
                                {--skip-similar : Skip Phase 3 similarity-matching}
                                {--dry-run : Show what would be done without making changes}';

    protected $description = 'Generate pose images. Phase 1: exact name match. Phase 2: Gemini Imagen (top N by appearances). Phase 3: fuzzy similarity match for remainder.';

    private const IMAGE_PROMPT = 'Simple yoga pose illustration showing %s. Clean white background, single human figure as a solid sage green silhouette, side view, flat minimal illustration style, no text, no labels, centered, square format.';

    public function handle(): int
    {
        $limit  = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        // ── Phase 1: Exact name / Sanskrit matching ───────────────────────────
        if (! $this->option('skip-match')) {
            $this->info('Phase 1: Matching poses to existing images by name/sanskrit...');
            $matched = $this->runNameMatching($dryRun);
            $this->info("  → Matched {$matched} poses to existing SVGs.");
        }

        // ── Phase 2: Gemini Imagen for top N most-used poses ──────────────────
        $this->info("Phase 2: Generating images via Gemini for top {$limit} remaining poses...");

        $poses = YogaMove::query()
            ->where(fn($q) => $q->whereNull('image_url')->orWhere('image_url', ''))
            ->withCount('segmentMoves')
            ->orderByDesc('segment_moves_count')
            ->limit($limit)
            ->get();

        if ($poses->isEmpty()) {
            $this->info('  No poses need images — skipping Phase 2.');
        } else {
            $this->info("  → Processing {$poses->count()} poses...");

            $apiKey  = config('services.gemini.api_key');
            $success = 0;
            $failed  = 0;

            $bar = $this->output->createProgressBar($poses->count());
            $bar->start();

            foreach ($poses as $pose) {
                $bar->advance();
                $prompt = sprintf(self::IMAGE_PROMPT, $pose->name);

                if ($dryRun) {
                    $this->line("\n  [dry-run] Would generate: {$pose->name} ({$pose->segment_moves_count} appearances)");
                    continue;
                }

                try {
                    $imageData = $this->callGeminiImagen($apiKey, $prompt);

                    if ($imageData === null) {
                        $failed++;
                        continue;
                    }

                    $path = "poses/{$pose->id}.jpg";
                    Storage::disk('public')->put($path, $imageData);
                    $pose->update(['image_url' => '/storage/' . $path]);
                    $success++;

                    usleep(500_000); // 0.5s rate limit

                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->warn("  Failed for '{$pose->name}': " . $e->getMessage());
                    $failed++;
                    sleep(2);
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info("  Done. Generated: {$success}, Failed: {$failed}");
        }

        // ── Phase 3: Similarity matching for remaining poses ──────────────────
        if (! $this->option('skip-similar')) {
            $this->info('Phase 3: Similarity-matching remaining poses to existing images...');
            $matched = $this->runSimilarityMatching($dryRun);
            $this->info("  → Similarity-matched {$matched} poses.");
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $remaining = YogaMove::where(fn($q) => $q->whereNull('image_url')->orWhere('image_url', ''))->count();
        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Poses still without any image: {$remaining}");

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 1: exact name / sanskrit match
    // ─────────────────────────────────────────────────────────────────────────

    private function runNameMatching(bool $dryRun): int
    {
        $withImages = YogaMove::whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->get()
            ->map(fn($m) => [
                'name_lower'     => strtolower($m->name),
                'sanskrit_lower' => strtolower($m->sanskrit_name ?? ''),
                'image_url'      => $m->image_url,
            ]);

        $imageless = YogaMove::where(fn($q) => $q->whereNull('image_url')->orWhere('image_url', ''))->get();
        $matched   = 0;

        foreach ($imageless as $pose) {
            $nameLower     = strtolower($pose->name);
            $sanskritLower = strtolower($pose->sanskrit_name ?? '');
            $imageUrl      = null;

            foreach ($withImages as $ref) {
                // Exact match or "X Pose" → "X"
                if ($nameLower === $ref['name_lower'] || $nameLower === $ref['name_lower'] . ' pose') {
                    $imageUrl = $ref['image_url'];
                    break;
                }
                // Sanskrit exact match
                if ($sanskritLower && $ref['sanskrit_lower'] && $sanskritLower === $ref['sanskrit_lower']) {
                    $imageUrl = $ref['image_url'];
                    break;
                }
                // Sanskrit prefix match
                if ($sanskritLower && $ref['sanskrit_lower'] && strlen($ref['sanskrit_lower']) > 4) {
                    if (str_starts_with($sanskritLower, $ref['sanskrit_lower'])) {
                        $imageUrl = $ref['image_url'];
                        break;
                    }
                }
            }

            if ($imageUrl) {
                $matched++;
                if (! $dryRun) {
                    $pose->update(['image_url' => $imageUrl]);
                }
            }
        }

        return $matched;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 3: fuzzy similarity — strip qualifiers and match to base pose
    // ─────────────────────────────────────────────────────────────────────────

    private function runSimilarityMatching(bool $dryRun): int
    {
        // Build lookup: normalised name → image_url
        $withImages = YogaMove::whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->get()
            ->mapWithKeys(fn($m) => [strtolower($m->name) => $m->image_url]);

        $imageless = YogaMove::where(fn($q) => $q->whereNull('image_url')->orWhere('image_url', ''))->get();
        $matched   = 0;

        foreach ($imageless as $pose) {
            $base     = $this->extractBaseName($pose->name);
            $imageUrl = null;

            // Try increasingly stripped versions of the name
            foreach ($base as $candidate) {
                if (isset($withImages[$candidate])) {
                    $imageUrl = $withImages[$candidate];
                    break;
                }
                // Also try "X Pose" form
                if (isset($withImages[$candidate . ' pose'])) {
                    $imageUrl = $withImages[$candidate . ' pose'];
                    break;
                }
            }

            if ($imageUrl) {
                $matched++;
                if ($dryRun) {
                    $this->line("  [dry-run] '{$pose->name}' → similarity match");
                } else {
                    $pose->update(['image_url' => $imageUrl]);
                }
            }
        }

        return $matched;
    }

    /**
     * Return a list of progressively-stripped candidate names (lowercase).
     * E.g. "Side Plank Variation (Knee Down)" →
     *   ["side plank variation (knee down)", "side plank variation", "side plank"]
     */
    private function extractBaseName(string $name): array
    {
        $n         = strtolower($name);
        $candidates = [$n];

        // Strip everything after " (" or " ["
        $stripped = preg_replace('/\s*[\(\[].*/', '', $n);
        if ($stripped !== $n) {
            $candidates[] = trim($stripped);
        }

        // Strip everything after the last comma
        $beforeComma = preg_replace('/,.*$/', '', $n);
        if ($beforeComma !== $n) {
            $candidates[] = trim($beforeComma);
        }

        // Strip trailing qualifiers (variation, prep, setup, hold, flow, etc.)
        $suffixes = [
            ' variation', ' variations', ' variation (left)', ' variation (right)',
            ' prep', ' preparation', ' setup', ' flow', ' hold',
            ' (left)', ' (right)', ' (both sides)', ' (right side)', ' (left side)',
            ' with arm variation', ' with arms extended', ' with block',
            ' modified', ' modification',
        ];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($stripped, $suffix)) {
                $candidates[] = substr($stripped, 0, -strlen($suffix));
            }
        }

        // Strip "modified " prefix
        if (str_starts_with($n, 'modified ')) {
            $candidates[] = substr($n, 9);
        }

        // Strip "revolved " prefix → check plain version
        if (str_starts_with($n, 'revolved ')) {
            $candidates[] = substr($n, 9);
        }

        // Strip " (left/right)" pattern from the full name too
        $sideStripped = preg_replace('/\s*\((left|right|left side|right side)\)\s*$/i', '', $n);
        if ($sideStripped !== $n) {
            $candidates[] = trim($sideStripped);
        }

        // Deduplicate and return non-empty
        return array_values(array_unique(array_filter($candidates, fn($c) => strlen(trim($c)) > 2)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Call Gemini Imagen API
    // ─────────────────────────────────────────────────────────────────────────

    private function callGeminiImagen(string $apiKey, string $prompt): ?string
    {
        $response = Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-fast-generate-001:predict?key={$apiKey}",
            [
                'instances'  => [['prompt' => $prompt]],
                'parameters' => [
                    'sampleCount'   => 1,
                    'aspectRatio'   => '1:1',
                    'outputOptions' => ['mimeType' => 'image/jpeg'],
                ],
            ]
        );

        if ($response->failed()) {
            throw new \RuntimeException("Imagen API error ({$response->status()}): " . $response->body());
        }

        $b64 = $response->json('predictions.0.bytesBase64Encoded');

        return $b64 ? base64_decode($b64) : null;
    }
}
