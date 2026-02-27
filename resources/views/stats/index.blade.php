@extends('layouts.app')

@section('title', 'Stats')

@section('content')

<x-page-header title="Stats" subtitle="Library health and usage data." />

<div class="px-4 pb-6 space-y-5">

    {{-- ── Pipeline Status ── --}}
    <x-card>
        <x-section-title class="mb-3">Analysis Pipeline</x-section-title>

        @if($failedVideos > 5)
            <div class="mb-3 px-3 py-2 rounded-xl text-sm font-medium" style="background-color: #FDDEDE; color: #922B2B;">
                ⚠ {{ $failedVideos }} videos have failed analysis — check the admin panel.
            </div>
        @endif

        {{-- Progress bar --}}
        <div class="flex justify-between text-xs mb-1" style="color: #6B6B67;">
            <span>{{ $analyzedVideos }} analyzed</span>
            <span>{{ $analysisPercent }}%</span>
        </div>
        <div class="w-full rounded-full overflow-hidden" style="height: 8px; background-color: #E8E5E0;">
            <div class="h-full rounded-full" style="width: {{ $analysisPercent }}%; background-color: #8FAF8F; transition: width 0.3s;"></div>
        </div>

        <div class="grid grid-cols-3 gap-3 mt-4">
            @foreach([
                ['label' => 'Total', 'value' => $totalVideos, 'color' => '#6B6B67'],
                ['label' => 'Pending', 'value' => $pendingVideos, 'color' => '#92660D'],
                ['label' => 'Failed', 'value' => $failedVideos, 'color' => $failedVideos > 0 ? '#922B2B' : '#6B6B67'],
            ] as $stat)
                <div class="text-center p-2 rounded-xl" style="background-color: #FAF7F2;">
                    <div class="text-lg font-bold" style="color: {{ $stat['color'] }};">{{ $stat['value'] }}</div>
                    <div class="text-xs" style="color: #6B6B67;">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>

        @if($lastAnalyzed)
            <p class="text-xs mt-3" style="color: #6B6B67;">Last analyzed: {{ \Carbon\Carbon::parse($lastAnalyzed)->diffForHumans() }}</p>
        @endif

        @if($queueDepth > 0)
            <p class="text-xs mt-1" style="color: #6B6B67;">Queue depth: <span class="font-medium" style="color: #3D3D3A;">{{ $queueDepth }}</span> jobs</p>
        @endif
    </x-card>

    {{-- ── Yoga Move Knowledge Base ── --}}
    <x-card>
        <x-section-title class="mb-3">Pose Knowledge Base</x-section-title>

        <div class="grid grid-cols-2 gap-3">
            @foreach([
                ['label' => 'Total poses', 'value' => $totalMoves, 'icon' => '🧘'],
                ['label' => 'Enriched', 'value' => $enrichedMoves, 'icon' => '✅'],
                ['label' => 'My favorites', 'value' => $favoriteMoves, 'icon' => '⭐'],
                ['label' => 'I avoid', 'value' => $avoidedMoves, 'icon' => '🚫'],
                ['label' => 'Unrated', 'value' => $unratedMoves, 'icon' => '❓'],
                ['label' => 'Stubs pending', 'value' => $pendingMoves, 'icon' => '⏳'],
            ] as $stat)
                <div class="flex items-center gap-2 p-2 rounded-xl" style="background-color: #FAF7F2;">
                    <span>{{ $stat['icon'] }}</span>
                    <div>
                        <div class="text-sm font-semibold" style="color: #3D3D3A;">{{ $stat['value'] }}</div>
                        <div class="text-xs" style="color: #6B6B67;">{{ $stat['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($unratedMoves > 0)
            <a href="{{ route('poses') }}#rate"
               class="mt-3 flex items-center justify-center gap-2 py-2 px-4 rounded-xl text-sm font-medium transition-colors"
               style="background-color: #C5D5C5; color: #4A6B4A;">
                Rate {{ $unratedMoves }} unrated pose{{ $unratedMoves !== 1 ? 's' : '' }} →
            </a>
        @endif
    </x-card>

    {{-- ── Video Content Stats ── --}}
    @if($analyzedVideos > 0)
    <x-card>
        <x-section-title class="mb-3">Video Content</x-section-title>

        <div class="space-y-2 text-sm">
            @php
                function fmtDuration(?float $secs): string {
                    if ($secs === null) return '—';
                    $m = floor($secs / 60);
                    $s = round($secs % 60);
                    return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
                }
            @endphp
            <div class="flex justify-between py-1" style="border-bottom: 1px solid #F0EDE8;">
                <span style="color: #6B6B67;">Avg segments per video</span>
                <span class="font-medium" style="color: #3D3D3A;">{{ $avgSegmentsPerVideo ? round($avgSegmentsPerVideo, 1) : '—' }}</span>
            </div>
            <div class="flex justify-between py-1" style="border-bottom: 1px solid #F0EDE8;">
                <span style="color: #6B6B67;">Avg pose hold time</span>
                <span class="font-medium" style="color: #3D3D3A;">{{ fmtDuration($avgPoseHold) }}</span>
            </div>
            <div class="flex justify-between py-1" style="border-bottom: 1px solid #F0EDE8;">
                <span style="color: #6B6B67;">Avg transition time</span>
                <span class="font-medium" style="color: #3D3D3A;">{{ fmtDuration($avgTransitionTime) }}</span>
            </div>
            <div class="flex justify-between py-1" style="border-bottom: 1px solid #F0EDE8;">
                <span style="color: #6B6B67;">Shortest video</span>
                <span class="font-medium" style="color: #3D3D3A;">{{ fmtDuration($shortestVideo) }}</span>
            </div>
            <div class="flex justify-between py-1">
                <span style="color: #6B6B67;">Longest video</span>
                <span class="font-medium" style="color: #3D3D3A;">{{ fmtDuration($longestVideo) }}</span>
            </div>
        </div>

        @if($topPoses->count() > 0)
            <x-section-title class="mt-4 mb-2 text-base">Top poses</x-section-title>
            <div class="space-y-1">
                @foreach($topPoses as $i => $pose)
                    <div class="flex items-center gap-2 py-1">
                        <span class="text-xs w-5 text-center font-medium" style="color: #6B6B67;">{{ $i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm truncate block" style="color: #3D3D3A;">{{ $pose->name }}</span>
                            @if($pose->sanskrit_name)
                                <span class="text-xs" style="color: #6B6B67;">{{ $pose->sanskrit_name }}</span>
                            @endif
                        </div>
                        <x-chip color="sage">{{ $pose->appearance_count }}×</x-chip>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
    @endif

    {{-- ── Back Pain Safety ── --}}
    @if($riskPoses->count() > 0)
    <x-card>
        <x-section-title class="mb-1">Back Pain Safety</x-section-title>
        <p class="text-xs mb-3" style="color: #6B6B67;">Poses classified as "avoid" for lower back or with spinal compression.</p>

        <div class="space-y-2">
            @foreach($riskPoses as $pose)
                <div class="flex items-start gap-3 py-2" style="border-bottom: 1px solid #F0EDE8;">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium" style="color: #3D3D3A;">{{ $pose->name }}</div>
                        <div class="flex gap-1 mt-1 flex-wrap">
                            @if($pose->benefit_back_pain_lower === 'avoid')
                                <x-chip color="red" size="sm">Avoid lower back</x-chip>
                            @endif
                            @if($pose->spinal_compression)
                                <x-chip color="amber" size="sm">Spinal compression</x-chip>
                            @endif
                            @if($pose->personally_avoided)
                                <x-chip color="red" size="sm">🚫 You avoid</x-chip>
                            @endif
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="text-sm font-semibold" style="color: #3D3D3A;">{{ $pose->video_count }}</div>
                        <div class="text-xs" style="color: #6B6B67;">video{{ $pose->video_count !== 1 ? 's' : '' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
    @endif

    {{-- ── Gemini Cost ── --}}
    <x-card>
        <x-section-title class="mb-3">API Usage</x-section-title>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span style="color: #6B6B67;">Total tokens used</span>
                <span class="font-medium" style="color: #3D3D3A;">{{ number_format($totalTokens) }}</span>
            </div>
            <div class="flex justify-between">
                <span style="color: #6B6B67;">Estimated cost</span>
                <span class="font-medium" style="color: #3D3D3A;">${{ $estimatedCostDollars }}</span>
            </div>
        </div>
    </x-card>

</div>

@endsection
