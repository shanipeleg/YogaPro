<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Video;
use Illuminate\Support\Facades\Artisan;

class ChannelController extends Controller
{
    public function index()
    {
        $channel = Channel::first();

        $counts = Video::query()
            ->selectRaw('analysis_status, COUNT(*) as count')
            ->groupBy('analysis_status')
            ->pluck('count', 'analysis_status');

        $totalCount      = $counts->sum();
        $analyzedCount   = $counts->get('analyzed', 0);
        $pendingCount    = $counts->get('pending', 0);
        $processingCount = $counts->get('processing', 0);
        $failedCount     = $counts->get('failed', 0);

        return view('admin.channel.index', compact(
            'channel', 'totalCount', 'analyzedCount', 'pendingCount', 'processingCount', 'failedCount'
        ));
    }

    public function scan()
    {
        Artisan::call('channel:scan');
        $output = Artisan::output();

        return back()
            ->with('trigger_output', $output ?: '(no output)')
            ->with('trigger_name', 'Scan Channel');
    }
}
