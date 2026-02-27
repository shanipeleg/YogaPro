<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeVideoJob;
use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');

        $query = Video::withCount('segments')
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('analysis_status', $status);
        }

        $videos = $query->paginate(50)->withQueryString();

        // Summary counts
        $counts = Video::query()
            ->selectRaw('analysis_status, COUNT(*) as count')
            ->groupBy('analysis_status')
            ->pluck('count', 'analysis_status');

        $totalCount      = $counts->sum();
        $analyzedCount   = $counts->get('analyzed', 0);
        $pendingCount    = $counts->get('pending', 0);
        $processingCount = $counts->get('processing', 0);
        $failedCount     = $counts->get('failed', 0);

        return view('admin.videos.index', compact(
            'videos', 'status',
            'totalCount', 'analyzedCount', 'pendingCount', 'processingCount', 'failedCount'
        ));
    }

    public function reanalyze(Video $video)
    {
        $video->update([
            'analysis_status' => 'pending',
            'analyzed_at'     => null,
            'analysis_error'  => null,
        ]);

        dispatch(new AnalyzeVideoJob($video->id));

        return back()->with('success', "Video \"{$video->title}\" re-queued for analysis.");
    }

    public function requeueAllFailed()
    {
        Video::where('analysis_status', 'failed')
            ->update([
                'analysis_status' => 'pending',
                'analyzed_at'     => null,
                'analysis_error'  => null,
            ]);

        return back()->with('success', 'All failed videos reset to pending. They will be picked up by the next scheduled dispatch.');
    }
}
