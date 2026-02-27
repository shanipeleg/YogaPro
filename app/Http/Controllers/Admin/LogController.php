<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoAnalysisLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $status   = $request->get('status', 'all');
        $videoId  = $request->get('video_id');
        $search   = $request->get('search');

        $query = VideoAnalysisLog::with('video')
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($videoId) {
            $query->where('video_id', $videoId);
        }

        if ($search) {
            $query->whereHas('video', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        // Cost summary
        $totals = DB::table('video_analysis_log')
            ->selectRaw('SUM(tokens_prompt + tokens_response) as total_tokens')
            ->first();

        $totalTokens   = (int) ($totals->total_tokens ?? 0);
        $estimatedCost = number_format($totalTokens * 0.000075 / 1000, 4);

        return view('admin.logs.index', compact('logs', 'status', 'search', 'videoId', 'totalTokens', 'estimatedCost'));
    }
}
