@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="text-xl font-bold text-gray-900 mb-6">Dashboard</h1>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">

    {{-- Queue Health --}}
    <div class="bg-white border border-gray-200 rounded p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Queue Health</h2>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Worker status</span>
                @if($workerRunning)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">● Running</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">● DOWN</span>
                @endif
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Pending jobs</span>
                <span class="font-mono font-medium">{{ number_format($pendingJobs) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Failed jobs</span>
                <span class="font-mono font-medium {{ $failedJobs > 0 ? 'text-red-600' : '' }}">{{ number_format($failedJobs) }}</span>
            </div>
        </div>
        <a href="{{ route('admin.queue') }}" class="mt-3 inline-block text-xs text-blue-600 hover:underline">View queue →</a>
    </div>

    {{-- Analysis Pipeline --}}
    <div class="bg-white border border-gray-200 rounded p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Analysis Pipeline</h2>
        @if($totalVideos > 0)
            <div class="mb-2">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>{{ number_format($analyzedVideos) }} / {{ number_format($totalVideos) }} analyzed</span>
                    <span>{{ round($analyzedVideos / $totalVideos * 100) }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ round($analyzedVideos / $totalVideos * 100) }}%"></div>
                </div>
            </div>
        @endif
        <div class="space-y-1 text-sm mt-3">
            <div class="flex justify-between">
                <span class="text-gray-600">Pending</span>
                <span class="font-mono">{{ number_format($pendingVideos) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Processing</span>
                <span class="font-mono">{{ number_format($processingVideos) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Failed</span>
                <span class="font-mono {{ $failedVideos > 0 ? 'text-red-600' : '' }}">{{ number_format($failedVideos) }}</span>
            </div>
        </div>
        <a href="{{ route('admin.videos') }}" class="mt-3 inline-block text-xs text-blue-600 hover:underline">View videos →</a>
    </div>

    {{-- Pose Enrichment --}}
    <div class="bg-white border border-gray-200 rounded p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Pose Enrichment</h2>
        @if($totalMoves > 0)
            <div class="mb-2">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>{{ number_format($enrichedMoves) }} / {{ number_format($totalMoves) }} enriched</span>
                    <span>{{ round($enrichedMoves / $totalMoves * 100) }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ round($enrichedMoves / $totalMoves * 100) }}%"></div>
                </div>
            </div>
        @endif
        <div class="space-y-1 text-sm mt-3">
            <div class="flex justify-between">
                <span class="text-gray-600">Pending stubs</span>
                <span class="font-mono {{ $pendingMoves > 0 ? 'text-amber-600' : '' }}">{{ number_format($pendingMoves) }}</span>
            </div>
        </div>
        <a href="{{ route('admin.moves') }}" class="mt-3 inline-block text-xs text-blue-600 hover:underline">View poses →</a>
    </div>

    {{-- Last Activity --}}
    <div class="bg-white border border-gray-200 rounded p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Last Activity</h2>
        <div class="space-y-3 text-sm">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Last analyzed video</p>
                @if($lastAnalyzed)
                    <p class="text-gray-800 font-medium truncate">{{ $lastAnalyzed->title }}</p>
                    <p class="text-gray-400 text-xs">{{ $lastAnalyzed->analyzed_at?->diffForHumans() }}</p>
                @else
                    <p class="text-gray-400 italic">None yet</p>
                @endif
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Last failed job</p>
                @if($lastFailed)
                    @php
                        $payload = json_decode($lastFailed->payload);
                        $jobName = $payload->displayName ?? 'Unknown';
                    @endphp
                    <p class="text-red-700 font-medium">{{ $jobName }}</p>
                    <p class="text-gray-400 text-xs">{{ \Carbon\Carbon::parse($lastFailed->failed_at)->diffForHumans() }}</p>
                @else
                    <p class="text-gray-400 italic">No failures</p>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
