<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationEngine $engine) {}

    /**
     * POST /api/recommendations
     *
     * Request body:
     * {
     *   "body_state": [{"zone": "lower_back", "mode": "sore"}, {"zone": "hips", "mode": "target"}],
     *   "energy_level": 3,
     *   "time_min": 20,
     *   "time_max": 30,
     *   "goals": ["stretch", "back_pain_relief"]
     * }
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

        // Eager load pose preview data for all recommended videos in 3 queries
        $videoIds = collect($results)->pluck('video.id');
        $videosWithPoses = Video::whereIn('id', $videoIds)
            ->with(['segments' => fn($q) => $q
                ->where('segment_type', 'pose')
                ->with(['segmentMoves' => fn($q) => $q
                    ->where('role', 'main')
                    ->with('yogaMove'),
                ]),
            ])
            ->get()
            ->keyBy('id');

        foreach ($results as &$r) {
            $r['video'] = $videosWithPoses[$r['video']->id] ?? $r['video'];
        }
        unset($r);

        return response()->json([
            'results' => collect($results)->map(fn($r) => [
                'video' => [
                    'id'               => $r['video']->id,
                    'youtube_id'       => $r['video']->youtube_id,
                    'title'            => $r['video']->title,
                    'url'              => $r['video']->url,
                    'thumbnail_url'    => $r['video']->thumbnail_url,
                    'duration_seconds' => $r['video']->duration_seconds,
                    'published_at'     => $r['video']->published_at?->toDateString(),
                    'view_count'       => $r['video']->view_count,
                    'pose_preview'     => $r['video']->posePreviewData(15),
                ],
                'score'          => $r['score'],
                'chips'          => $r['chips'],
                'key_factors'    => $r['key_factors'],
                'avg_transition' => $r['avg_transition'],
                'avoided_count'  => $r['avoided_count'],
                'fav_count'      => $r['fav_count'],
                'new_pose_count' => $r['new_pose_count'],
                'session_count'  => $r['session_count'],
                'last_rating'    => $r['last_rating'],
                'last_watched_at'=> $r['last_watched_at'],
            ])->values(),
            'total'   => count($results),
        ]);
    }
}
