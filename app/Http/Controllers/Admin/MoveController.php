<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnrichYogaMoveJob;
use App\Models\YogaMove;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoveController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');

        $query = YogaMove::query()
            ->addSelect('yoga_moves.*')
            ->addSelect(DB::raw(
                '(SELECT COUNT(DISTINCT video_segments.video_id)
                  FROM segment_moves
                  JOIN video_segments ON segment_moves.video_segment_id = video_segments.id
                  WHERE segment_moves.yoga_move_id = yoga_moves.id) as videos_count'
            ))
            ->orderBy('name');

        if ($status !== 'all') {
            $query->where('enrichment_status', $status);
        }

        $moves = $query->paginate(50)->withQueryString();

        $counts = YogaMove::query()
            ->selectRaw('enrichment_status, COUNT(*) as count')
            ->groupBy('enrichment_status')
            ->pluck('count', 'enrichment_status');

        $totalCount    = $counts->sum();
        $enrichedCount = $counts->get('enriched', 0);
        $pendingCount  = $counts->get('pending', 0);

        return view('admin.moves.index', compact(
            'moves', 'status', 'totalCount', 'enrichedCount', 'pendingCount'
        ));
    }

    public function show(YogaMove $move)
    {
        $videosCount = DB::table('segment_moves')
            ->join('video_segments', 'segment_moves.video_segment_id', '=', 'video_segments.id')
            ->where('segment_moves.yoga_move_id', $move->id)
            ->distinct('video_segments.video_id')
            ->count('video_segments.video_id');

        return view('admin.moves.show', compact('move', 'videosCount'));
    }

    public function reenrich(YogaMove $move)
    {
        $move->update(['enrichment_status' => 'pending']);
        dispatch(new EnrichYogaMoveJob($move->id));

        return back()->with('success', "Pose \"{$move->name}\" re-queued for enrichment.");
    }

    public function reenrichAllPending()
    {
        $count = YogaMove::where('enrichment_status', 'pending')->count();
        // They'll be picked up by the next yoga:enrich run; just confirm current count
        return back()->with('success', "{$count} pending poses will be enriched on next scheduler run. Run 'Enrich Poses' from the Queue page to trigger immediately.");
    }
}
