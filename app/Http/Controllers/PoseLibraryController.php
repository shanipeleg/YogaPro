<?php

namespace App\Http\Controllers;

use App\Models\UserMoveOpinion;
use App\Models\YogaMove;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoseLibraryController extends Controller
{
    /**
     * GET /poses — browse view
     */
    public function index(Request $request)
    {
        $query = YogaMove::query()->with('userOpinion');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sanskrit_name', 'like', "%{$search}%");
            });
        }

        // Zone filter
        if ($zone = $request->input('zone')) {
            $col = 'targets_' . $zone;
            $query->where($col, true);
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // My rating filter
        $ratingFilter = $request->input('rating');
        if ($ratingFilter === 'favorites') {
            $query->whereHas('userOpinion', fn($q) => $q->where('comfort_level', '>=', 4));
        } elseif ($ratingFilter === 'avoided') {
            $query->whereHas('userOpinion', fn($q) => $q->where('is_avoided', true));
        } elseif ($ratingFilter === 'unrated') {
            $query->whereDoesntHave('userOpinion');
        }

        // Sort
        $sort = $request->input('sort', 'name');
        match ($sort) {
            'comfort_desc'    => $query->leftJoin('user_move_opinions as umo', 'umo.yoga_move_id', '=', 'yoga_moves.id')
                                       ->orderByDesc('umo.comfort_level')
                                       ->select('yoga_moves.*'),
            'difficulty_asc'  => $query->orderBy('difficulty_base'),
            'difficulty_desc' => $query->orderByDesc('difficulty_base'),
            default           => $query->orderBy('name'),
        };

        $moves = $query->paginate(50)->withQueryString();

        $categories = YogaMove::distinct()->whereNotNull('category')->pluck('category')->sort()->values();

        $unratedCount = YogaMove::whereDoesntHave('userOpinion')->count();

        return view('poses.index', compact('moves', 'categories', 'unratedCount'));
    }

    /**
     * GET /poses/{move} — pose detail
     */
    public function show(YogaMove $move)
    {
        $move->load('userOpinion');

        // Videos containing this pose (via segment_moves)
        $appearsIn = \App\Models\Video::whereHas('segments.segmentMoves', function ($q) use ($move) {
            $q->where('yoga_move_id', $move->id)->where('role', 'main');
        })
        ->where('analysis_status', 'analyzed')
        ->withCount([
            'segments as pose_count' => fn($q) => $q->where('segment_type', 'pose'),
        ])
        ->orderByDesc('view_count')
        ->limit(10)
        ->get();

        return view('poses.show', compact('move', 'appearsIn'));
    }

    /**
     * PUT /api/moves/{move}/opinion
     */
    public function upsertOpinion(Request $request, YogaMove $move): JsonResponse
    {
        $validated = $request->validate([
            'personal_difficulty'   => 'nullable|integer|min:1|max:10',
            'comfort_level'         => 'nullable|integer|min:1|max:5',
            'is_avoided'            => 'nullable|boolean',
            'avoid_reason'          => 'nullable|string|max:500',
            'personal_notes'        => 'nullable|string|max:2000',
        ]);

        $opinion = UserMoveOpinion::updateOrCreate(
            ['yoga_move_id' => $move->id],
            array_merge($validated, ['updated_at' => now()])
        );

        return response()->json($opinion);
    }

    /**
     * GET /api/moves/unrated — for swipe mode
     */
    public function unrated(): JsonResponse
    {
        $moves = YogaMove::whereDoesntHave('userOpinion')
            ->where('enrichment_status', 'enriched')
            ->inRandomOrder()
            ->limit(50)
            ->get(['id', 'name', 'sanskrit_name', 'category', 'description', 'difficulty_base',
                   'benefit_back_pain_lower', 'targets_lower_back', 'targets_hips', 'targets_shoulders',
                   'targets_core', 'targets_hamstrings']);

        return response()->json([
            'moves' => $moves,
            'total' => YogaMove::whereDoesntHave('userOpinion')->count(),
        ]);
    }
}
