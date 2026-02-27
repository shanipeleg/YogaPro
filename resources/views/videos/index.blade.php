@extends('layouts.app')

@section('title', 'Videos')

@section('content')

<x-page-header title="Videos" subtitle="{{ $videos->total() }} analyzed videos" />

<div class="px-4 pb-8 space-y-3">

    {{-- ── Filters ── --}}
    <form method="GET" action="{{ route('videos') }}" x-data class="space-y-3">

        {{-- Search --}}
        <input
            type="text"
            name="q"
            value="{{ $search }}"
            placeholder="Search by title…"
            class="w-full px-3 py-2.5 rounded-xl text-sm border"
            style="border-color: #E8E5E0; background: #FFFFFF; outline: none; color: #3D3D3A;"
        />

        {{-- Duration chips --}}
        <div class="flex gap-2 flex-wrap">
            @foreach(['all' => 'All lengths', 'short' => '5–15 min', 'medium' => '15–30 min', 'long' => '30+ min'] as $val => $label)
                <a href="{{ route('videos', array_merge(request()->query(), ['duration' => $val, 'page' => 1])) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium border transition-all"
                   style="{{ $duration === $val
                       ? 'background-color: #8FAF8F; color: #FFFFFF; border-color: #8FAF8F;'
                       : 'background-color: #FFFFFF; color: #6B6B67; border-color: #E8E5E0;' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Submit on search input --}}
        <button type="submit" class="sr-only">Search</button>
    </form>

    {{-- ── Video list ── --}}
    @forelse($videos as $video)
        @php
            $mins = intdiv($video->duration_seconds, 60);
        @endphp

        <a href="{{ route('videos.show', $video) }}"
           class="flex items-start gap-3 p-3 rounded-2xl transition-all active:scale-[0.98]"
           style="background-color: #FFFFFF; box-shadow: 0 2px 8px rgba(61,61,58,0.08);">

            {{-- Thumbnail / Pose Preview --}}
            @php $previewItems = $video->posePreviewData(20); @endphp
            <x-pose-preview
                :items="$previewItems"
                :fallback="$video->thumbnail_url"
                class="rounded-xl flex-shrink-0"
                style="width: 88px; height: 58px;" />

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium leading-snug line-clamp-2" style="color: #3D3D3A;">
                    {{ $video->title }}
                </p>
                <div class="flex items-center gap-2 mt-1.5">
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                          style="background-color: #F0EDE8; color: #6B6B67;">
                        {{ $mins }} min
                    </span>
                </div>
            </div>
        </a>
    @empty
        <div class="text-center py-12">
            <p class="text-base mb-1" style="color: #6B6B67;">No videos found.</p>
            <p class="text-sm" style="color: #6B6B67;">Try adjusting the filters.</p>
        </div>
    @endforelse

    {{-- ── Pagination ── --}}
    @if($videos->hasPages())
        <div class="pt-2">{{ $videos->links() }}</div>
    @endif

</div>

@endsection
