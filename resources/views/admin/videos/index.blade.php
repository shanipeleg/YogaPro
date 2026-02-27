@extends('layouts.admin')

@section('title', 'Videos')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-900">Videos</h1>
    @if($status === 'failed' && $failedCount > 0)
        <form method="POST" action="{{ route('admin.videos.requeue-all-failed') }}">
            @csrf
            <button type="submit"
                    onclick="return confirm('Reset all {{ $failedCount }} failed videos to pending?')"
                    class="px-3 py-1.5 text-sm bg-amber-500 text-white rounded hover:bg-amber-600 transition-colors">
                Re-queue All Failed ({{ $failedCount }})
            </button>
        </form>
    @endif
</div>

{{-- Summary bar --}}
<div class="flex flex-wrap gap-3 mb-5">
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 rounded-full text-sm">
        <span class="font-medium">{{ number_format($totalCount) }}</span> <span class="text-gray-500">total</span>
    </span>
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-50 text-green-800 rounded-full text-sm">
        <span class="font-medium">{{ number_format($analyzedCount) }}</span> analyzed
    </span>
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-50 text-blue-800 rounded-full text-sm">
        <span class="font-medium">{{ number_format($pendingCount) }}</span> pending
    </span>
    @if($processingCount > 0)
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-yellow-50 text-yellow-800 rounded-full text-sm">
        <span class="font-medium">{{ number_format($processingCount) }}</span> processing
    </span>
    @endif
    @if($failedCount > 0)
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-red-50 text-red-800 rounded-full text-sm">
        <span class="font-medium">{{ number_format($failedCount) }}</span> failed
    </span>
    @endif
</div>

{{-- Status tabs --}}
<div class="flex gap-1 mb-4 border-b border-gray-200">
    @foreach(['all' => 'All', 'pending' => 'Pending', 'processing' => 'Processing', 'analyzed' => 'Analyzed', 'failed' => 'Failed'] as $key => $label)
        <a href="{{ route('admin.videos', ['status' => $key]) }}"
           class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
               {{ $status === $key
                   ? 'border-blue-600 text-blue-600'
                   : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Table --}}
<div class="bg-white border border-gray-200 rounded overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-2 w-10">ID</th>
                <th class="text-left px-4 py-2 w-14">Thumb</th>
                <th class="text-left px-4 py-2">Title</th>
                <th class="text-left px-4 py-2">Duration</th>
                <th class="text-left px-4 py-2">Published</th>
                <th class="text-left px-4 py-2">Status</th>
                <th class="text-left px-4 py-2">Segments</th>
                <th class="text-left px-4 py-2">Analyzed At</th>
                <th class="text-left px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($videos as $video)
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $video->id }}</td>
                    <td class="px-4 py-2">
                        @if($video->thumbnail_url)
                            <img src="{{ $video->thumbnail_url }}" alt="" class="w-16 h-9 object-cover rounded" loading="lazy">
                        @else
                            <div class="w-16 h-9 bg-gray-200 rounded"></div>
                        @endif
                    </td>
                    <td class="px-4 py-2 max-w-xs">
                        <a href="https://www.youtube.com/watch?v={{ $video->youtube_id }}" target="_blank"
                           class="text-gray-800 hover:text-blue-600 line-clamp-2 leading-snug">
                            {{ $video->title }}
                        </a>
                    </td>
                    <td class="px-4 py-2 text-gray-600 whitespace-nowrap">
                        @if($video->duration_seconds)
                            {{ gmdate('G:i:s', $video->duration_seconds) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2 text-gray-500 whitespace-nowrap text-xs">
                        {{ $video->published_at?->format('M j, Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        @php
                            $statusStyles = [
                                'pending'    => 'bg-gray-100 text-gray-700',
                                'processing' => 'bg-yellow-100 text-yellow-800',
                                'analyzed'   => 'bg-green-100 text-green-800',
                                'failed'     => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $statusStyles[$video->analysis_status] ?? 'bg-gray-100' }}">
                            {{ $video->analysis_status }}
                        </span>
                        @if($video->analysis_status === 'failed' && $video->analysis_error)
                            <p class="text-xs text-red-600 mt-0.5 max-w-xs truncate" title="{{ $video->analysis_error }}">
                                {{ Str::limit($video->analysis_error, 80) }}
                            </p>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-gray-600 font-mono text-xs">
                        {{ $video->segments_count ?? 0 }}
                    </td>
                    <td class="px-4 py-2 text-gray-500 whitespace-nowrap text-xs">
                        {{ $video->analyzed_at?->format('M j H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.videos.reanalyze', $video) }}">
                                @csrf
                                <button type="submit"
                                        onclick="return confirm('Re-analyze this video? It will be re-queued.')"
                                        class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                    Re-analyze
                                </button>
                            </form>
                            <a href="{{ route('admin.logs', ['video_id' => $video->id]) }}"
                               class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">
                                View Log
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-400 italic">No videos found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $videos->links() }}
</div>
@endsection
