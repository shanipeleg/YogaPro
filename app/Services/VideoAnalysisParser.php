<?php

namespace App\Services;

use App\Models\SegmentMove;
use App\Models\Video;
use App\Models\VideoSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VideoAnalysisParser
{
    public function __construct(private readonly YogaMoveResolver $resolver)
    {
    }

    /**
     * Parse a Gemini segments array and write video_segments + segment_moves to DB.
     * Runs inside a transaction — either all inserts succeed or none do.
     *
     * @param  array  $geminiData  Decoded JSON from Gemini (expects ['segments' => [...]])
     * @throws \Throwable
     */
    public function parse(Video $video, array $geminiData): void
    {
        $segments = $geminiData['segments'] ?? [];

        if (empty($segments)) {
            Log::warning("VideoAnalysisParser: no segments in Gemini response for video {$video->id}");
            return;
        }

        DB::transaction(function () use ($video, $segments) {
            // Clear any previous partial analysis for this video
            $video->segments()->delete();

            foreach ($segments as $seg) {
                $type  = $seg['type'] ?? 'pose';
                $order = (int) ($seg['order'] ?? 0);
                $start = (int) ($seg['start_time_seconds'] ?? 0);
                $end   = (int) ($seg['end_time_seconds'] ?? $start);

                if ($end <= $start) {
                    $end = $start + 1; // Prevent zero/negative durations
                }

                $videoSegment = VideoSegment::create([
                    'video_id'           => $video->id,
                    'order_index'        => $order,
                    'segment_type'       => $type === 'transition' ? 'transition' : 'pose',
                    'start_time_seconds' => $start,
                    'end_time_seconds'   => $end,
                    // duration_seconds is stored computed column, no need to set
                ]);

                if ($type === 'pose') {
                    $this->insertPoseMove($videoSegment, $seg);
                } else {
                    $this->insertTransitionMoves($videoSegment, $seg);
                }
            }
        });
    }

    private function insertPoseMove(VideoSegment $segment, array $seg): void
    {
        $name     = $seg['name'] ?? null;
        $sanskrit = $seg['sanskrit_name'] ?? null;

        if (! $name) {
            Log::warning("VideoAnalysisParser: pose segment {$segment->id} has no name, skipping move link.");
            return;
        }

        $move = $this->resolver->resolve($name, $sanskrit);

        SegmentMove::create([
            'video_segment_id' => $segment->id,
            'yoga_move_id'     => $move->id,
            'role'             => 'main',
            'side'             => $this->normaliseSide($seg['side'] ?? null),
            'hold_count'       => isset($seg['hold_count']) ? (int) $seg['hold_count'] : null,
            'ai_confidence'    => isset($seg['confidence']) ? round((float) $seg['confidence'], 2) : null,
            'created_at'       => now(),
        ]);
    }

    private function insertTransitionMoves(VideoSegment $segment, array $seg): void
    {
        $fromName     = $seg['from_name'] ?? null;
        $fromSanskrit = $seg['from_sanskrit'] ?? null;
        $toName       = $seg['to_name'] ?? null;
        $toSanskrit   = $seg['to_sanskrit'] ?? null;
        $confidence   = isset($seg['confidence']) ? round((float) $seg['confidence'], 2) : null;

        if ($fromName) {
            $fromMove = $this->resolver->resolve($fromName, $fromSanskrit);
            SegmentMove::create([
                'video_segment_id' => $segment->id,
                'yoga_move_id'     => $fromMove->id,
                'role'             => 'transition_from',
                'side'             => 'n_a',
                'hold_count'       => null,
                'ai_confidence'    => $confidence,
                'created_at'       => now(),
            ]);
        }

        if ($toName) {
            $toMove = $this->resolver->resolve($toName, $toSanskrit);
            SegmentMove::create([
                'video_segment_id' => $segment->id,
                'yoga_move_id'     => $toMove->id,
                'role'             => 'transition_to',
                'side'             => 'n_a',
                'hold_count'       => null,
                'ai_confidence'    => $confidence,
                'created_at'       => now(),
            ]);
        }
    }

    private function normaliseSide(?string $side): string
    {
        return match (strtolower(trim((string) $side))) {
            'left'  => 'left',
            'right' => 'right',
            'both'  => 'both',
            default => 'n_a',
        };
    }
}
