@extends('layouts.admin')

@section('title', 'Channel')

@section('content')
<h1 class="text-xl font-bold text-gray-900 mb-6">Channel Info</h1>

{{-- Artisan output --}}
@if(session('trigger_output'))
<div class="mb-6 bg-gray-900 rounded p-4">
    <p class="text-green-400 text-xs font-mono mb-2">▶ {{ session('trigger_name') }} completed</p>
    <pre class="text-gray-200 text-xs font-mono whitespace-pre-wrap overflow-auto max-h-64">{{ session('trigger_output') }}</pre>
</div>
@endif

@if($channel)
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

        {{-- Channel card --}}
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded p-5">
            <div class="flex items-start gap-4">
                @if($channel->thumbnail_url)
                    <img src="{{ $channel->thumbnail_url }}" alt="{{ $channel->name }}" class="w-20 h-20 rounded-full object-cover flex-shrink-0">
                @endif
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-gray-900">{{ $channel->name }}</h2>
                    <p class="text-gray-500 text-sm">{{ $channel->handle }}</p>
                    <p class="text-xs text-gray-400 font-mono mt-1">{{ $channel->youtube_channel_id }}</p>
                    @if($channel->description)
                        <p class="text-sm text-gray-600 mt-2 line-clamp-3">{{ $channel->description }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Last scanned:</span>
                    <span class="font-medium ml-1">{{ $channel->last_scanned_at?->format('M j, Y H:i') ?? 'Never' }}</span>
                    @if($channel->last_scanned_at)
                        <span class="text-gray-400 text-xs ml-1">({{ $channel->last_scanned_at->diffForHumans() }})</span>
                    @endif
                </div>
                <div class="flex gap-3">
                    <a href="https://www.youtube.com/channel/{{ $channel->youtube_channel_id }}" target="_blank"
                       class="inline-flex items-center px-3 py-1.5 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
                        View on YouTube ↗
                    </a>
                    <form method="POST" action="{{ route('admin.channel.scan') }}" data-trigger>
                        @csrf
                        <button type="submit"
                                class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                            Scan Channel Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Video stats --}}
        <div class="bg-white border border-gray-200 rounded p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Video Stats</h2>
            @if($totalCount > 0)
                <div class="mb-3">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ number_format($analyzedCount) }} / {{ number_format($totalCount) }} analyzed</span>
                        <span>{{ round($analyzedCount / $totalCount * 100) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ round($analyzedCount / $totalCount * 100) }}%"></div>
                    </div>
                </div>
            @endif
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Total</dt>
                    <dd class="font-mono font-medium">{{ number_format($totalCount) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Analyzed</dt>
                    <dd class="font-mono text-green-700">{{ number_format($analyzedCount) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Pending</dt>
                    <dd class="font-mono text-gray-600">{{ number_format($pendingCount) }}</dd>
                </div>
                @if($processingCount > 0)
                <div class="flex justify-between">
                    <dt class="text-gray-500">Processing</dt>
                    <dd class="font-mono text-yellow-700">{{ number_format($processingCount) }}</dd>
                </div>
                @endif
                @if($failedCount > 0)
                <div class="flex justify-between">
                    <dt class="text-gray-500">Failed</dt>
                    <dd class="font-mono text-red-600">{{ number_format($failedCount) }}</dd>
                </div>
                @endif
            </dl>
            <a href="{{ route('admin.videos') }}" class="mt-3 inline-block text-xs text-blue-600 hover:underline">Browse videos →</a>
        </div>
    </div>
@else
    <div class="bg-white border border-gray-200 rounded p-8 text-center">
        <p class="text-gray-500 mb-4">No channel found in the database.</p>
        <form method="POST" action="{{ route('admin.channel.scan') }}" data-trigger>
            @csrf
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Run Channel Scan to Import
            </button>
        </form>
    </div>
@endif
@endsection
