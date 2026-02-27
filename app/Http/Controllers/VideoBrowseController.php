<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;

class VideoBrowseController extends Controller
{
    public function index(Request $request)
    {
        $query = Video::where('analysis_status', 'analyzed');

        // Title search
        if ($search = $request->input('q')) {
            $query->where('title', 'like', "%{$search}%");
        }

        // Duration filter
        match ($request->input('duration', 'all')) {
            'short'  => $query->whereBetween('duration_seconds', [300, 900]),   // 5–15 min
            'medium' => $query->whereBetween('duration_seconds', [900, 1800]),  // 15–30 min
            'long'   => $query->where('duration_seconds', '>', 1800),           // 30+ min
            default  => null,
        };

        $videos = $query
            ->orderByDesc('published_at')
            ->paginate(20)
            ->withQueryString();

        // Eager load pose segments + moves so posePreviewData() works without N+1
        $videos->load(['segments' => fn($q) => $q
            ->where('segment_type', 'pose')
            ->with(['segmentMoves' => fn($q) => $q
                ->where('role', 'main')
                ->with('yogaMove'),
            ]),
        ]);

        return view('videos.index', [
            'videos'   => $videos,
            'search'   => $request->input('q', ''),
            'duration' => $request->input('duration', 'all'),
        ]);
    }
}
