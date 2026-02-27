<?php

namespace App\Console\Commands;

use App\Models\YogaMove;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsolidateCatCowCommand extends Command
{
    protected $signature = 'yoga:consolidate-cat-cow
                            {--dry-run : Show what would be done without making changes (default)}
                            {--confirm : Actually execute changes}';

    protected $description = 'Merge consecutive Cat + Cow segments into a single Cat-Cow Pose segment';

    public function handle(): int
    {
        $dryRun = ! $this->option('confirm');

        if ($dryRun) {
            $this->warn('DRY RUN — use --confirm to execute.');
            $this->newLine();
        }

        // Find the canonical Cat-Cow Pose
        $catCow = YogaMove::where('name', 'Cat-Cow Pose')->first();
        if (! $catCow) {
            $this->error('Could not find "Cat-Cow Pose" in yoga_moves. Aborting.');
            return self::FAILURE;
        }

        $this->info("Canonical pose: \"Cat-Cow Pose\" (id={$catCow->id})");

        // Find all consecutive Cat → Cow pairs
        $pairs = DB::select("
            SELECT
                vs1.id            AS cat_seg_id,
                vs1.video_id      AS video_id,
                vs1.order_index   AS cat_order,
                vs1.start_time_seconds AS cat_start,
                vs1.end_time_seconds   AS cat_end,
                vs2.id            AS cow_seg_id,
                vs2.order_index   AS cow_order,
                vs2.end_time_seconds   AS cow_end,
                sm1.id            AS cat_sm_id,
                ym1.id            AS cat_move_id,
                ym1.name          AS cat_name
            FROM video_segments vs1
            JOIN segment_moves sm1 ON sm1.video_segment_id = vs1.id AND sm1.role = 'main'
            JOIN yoga_moves ym1    ON ym1.id = sm1.yoga_move_id AND ym1.name = 'Cat'
            JOIN video_segments vs2 ON vs2.video_id = vs1.video_id
                AND vs2.order_index = vs1.order_index + 1
                AND vs2.segment_type = 'pose'
            JOIN segment_moves sm2 ON sm2.video_segment_id = vs2.id AND sm2.role = 'main'
            JOIN yoga_moves ym2    ON ym2.id = sm2.yoga_move_id AND ym2.name = 'Cow'
        ");

        $count = count($pairs);

        if ($count === 0) {
            $this->info('No consecutive Cat → Cow pairs found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} consecutive Cat → Cow pairs to merge.");
        $this->newLine();

        // Group by video for reporting
        $byVideo = collect($pairs)->groupBy('video_id');
        foreach ($byVideo as $videoId => $videoPairs) {
            $this->line("  Video {$videoId}: {$videoPairs->count()} pair(s)");
        }

        $this->newLine();

        if ($dryRun) {
            $this->comment("Re-run with --confirm to merge these pairs.");
            return self::SUCCESS;
        }

        $merged = 0;
        foreach ($pairs as $pair) {
            // 1. Extend the Cat segment to cover the Cow segment's end time
            // duration_seconds is a generated column — only update end_time_seconds
            DB::table('video_segments')
                ->where('id', $pair->cat_seg_id)
                ->update(['end_time_seconds' => $pair->cow_end]);

            // 2. Update the Cat segment's main segment_move to point to Cat-Cow Pose
            DB::table('segment_moves')
                ->where('id', $pair->cat_sm_id)
                ->update([
                    'yoga_move_id' => $catCow->id,
                    'side'         => 'both',
                ]);

            // 3. Delete the Cow segment's segment_moves, then the Cow segment itself
            $cowSegmentMoveIds = DB::table('segment_moves')
                ->where('video_segment_id', $pair->cow_seg_id)
                ->pluck('id');
            DB::table('session_move_flags')
                ->whereIn('yoga_move_id', DB::table('segment_moves')
                    ->where('video_segment_id', $pair->cow_seg_id)
                    ->pluck('yoga_move_id'))
                ->delete();
            DB::table('segment_moves')->where('video_segment_id', $pair->cow_seg_id)->delete();
            DB::table('video_segments')->where('id', $pair->cow_seg_id)->delete();

            $merged++;
        }

        $this->info("✓ Merged {$merged} Cat → Cow pairs into Cat-Cow Pose segments.");

        // Report remaining standalone Cat / Cow counts
        $standaloneCat = DB::table('segment_moves')
            ->join('yoga_moves', 'yoga_moves.id', '=', 'segment_moves.yoga_move_id')
            ->where('yoga_moves.name', 'Cat')
            ->count();
        $standaloneCow = DB::table('segment_moves')
            ->join('yoga_moves', 'yoga_moves.id', '=', 'segment_moves.yoga_move_id')
            ->where('yoga_moves.name', 'Cow')
            ->count();
        $this->line("  Remaining standalone 'Cat' refs: {$standaloneCat}");
        $this->line("  Remaining standalone 'Cow' refs: {$standaloneCow}");

        return self::SUCCESS;
    }
}
