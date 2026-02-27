<?php

namespace App\Http\Controllers;

use App\Models\BodyStatePreset;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Models\UserMoveOpinion;
use App\Services\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MomentFinderController extends Controller
{
    public function __construct(private RecommendationEngine $engine) {}

    public function index()
    {
        $presets = BodyStatePreset::orderBy('name')->get();

        return view('home', compact('presets'));
    }

    /**
     * POST /api/recommendations (called by the Moment Finder UI via Alpine fetch)
     */
    public function recommend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body_state'            => 'nullable|array',
            'body_state.*.zone'     => 'required_with:body_state|string',
            'body_state.*.mode'     => 'required_with:body_state|in:sore,target',
            'energy_level'          => 'nullable|integer|min:1|max:5',
            'time_min'              => 'nullable|integer|min:1|max:180',
            'time_max'              => 'nullable|integer|min:1|max:180',
            'goals'                 => 'nullable|array',
            'goals.*'               => 'string|in:stretch,strengthen,relax,back_pain_relief,try_something_new,challenge_me,my_favorites',
        ]);

        $results = $this->engine->recommend($validated);

        return response()->json([
            'results' => collect($results)->map(fn($r) => [
                'video' => [
                    'id'               => $r['video']->id,
                    'youtube_id'       => $r['video']->youtube_id,
                    'title'            => $r['video']->title,
                    'url'              => $r['video']->url,
                    'thumbnail_url'    => $r['video']->thumbnail_url,
                    'duration_seconds' => $r['video']->duration_seconds,
                    'view_count'       => $r['video']->view_count,
                ],
                'score'          => $r['score'],
                'chips'          => $r['chips'],
                'key_factors'    => $r['key_factors'],
                'avg_transition' => $r['avg_transition'],
                'avoided_count'  => $r['avoided_count'],
                'fav_count'      => $r['fav_count'],
                'new_pose_count' => $r['new_pose_count'],
            ])->values(),
            'total' => count($results),
        ]);
    }

    /**
     * GET /videos/{id}
     */
    public function show(Video $video)
    {
        // Load all segments with their yoga moves
        $video->load([
            'segments.segmentMoves.yogaMove',
            'userSessions' => fn($q) => $q->latest('watched_at'),
        ]);

        // Load personal opinions for all moves in this video
        $moveIds = $video->segments
            ->flatMap(fn($s) => $s->segmentMoves)
            ->pluck('yoga_move_id')
            ->unique();

        $opinions = UserMoveOpinion::whereIn('yoga_move_id', $moveIds)
            ->get()
            ->keyBy('yoga_move_id');

        // Body map: aggregate zone coverage by hold time
        $bodyMap = $this->computeBodyMap($video);

        return view('videos.show', compact('video', 'opinions', 'bodyMap'));
    }

    // ─────────────────────────────────────────────────────────
    // API: body state presets
    // ─────────────────────────────────────────────────────────

    public function listPresets(): JsonResponse
    {
        return response()->json(BodyStatePreset::orderBy('name')->get());
    }

    public function createPreset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:128',
            'zones'         => 'required|array',
            'zones.*.zone'  => 'required|string',
            'zones.*.mode'  => 'required|in:sore,target',
        ]);

        $preset = BodyStatePreset::create([
            'name'  => $validated['name'],
            'zones' => $validated['zones'],
        ]);

        return response()->json($preset, 201);
    }

    public function deletePreset(BodyStatePreset $preset): JsonResponse
    {
        $preset->delete();
        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────
    // Helper: body map computation
    // ─────────────────────────────────────────────────────────

    private function computeBodyMap(Video $video): array
    {
        $zoneColumns = [
            'Lower back'  => 'targets_lower_back',
            'Upper back'  => 'targets_upper_back',
            'Mid back'    => 'targets_mid_back',
            'Pelvis'      => 'targets_pelvis',
            'Hips'        => 'targets_hips',
            'Hamstrings'  => 'targets_hamstrings',
            'Hip flexors' => 'targets_hip_flexors',
            'Glutes'      => 'targets_glutes',
            'Core'        => 'targets_core',
            'Shoulders'   => 'targets_shoulders',
            'Neck'        => 'targets_neck',
            'Chest'       => 'targets_chest',
            'Quads'       => 'targets_quads',
            'Calves'      => 'targets_calves',
            'Ankles'      => 'targets_ankles',
            'Wrists'      => 'targets_wrists',
        ];

        $zoneTotals = array_fill_keys(array_keys($zoneColumns), 0.0);
        $totalTime  = 0.0;

        foreach ($video->segments as $segment) {
            if ($segment->segment_type !== 'pose') {
                continue;
            }

            $duration = $segment->duration_seconds ?? 0;
            $totalTime += $duration;

            $mainMove = $segment->segmentMoves->firstWhere('role', 'main');
            if (!$mainMove || !$mainMove->yogaMove) {
                continue;
            }

            $move = $mainMove->yogaMove;
            foreach ($zoneColumns as $label => $col) {
                if ($move->{$col}) {
                    $zoneTotals[$label] += $duration;
                }
            }
        }

        if ($totalTime === 0.0) {
            return [];
        }

        $result = [];
        foreach ($zoneTotals as $label => $time) {
            if ($time > 0) {
                $result[] = [
                    'zone'    => $label,
                    'seconds' => $time,
                    'pct'     => round($time / $totalTime * 100, 1),
                ];
            }
        }

        usort($result, fn($a, $b) => $b['pct'] <=> $a['pct']);

        return $result;
    }
}
