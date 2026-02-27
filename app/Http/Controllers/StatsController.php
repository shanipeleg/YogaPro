<?php

namespace App\Http\Controllers;

use App\Models\UserMoveOpinion;
use App\Models\Video;
use App\Models\VideoAnalysisLog;
use App\Models\YogaMove;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        // Pipeline status
        $totalVideos    = Video::count();
        $analyzedVideos = Video::where('analysis_status', 'analyzed')->count();
        $pendingVideos  = Video::where('analysis_status', 'pending')->count();
        $failedVideos   = Video::where('analysis_status', 'failed')->count();
        $lastAnalyzed   = Video::where('analysis_status', 'analyzed')->max('analyzed_at');
        $queueDepth     = DB::table('jobs')->count();
        $analysisPercent = $totalVideos > 0 ? round($analyzedVideos / $totalVideos * 100, 1) : 0;

        // Yoga move knowledge base
        $totalMoves    = YogaMove::count();
        $enrichedMoves = YogaMove::where('enrichment_status', 'enriched')->count();
        $pendingMoves  = YogaMove::where('enrichment_status', 'pending')->count();
        $favoriteMoves = UserMoveOpinion::where('comfort_level', '>=', 4)->count();
        $avoidedMoves  = UserMoveOpinion::where('is_avoided', true)->count();
        $unratedMoves  = YogaMove::whereDoesntHave('userOpinion')->count();

        // Video content stats (across analyzed videos only)
        $avgSegmentsPerVideo = DB::table('video_segments')
            ->join('videos', 'videos.id', '=', 'video_segments.video_id')
            ->where('videos.analysis_status', 'analyzed')
            ->select(DB::raw('COUNT(*) / COUNT(DISTINCT video_segments.video_id) as avg'))
            ->value('avg');

        $avgPoseHold = DB::table('video_segments')
            ->join('videos', 'videos.id', '=', 'video_segments.video_id')
            ->where('videos.analysis_status', 'analyzed')
            ->where('video_segments.segment_type', 'pose')
            ->avg('video_segments.duration_seconds');

        $avgTransitionTime = DB::table('video_segments')
            ->join('videos', 'videos.id', '=', 'video_segments.video_id')
            ->where('videos.analysis_status', 'analyzed')
            ->where('video_segments.segment_type', 'transition')
            ->avg('video_segments.duration_seconds');

        $shortestVideo = Video::where('analysis_status', 'analyzed')->min('duration_seconds');
        $longestVideo  = Video::where('analysis_status', 'analyzed')->max('duration_seconds');

        // Top 10 most common poses
        $topPoses = DB::table('segment_moves')
            ->join('video_segments', 'video_segments.id', '=', 'segment_moves.video_segment_id')
            ->join('videos', 'videos.id', '=', 'video_segments.video_id')
            ->join('yoga_moves', 'yoga_moves.id', '=', 'segment_moves.yoga_move_id')
            ->where('videos.analysis_status', 'analyzed')
            ->where('segment_moves.role', 'main')
            ->select('yoga_moves.id', 'yoga_moves.name', 'yoga_moves.sanskrit_name', DB::raw('COUNT(*) as appearance_count'))
            ->groupBy('yoga_moves.id', 'yoga_moves.name', 'yoga_moves.sanskrit_name')
            ->orderByDesc('appearance_count')
            ->limit(10)
            ->get();

        // Back pain safety overview
        $riskPoses = DB::table('yoga_moves')
            ->where(function ($q) {
                $q->where('benefit_back_pain_lower', 'avoid')
                  ->orWhere('spinal_compression', true);
            })
            ->select(
                'yoga_moves.id',
                'yoga_moves.name',
                'yoga_moves.benefit_back_pain_lower',
                'yoga_moves.spinal_compression',
                DB::raw('(
                    SELECT COUNT(DISTINCT videos.id)
                    FROM segment_moves
                    JOIN video_segments ON video_segments.id = segment_moves.video_segment_id
                    JOIN videos ON videos.id = video_segments.video_id
                    WHERE segment_moves.yoga_move_id = yoga_moves.id
                    AND videos.analysis_status = \'analyzed\'
                ) as video_count')
            )
            ->orderByDesc('video_count')
            ->get();

        // Enrich risk poses with personal flags
        $avoidedMoveIds = UserMoveOpinion::where('is_avoided', true)->pluck('yoga_move_id');
        $riskPoses = $riskPoses->map(function ($pose) use ($avoidedMoveIds) {
            $pose->personally_avoided = $avoidedMoveIds->contains($pose->id);
            return $pose;
        });

        // Gemini cost tracking
        $totalTokensPrompt    = VideoAnalysisLog::where('status', 'success')->sum('tokens_prompt');
        $totalTokensResponse  = VideoAnalysisLog::where('status', 'success')->sum('tokens_response');
        $totalTokens          = $totalTokensPrompt + $totalTokensResponse;
        $estimatedCostDollars = round($totalTokens * 0.000075 / 1000, 4);

        return view('stats.index', compact(
            'totalVideos', 'analyzedVideos', 'pendingVideos', 'failedVideos',
            'lastAnalyzed', 'queueDepth', 'analysisPercent',
            'totalMoves', 'enrichedMoves', 'pendingMoves',
            'favoriteMoves', 'avoidedMoves', 'unratedMoves',
            'avgSegmentsPerVideo', 'avgPoseHold', 'avgTransitionTime',
            'shortestVideo', 'longestVideo', 'topPoses',
            'riskPoses', 'totalTokens', 'estimatedCostDollars'
        ));
    }
}
