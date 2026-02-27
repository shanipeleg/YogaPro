<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoAnalysisLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function index()
    {
        // Pending jobs
        $pendingJobs = DB::table('jobs')
            ->orderBy('available_at')
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload);
                $job->display_name = $payload->displayName ?? 'Unknown';
                return $job;
            });

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload);
                $job->display_name = $payload->displayName ?? 'Unknown';
                $job->exception_excerpt = substr($job->exception, 0, 120);
                return $job;
            });

        // Worker status
        $workerPid    = shell_exec('pgrep -f "queue:work"');
        $workerRunning = !empty(trim((string) $workerPid));

        // Last 20 analysis log entries
        $recentLogs = VideoAnalysisLog::with('video')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.queue', compact('pendingJobs', 'failedJobs', 'workerRunning', 'recentLogs'));
    }

    public function triggerScan()
    {
        Artisan::call('channel:scan');
        $output = Artisan::output();

        return back()
            ->with('trigger_output', $output ?: '(no output)')
            ->with('trigger_name', 'Scan Channel');
    }

    public function triggerAnalyze()
    {
        Artisan::call('videos:analyze', ['--limit' => 5]);
        $output = Artisan::output();

        return back()
            ->with('trigger_output', $output ?: '(no output)')
            ->with('trigger_name', 'Analyze 5 Videos');
    }

    public function triggerEnrich()
    {
        Artisan::call('yoga:enrich', ['--limit' => 10, '--sync' => true]);
        $output = Artisan::output();

        return back()
            ->with('trigger_output', $output ?: '(no output)')
            ->with('trigger_name', 'Enrich Poses');
    }

    public function retryFailed(Request $request, string $uuid)
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('success', "Job {$uuid} re-queued.");
    }

    public function deleteFailed(string $uuid)
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        return back()->with('success', "Failed job deleted.");
    }

    public function retryAllFailed()
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', 'All failed jobs re-queued.');
    }
}
