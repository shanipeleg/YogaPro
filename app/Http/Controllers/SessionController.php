<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSessionFeedbackJob;
use App\Models\SessionMoveFlag;
use App\Models\UserSession;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * GET /history
     */
    public function index()
    {
        $sessions = UserSession::with('video')
            ->latest('watched_at')
            ->paginate(25);

        $totalSessions = UserSession::count();
        $avgRating     = UserSession::whereNotNull('overall_rating')->avg('overall_rating');
        $topVideo      = UserSession::select('video_id')
            ->groupBy('video_id')
            ->orderByRaw('COUNT(*) DESC')
            ->with('video')
            ->first()?->video;

        // Insights: top 3 most-loved poses (loved flags)
        $lovedPoses = \App\Models\SessionMoveFlag::where('flag', 'loved')
            ->with('yogaMove')
            ->select('yoga_move_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as love_count'))
            ->groupBy('yoga_move_id')
            ->orderByDesc('love_count')
            ->limit(3)
            ->get();

        return view('history.index', compact('sessions', 'totalSessions', 'avgRating', 'topVideo', 'lovedPoses'));
    }

    /**
     * POST /api/sessions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id'             => 'required|exists:videos,id',
            'watched_at'           => 'nullable|date',
            'completed_full_video' => 'nullable|boolean',
            'overall_rating'       => 'nullable|integer|min:1|max:5',
            'notes'                => 'nullable|string|max:2000',
            'body_state'           => 'nullable|array',
            'energy_level'         => 'nullable|integer|min:1|max:5',
            'time_available'       => 'nullable|integer|min:1|max:120',
            'goals'                => 'nullable|array',
            'tags'                 => 'nullable|array',
            'tags.*'               => 'string|max:64',
        ]);

        $session = UserSession::create(array_merge($validated, [
            'watched_at' => $validated['watched_at'] ?? now(),
        ]));

        return response()->json($session->load('video'), 201);
    }

    /**
     * PUT /api/sessions/{session}
     */
    public function update(Request $request, UserSession $session): JsonResponse
    {
        $validated = $request->validate([
            'overall_rating'       => 'nullable|integer|min:1|max:5',
            'notes'                => 'nullable|string|max:2000',
            'completed_full_video' => 'nullable|boolean',
            'tags'                 => 'nullable|array',
            'tags.*'               => 'string|max:64',
        ]);

        $session->update($validated);

        return response()->json($session);
    }

    /**
     * POST /api/sessions/{session}/flags — per-move feedback
     */
    public function storeFlags(Request $request, UserSession $session): JsonResponse
    {
        $request->validate([
            'flags'                         => 'required|array',
            'flags.*.yoga_move_id'          => 'required|exists:yoga_moves,id',
            'flags.*.flag'                  => 'required|in:loved,uncomfortable,avoided,unclear_instructions,too_hard,too_easy',
            'flags.*.conditional_avoidance' => 'nullable|array',
            'flags.*.notes'                 => 'nullable|string|max:500',
        ]);

        $created = [];
        foreach ($request->flags as $flagData) {
            $created[] = SessionMoveFlag::create([
                'user_session_id'      => $session->id,
                'yoga_move_id'         => $flagData['yoga_move_id'],
                'flag'                 => $flagData['flag'],
                'conditional_avoidance'=> $flagData['conditional_avoidance'] ?? null,
                'notes'                => $flagData['notes'] ?? null,
            ]);
        }

        // Fire the learning loop job
        ProcessSessionFeedbackJob::dispatch($session->id);

        return response()->json($created, 201);
    }

    /**
     * GET /api/sessions — history list (paginated)
     */
    public function list(Request $request): JsonResponse
    {
        $sessions = UserSession::with('video')
            ->latest('watched_at')
            ->paginate(25);

        return response()->json($sessions);
    }

    /**
     * GET /api/sessions/{session}
     */
    public function show(UserSession $session): JsonResponse
    {
        return response()->json($session->load(['video', 'moveFlags.yogaMove']));
    }
}
