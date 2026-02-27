<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoAnalysisLog;
use App\Models\YogaMove;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        // Queue health
        $pendingJobs  = DB::table('jobs')->count();
        $failedJobs   = DB::table('failed_jobs')->count();
        $workerPid    = shell_exec('pgrep -f "queue:work"');
        $workerRunning = !empty(trim((string) $workerPid));

        // Analysis pipeline
        $videoStats = Video::query()
            ->selectRaw('analysis_status, COUNT(*) as count')
            ->groupBy('analysis_status')
            ->pluck('count', 'analysis_status');

        $totalVideos    = $videoStats->sum();
        $analyzedVideos = $videoStats->get('analyzed', 0);
        $pendingVideos  = $videoStats->get('pending', 0);
        $processingVideos = $videoStats->get('processing', 0);
        $failedVideos   = $videoStats->get('failed', 0);

        // Enrichment
        $moveStats = YogaMove::query()
            ->selectRaw('enrichment_status, COUNT(*) as count')
            ->groupBy('enrichment_status')
            ->pluck('count', 'enrichment_status');

        $totalMoves    = $moveStats->sum();
        $enrichedMoves = $moveStats->get('enriched', 0);
        $pendingMoves  = $moveStats->get('pending', 0);

        // Last activity
        $lastAnalyzed = Video::where('analysis_status', 'analyzed')
            ->orderByDesc('analyzed_at')
            ->first(['id', 'title', 'analyzed_at', 'youtube_id']);

        $lastFailed = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->first();

        return view('admin.dashboard', compact(
            'pendingJobs', 'failedJobs', 'workerRunning',
            'totalVideos', 'analyzedVideos', 'pendingVideos', 'processingVideos', 'failedVideos',
            'totalMoves', 'enrichedMoves', 'pendingMoves',
            'lastAnalyzed', 'lastFailed'
        ));
    }
}
