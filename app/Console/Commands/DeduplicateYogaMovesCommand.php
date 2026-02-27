<?php

namespace App\Console\Commands;

use App\Models\YogaMove;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateYogaMovesCommand extends Command
{
    protected $signature = 'yoga:deduplicate
                            {--dry-run : Show what would be done without making changes (default)}
                            {--confirm : Actually execute all changes}
                            {--junk : Phase 1 — remove non-yoga/junk entries}
                            {--pose-suffix : Phase 2 — merge "X" and "X Pose" duplicates}
                            {--bilateral : Phase 3 — merge "Left X" / "Right X" into base "X"}
                            {--all : Run all three phases}';

    protected $description = 'Deduplicate and clean the yoga_moves library (dry-run by default)';

    // ─── Junk patterns ────────────────────────────────────────────────────────

    private array $junkPatterns = [
        '/^(applying|apply)\s/i',
        '/face (cream|massage|strokes|lotion)/i',
        '/facial exercise/i',
        '/washing face/i',
        '/palming face/i',
        '/tapping massage on face/i',
        '/^(talking|speaking)\b/i',
        '/\(talking\)/i',
        '/\(speaking\)/i',
        '/talking,?\s+(standing|seated)/i',
        '/seated.*talking/i',
        '/sitting and talking/i',
        '/squatting.*talking/i',
        '/squatting.*(speaking|introduction)/i',
        '/^(intro|outro)\b/i',
        '/\bintro screen\b/i',
        '/\boutro screen\b/i',
        '/^(introduction)\b/i',
        '/seated introduction/i',
        '/kneeling position \(talking/i',
        '/kneeling position \(introduction/i',
        '/kneeling \(speaking\)/i',
        '/^black screen$/i',
        '/^title (card|screen)$/i',
        '/breathing exercise explanation/i',
        '/seated,\s*talking/i',
        '/final talking/i',
        '/call to action/i',
        '/outro and/i',
        '/seated \(goodbye\)/i',
        '/seated \(handstand (explanation|prep)/i',
        '/seated \(kiss wrists\)/i',
        '/^arm stroke$/i',
        '/playing chimes/i',
        '/progressive muscle relaxation/i',  // PMR — not yoga
        '/self-massage \((cheeks|jawline|mouth|temples|face)\)/i',
        '/\(observing body\)/i',
    ];

    private bool $dryRun = true;
    private int $totalDeleted = 0;
    private int $totalMerged = 0;
    private int $totalSegmentMoves = 0;

    public function handle(): int
    {
        $this->dryRun = ! $this->option('confirm');

        $runAll      = $this->option('all');
        $runJunk     = $runAll || $this->option('junk');
        $runSuffix   = $runAll || $this->option('pose-suffix');
        $runBilateral = $runAll || $this->option('bilateral');

        if (! $runJunk && ! $runSuffix && ! $runBilateral) {
            // Default: run all phases
            $runJunk = $runSuffix = $runBilateral = true;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN — no changes will be made. Use --confirm to execute.');
            $this->newLine();
        }

        if ($runJunk)      $this->phaseJunk();
        if ($runSuffix)    $this->phasePoseSuffix();
        if ($runBilateral) $this->phaseBilateral();

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $mode = $this->dryRun ? 'DRY RUN SUMMARY' : 'SUMMARY';
        $this->info($mode);
        $this->info("  Junk poses removed:         {$this->totalDeleted}");
        $this->info("  Duplicate pairs merged:     {$this->totalMerged}");
        $this->info("  segment_moves reassigned:   {$this->totalSegmentMoves}");
        $this->info('═══════════════════════════════════════');

        if ($this->dryRun && ($this->totalDeleted + $this->totalMerged) > 0) {
            $this->newLine();
            $this->comment('Re-run with --confirm to apply these changes.');
        }

        return self::SUCCESS;
    }

    // ─── Phase 1: Junk removal ────────────────────────────────────────────────

    private function phaseJunk(): void
    {
        $this->info('Phase 1: Junk / non-yoga pose removal');
        $this->line('─────────────────────────────────────────');

        $poses = YogaMove::all(['id', 'name']);
        $junk  = $poses->filter(fn ($p) => $this->isJunk($p->name));

        if ($junk->isEmpty()) {
            $this->line('  No junk poses found.');
            $this->newLine();
            return;
        }

        $junkIds = $junk->pluck('id');

        // Count segment_moves that will be deleted
        $smCount = DB::table('segment_moves')->whereIn('yoga_move_id', $junkIds)->count();

        foreach ($junk as $pose) {
            $uses = DB::table('segment_moves')->where('yoga_move_id', $pose->id)->count();
            $this->line("  [DELETE] {$pose->name} ({$uses} segment_moves)");
        }

        $this->line("  → {$junk->count()} poses, {$smCount} segment_moves");

        if (! $this->dryRun) {
            DB::table('session_move_flags')->whereIn('yoga_move_id', $junkIds)->delete();
            DB::table('user_move_opinions')->whereIn('yoga_move_id', $junkIds)->delete();
            DB::table('segment_moves')->whereIn('yoga_move_id', $junkIds)->delete();
            YogaMove::whereIn('id', $junkIds)->delete();
            $this->info("  ✓ Done.");
        }

        $this->totalDeleted     += $junk->count();
        $this->totalSegmentMoves += $smCount;
        $this->newLine();
    }

    private function isJunk(string $name): bool
    {
        foreach ($this->junkPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }
        return false;
    }

    // ─── Phase 2: "X" vs "X Pose" deduplication ──────────────────────────────

    private function phasePoseSuffix(): void
    {
        $this->info('Phase 2: "X" / "X Pose" duplicate merging');
        $this->line('─────────────────────────────────────────');

        $pairs = DB::select("
            SELECT
                a.id    AS a_id,  a.name AS a_name,
                b.id    AS b_id,  b.name AS b_name,
                (SELECT COUNT(*) FROM segment_moves WHERE yoga_move_id = a.id) AS a_uses,
                (SELECT COUNT(*) FROM segment_moves WHERE yoga_move_id = b.id) AS b_uses
            FROM yoga_moves a
            JOIN yoga_moves b
              ON (CONCAT(b.name, ' Pose') = a.name OR CONCAT(a.name, ' Pose') = b.name)
             AND a.id < b.id
        ");

        if (empty($pairs)) {
            $this->line('  No "X" / "X Pose" duplicates found.');
            $this->newLine();
            return;
        }

        foreach ($pairs as $pair) {
            // Keep whichever has more uses; if tied, keep the one with "Pose" suffix
            $totalUses = $pair->a_uses + $pair->b_uses;
            if ($pair->a_uses >= $pair->b_uses) {
                [$keepId, $keepName, $dropId, $dropName, $dropUses] =
                    [$pair->a_id, $pair->a_name, $pair->b_id, $pair->b_name, $pair->b_uses];
            } else {
                [$keepId, $keepName, $dropId, $dropName, $dropUses] =
                    [$pair->b_id, $pair->b_name, $pair->a_id, $pair->a_name, $pair->a_uses];
            }

            $this->line("  [MERGE] \"{$dropName}\" → \"{$keepName}\" ({$dropUses} refs moved, {$totalUses} total)");

            if (! $this->dryRun) {
                DB::table('segment_moves')
                    ->where('yoga_move_id', $dropId)
                    ->update(['yoga_move_id' => $keepId]);
                DB::table('session_move_flags')
                    ->where('yoga_move_id', $dropId)
                    ->update(['yoga_move_id' => $keepId]);
                DB::table('user_move_opinions')
                    ->where('yoga_move_id', $dropId)
                    ->delete(); // no opinions exist but safe to include
                YogaMove::where('id', $dropId)->delete();
            }

            $this->totalMerged++;
            $this->totalSegmentMoves += $dropUses;
        }

        if (! $this->dryRun) {
            $this->info("  ✓ Done.");
        }
        $this->newLine();
    }

    // ─── Phase 3: Bilateral deduplication ─────────────────────────────────────

    private function phaseBilateral(): void
    {
        $this->info('Phase 3: Bilateral "Left X" / "Right X" → "X" deduplication');
        $this->line('─────────────────────────────────────────');

        // Find all "Left X" poses that have a matching "Right X"
        $lefts = YogaMove::where('name', 'like', 'Left %')->get(['id', 'name']);

        $groups = collect(); // base_name → [left, right, base_if_exists]

        foreach ($lefts as $left) {
            $baseName  = preg_replace('/^Left\s+/i', '', $left->name);
            $rightName = 'Right ' . $baseName;

            $right = YogaMove::where('name', $rightName)->first(['id', 'name']);
            if (! $right) {
                continue; // no matching right side — skip
            }

            $groups[$baseName] = [
                'base_name'  => $baseName,
                'left'       => $left,
                'right'      => $right,
                'base'       => YogaMove::whereRaw('LOWER(name) = ?', [strtolower($baseName)])->first(),
            ];
        }

        if ($groups->isEmpty()) {
            $this->line('  No bilateral pairs found.');
            $this->newLine();
            return;
        }

        foreach ($groups as $baseName => $g) {
            $left  = $g['left'];
            $right = $g['right'];
            $base  = $g['base'];

            $leftUses  = DB::table('segment_moves')->where('yoga_move_id', $left->id)->count();
            $rightUses = DB::table('segment_moves')->where('yoga_move_id', $right->id)->count();
            $baseUses  = $base ? DB::table('segment_moves')->where('yoga_move_id', $base->id)->count() : 0;

            $canonicalName = $base ? $base->name : $baseName;
            $this->line("  [BILATERAL] \"{$baseName}\" ← Left({$leftUses}) + Right({$rightUses})" .
                ($base ? " + base({$baseUses})" : ' (will create base)'));

            if (! $this->dryRun) {
                // Get or create the canonical base pose
                if (! $base) {
                    // Clone the left pose as the base (drop "Left" from name)
                    $base = $left->replicate();
                    $base->name = $baseName;
                    $base->save();
                    $canonicalName = $baseName;
                }

                // Reassign "Left X" segment_moves → canonical, set side=left where n_a
                DB::table('segment_moves')
                    ->where('yoga_move_id', $left->id)
                    ->update([
                        'yoga_move_id' => $base->id,
                        'side' => DB::raw("IF(side = 'n_a', 'left', side)"),
                    ]);

                // Reassign "Right X" segment_moves → canonical, set side=right where n_a
                DB::table('segment_moves')
                    ->where('yoga_move_id', $right->id)
                    ->update([
                        'yoga_move_id' => $base->id,
                        'side' => DB::raw("IF(side = 'n_a', 'right', side)"),
                    ]);

                // Delete the left/right variants (not the base)
                YogaMove::whereIn('id', [$left->id, $right->id])->delete();
            }

            $this->totalMerged       += 2;
            $this->totalSegmentMoves += $leftUses + $rightUses;
        }

        if (! $this->dryRun) {
            $this->info("  ✓ Done.");
        }
        $this->newLine();
    }
}
