@extends('layouts.admin')

@section('title', 'Analysis Logs')

@section('content')
<h1 class="text-xl font-bold text-gray-900 mb-4">Analysis Logs</h1>

{{-- Summary --}}
<div class="flex flex-wrap gap-4 mb-5">
    <div class="bg-white border border-gray-200 rounded px-4 py-3 text-sm">
        <span class="text-gray-500">Total tokens:</span>
        <span class="font-mono font-medium ml-1">{{ number_format($totalTokens) }}</span>
    </div>
    <div class="bg-white border border-gray-200 rounded px-4 py-3 text-sm">
        <span class="text-gray-500">Estimated cost:</span>
        <span class="font-mono font-medium ml-1">${{ $estimatedCost }}</span>
        <span class="text-gray-400 text-xs ml-1">($0.000075/1K tokens)</span>
    </div>
</div>

{{-- Filters --}}
<div class="flex flex-wrap gap-3 mb-4">
    {{-- Status tabs --}}
    <div class="flex gap-1 border-b border-gray-200">
        @foreach(['all' => 'All', 'success' => 'Success', 'error' => 'Error'] as $key => $label)
            <a href="{{ route('admin.logs', array_merge(request()->query(), ['status' => $key])) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                   {{ $status === $key
                       ? 'border-blue-600 text-blue-600'
                       : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.logs') }}" class="flex gap-2">
        @if($status !== 'all') <input type="hidden" name="status" value="{{ $status }}"> @endif
        @if($videoId) <input type="hidden" name="video_id" value="{{ $videoId }}"> @endif
        <input type="text" name="search" value="{{ $search }}"
               placeholder="Search by video title…"
               class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
        <button type="submit" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Search</button>
        @if($search)
            <a href="{{ route('admin.logs', array_merge(request()->except('search'))) }}" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>

    @if($videoId)
        <div class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-700 rounded text-sm">
            Filtered to video #{{ $videoId }}
            <a href="{{ route('admin.logs', request()->except('video_id')) }}" class="hover:text-blue-900 font-medium">×</a>
        </div>
    @endif
</div>

{{-- Table --}}
<div class="bg-white border border-gray-200 rounded overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-2">ID</th>
                <th class="text-left px-4 py-2">Video</th>
                <th class="text-left px-4 py-2">Model</th>
                <th class="text-left px-4 py-2">Status</th>
                <th class="text-left px-4 py-2">Prompt Tokens</th>
                <th class="text-left px-4 py-2">Response Tokens</th>
                <th class="text-left px-4 py-2">Total</th>
                <th class="text-left px-4 py-2">When</th>
                <th class="text-left px-4 py-2">Details</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($logs as $log)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $log->id }}</td>
                    <td class="px-4 py-2 max-w-xs">
                        <a href="{{ route('admin.videos', ['status' => 'all']) }}#video-{{ $log->video_id }}"
                           class="text-gray-800 hover:text-blue-600 line-clamp-1 text-xs">
                            {{ $log->video?->title ?? 'Video #'.$log->video_id }}
                        </a>
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500 font-mono">{{ $log->gemini_model }}</td>
                    <td class="px-4 py-2">
                        @if($log->status === 'success')
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">success</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">error</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ number_format($log->tokens_prompt ?? 0) }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ number_format($log->tokens_response ?? 0) }}</td>
                    <td class="px-4 py-2 font-mono text-xs font-medium">{{ number_format(($log->tokens_prompt ?? 0) + ($log->tokens_response ?? 0)) }}</td>
                    <td class="px-4 py-2 text-gray-400 text-xs whitespace-nowrap">{{ $log->created_at?->format('M j H:i') }}</td>
                    <td class="px-4 py-2">
                        <details class="text-xs">
                            <summary class="cursor-pointer text-blue-600 hover:underline select-none">Expand</summary>
                            <div class="mt-2 space-y-2">
                                @if($log->status === 'error' && $log->error_message)
                                    <div>
                                        <p class="text-xs text-red-600 font-semibold mb-1">Error:</p>
                                        <pre class="text-xs text-red-700 bg-red-50 p-2 rounded overflow-auto max-h-40 whitespace-pre-wrap">{{ $log->error_message }}</pre>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-xs text-gray-400 font-semibold mb-1">Prompt used:</p>
                                    <pre class="text-xs text-gray-600 bg-gray-50 p-2 rounded overflow-auto max-h-32 whitespace-pre-wrap">{{ $log->prompt_used }}</pre>
                                </div>
                                @if($log->raw_response)
                                    <div>
                                        <p class="text-xs text-gray-400 font-semibold mb-1">Raw response:</p>
                                        <pre class="text-xs text-gray-600 bg-gray-50 p-2 rounded overflow-auto max-h-64 whitespace-pre-wrap">{{ json_encode($log->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-400 italic">No logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection
