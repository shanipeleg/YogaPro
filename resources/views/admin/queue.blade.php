@extends('layouts.admin')

@section('title', 'Queue')

@section('content')
<h1 class="text-xl font-bold text-gray-900 mb-6">Queue Monitor</h1>

{{-- Worker Status --}}
<div class="mb-5 flex items-center gap-3">
    @if($workerRunning)
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">● Worker running</span>
    @else
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">● Worker DOWN</span>
        <span class="text-xs text-gray-500">Run: <code class="bg-gray-100 px-1 rounded">php artisan queue:work database --sleep=3 --tries=3 --timeout=600</code></span>
    @endif
</div>

{{-- Artisan trigger output --}}
@if(session('trigger_output'))
<div class="mb-6 bg-gray-900 rounded p-4">
    <p class="text-green-400 text-xs font-mono mb-2">▶ {{ session('trigger_name') }} completed</p>
    <pre class="text-gray-200 text-xs font-mono whitespace-pre-wrap overflow-auto max-h-64">{{ session('trigger_output') }}</pre>
</div>
@endif

{{-- Manual Trigger Panel --}}
<div class="bg-white border border-gray-200 rounded p-5 mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Manual Triggers</h2>
    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('admin.queue.trigger.scan') }}" data-trigger>
            @csrf
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                Scan Channel
            </button>
        </form>

        <form method="POST" action="{{ route('admin.queue.trigger.analyze') }}" data-trigger>
            @csrf
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                Analyze 5 Videos
            </button>
        </form>

        <form method="POST" action="{{ route('admin.queue.trigger.enrich') }}" data-trigger>
            @csrf
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                Enrich Poses (10, sync)
            </button>
        </form>
    </div>
    <p class="text-xs text-gray-400 mt-3">Triggers run synchronously and display output when complete. Analyze dispatches to queue; Enrich runs immediately.</p>
</div>

{{-- Pending Jobs --}}
<div class="bg-white border border-gray-200 rounded mb-6">
    <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Pending Jobs
            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">{{ $pendingJobs->count() }}</span>
        </h2>
    </div>
    @if($pendingJobs->isEmpty())
        <p class="px-5 py-4 text-sm text-gray-400 italic">No pending jobs.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-2">Job Class</th>
                        <th class="text-left px-4 py-2">Queue</th>
                        <th class="text-left px-4 py-2">Attempts</th>
                        <th class="text-left px-4 py-2">Available At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($pendingJobs as $job)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ class_basename($job->display_name) }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $job->queue }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $job->attempts }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ \Carbon\Carbon::createFromTimestamp($job->available_at)->format('H:i:s') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Failed Jobs --}}
<div class="bg-white border border-gray-200 rounded mb-6">
    <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Failed Jobs
            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $failedJobs->count() > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500' }}">{{ $failedJobs->count() }}</span>
        </h2>
        @if($failedJobs->count() > 0)
            <form method="POST" action="{{ route('admin.queue.retry-all') }}">
                @csrf
                <button type="submit"
                        onclick="return confirm('Retry all {{ $failedJobs->count() }} failed jobs?')"
                        class="text-xs px-3 py-1.5 bg-amber-500 text-white rounded hover:bg-amber-600 transition-colors">
                    Retry All Failed
                </button>
            </form>
        @endif
    </div>
    @if($failedJobs->isEmpty())
        <p class="px-5 py-4 text-sm text-gray-400 italic">No failed jobs.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-2">ID</th>
                        <th class="text-left px-4 py-2">Job Class</th>
                        <th class="text-left px-4 py-2">Queue</th>
                        <th class="text-left px-4 py-2">Exception</th>
                        <th class="text-left px-4 py-2">Failed At</th>
                        <th class="text-left px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($failedJobs as $job)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-400 text-xs font-mono">{{ $job->id }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ class_basename($job->display_name) }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $job->queue }}</td>
                            <td class="px-4 py-2 text-xs text-red-700 font-mono max-w-xs truncate" title="{{ $job->exception }}">
                                {{ $job->exception_excerpt }}
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ \Carbon\Carbon::parse($job->failed_at)->format('M j H:i') }}</td>
                            <td class="px-4 py-2">
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.queue.retry', $job->uuid) }}">
                                        @csrf
                                        <button type="submit" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">Retry</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.queue.delete', $job->uuid) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                onclick="return confirm('Delete this failed job?')"
                                                class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Analysis Log Quick-View --}}
<div class="bg-white border border-gray-200 rounded">
    <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Recent Analysis Log <span class="text-gray-400 font-normal">(last 20)</span></h2>
        <a href="{{ route('admin.logs') }}" class="text-xs text-blue-600 hover:underline">View full log →</a>
    </div>
    @if($recentLogs->isEmpty())
        <p class="px-5 py-4 text-sm text-gray-400 italic">No analysis logs yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-2">Video</th>
                        <th class="text-left px-4 py-2">Model</th>
                        <th class="text-left px-4 py-2">Status</th>
                        <th class="text-left px-4 py-2">Tokens</th>
                        <th class="text-left px-4 py-2">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentLogs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 max-w-xs truncate text-gray-800" title="{{ $log->video?->title }}">
                                {{ $log->video?->title ?? 'Video #'.$log->video_id }}
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500 font-mono">{{ $log->gemini_model }}</td>
                            <td class="px-4 py-2">
                                @if($log->status === 'success')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">success</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">error</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600 font-mono text-xs">
                                {{ number_format(($log->tokens_prompt ?? 0) + ($log->tokens_response ?? 0)) }}
                            </td>
                            <td class="px-4 py-2 text-gray-400 text-xs">{{ $log->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
