@php
    $backUrl  = url('/videos');
    $pageTitle = null; // use default YogaPro header for video pages
@endphp

@extends('layouts.app')

@section('title', $video->title)

@section('content')

<div x-data="{ activeTab: 'overview' }">

    {{-- ── Hero ── --}}
    <div class="relative">
        <img
            src="{{ $video->thumbnail_url }}"
            alt="{{ $video->title }}"
            class="w-full object-cover"
            style="height: 200px; object-position: center;"
        />
        {{-- Gradient overlay --}}
        <div class="absolute inset-0" style="background: linear-gradient(to top, rgba(250,247,242,0.95) 0%, transparent 60%);"></div>

        {{-- Back button --}}
        <a href="/videos" class="absolute top-4 left-4 flex items-center justify-center rounded-full"
           style="width: 36px; height: 36px; background-color: rgba(255,255,255,0.9);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3D3D3A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </a>
    </div>

    {{-- ── Title + Meta ── --}}
    <div class="px-4 pt-2 pb-3">
        <h1 class="text-xl font-semibold leading-snug" style="color: #3D3D3A; font-family: 'Lora', serif;">
            {{ $video->title }}
        </h1>
        <div class="flex items-center gap-3 mt-2 text-xs" style="color: #6B6B67;">
            @php
                $mins = floor($video->duration_seconds / 60);
                $secs = $video->duration_seconds % 60;
                $dur  = $mins > 0 ? "{$mins} min" . ($secs ? " {$secs}s" : '') : "{$secs}s";
            @endphp
            <span>{{ $dur }}</span>
            @if($video->published_at)
                <span>·</span>
                <span>{{ $video->published_at->format('M Y') }}</span>
            @endif
            @if($video->view_count)
                <span>·</span>
                <span>{{ number_format($video->view_count) }} views</span>
            @endif
        </div>

        {{-- YouTube CTA --}}
        <a href="{{ $video->url }}"
           target="_blank"
           rel="noopener noreferrer"
           class="mt-3 w-full flex items-center justify-center gap-2 py-3 rounded-2xl text-base font-semibold transition-all active:scale-[0.98]"
           style="background-color: #D4846A; color: #FFFFFF; display: flex;">
            ▶ Open in YouTube
        </a>

        {{-- Rate this practice --}}
        <div x-data="videoRater({{ $video->id }}, '{{ addslashes($video->title) }}')" class="mt-2">
            <button
                type="button"
                @click="open = !open"
                class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl text-base font-semibold border transition-all active:scale-[0.98]"
                style="border-color: #8FAF8F; color: #5A8F5A; background-color: #FFFFFF;">
                ★ Rate this practice
            </button>

            {{-- Inline rating form --}}
            <div x-show="open" x-transition class="mt-2 rounded-2xl p-4 space-y-4"
                 style="background-color: #FFFFFF; box-shadow: 0 4px 16px rgba(61,61,58,0.12);">
                <p class="text-sm font-semibold" style="color: #3D3D3A;">Log a session for this video</p>

                {{-- Date --}}
                <div>
                    <label class="text-xs font-medium block mb-1" style="color: #6B6B67;">When did you practice?</label>
                    <input type="datetime-local" x-model="watchedAt"
                           class="w-full px-3 py-2 rounded-xl text-sm border"
                           style="border-color: #E8E5E0; outline: none;" />
                </div>

                {{-- Rating --}}
                <div>
                    <label class="text-xs font-medium block mb-2" style="color: #6B6B67;">How did it feel?</label>
                    <div class="flex gap-2 justify-center">
                        @foreach(['😫', '😕', '😐', '😊', '🤩'] as $i => $emoji)
                            <button type="button"
                                    @click="rating = {{ $i + 1 }}"
                                    class="text-2xl p-2 rounded-xl transition-all active:scale-95"
                                    :style="rating === {{ $i + 1 }} ? 'background-color: #C5D5C5;' : 'background-color: #FAF7F2;'"
                                    style="min-width: 48px; min-height: 48px;">
                                {{ $emoji }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Tags --}}
                <div>
                    <label class="text-xs font-medium block mb-2" style="color: #6B6B67;">Tags (optional)</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['Morning practice', 'Post-work', 'Bad back day', 'High energy', 'Weekend flow', 'Quick fix'] as $tag)
                            <button type="button" @click="toggleTag('{{ $tag }}')"
                                    class="px-3 py-1.5 rounded-full text-xs font-medium border transition-all active:scale-95"
                                    :style="tags.includes('{{ $tag }}') ?
                                        'background-color: #8FAF8F; color: #FFFFFF; border-color: #8FAF8F;' :
                                        'background-color: #FFFFFF; color: #6B6B67; border-color: #E8E5E0;'">
                                {{ $tag }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="text-xs font-medium block mb-1" style="color: #6B6B67;">Notes (optional)</label>
                    <textarea x-model="notes" placeholder="How did you feel?" rows="2"
                              class="w-full px-3 py-2 rounded-xl text-sm border resize-none"
                              style="border-color: #E8E5E0; outline: none;"></textarea>
                </div>

                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer text-sm" style="color: #3D3D3A;">
                        <input type="checkbox" x-model="completed" class="rounded" style="accent-color: #8FAF8F;">
                        Completed full video
                    </label>
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="open = false"
                            class="flex-1 py-2.5 rounded-xl text-sm border"
                            style="border-color: #E8E5E0; color: #6B6B67;">
                        Cancel
                    </button>
                    <button type="button" @click="save()"
                            :disabled="saving || !rating"
                            class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all"
                            style="background-color: #D4846A; color: #FFFFFF;"
                            :style="(saving || !rating) ? 'opacity: 0.5;' : ''">
                        <span x-show="!saving">Save session</span>
                        <span x-show="saving">Saving…</span>
                    </button>
                </div>
                <p x-show="!rating" class="text-xs text-center" style="color: #9B9B97;">Choose a rating to save.</p>
                <p x-show="saved" class="text-xs text-center font-medium" style="color: #8FAF8F;">Session saved!</p>
            </div>
        </div>
    </div>

    {{-- ── Tab Strip ── --}}
    @php $sessionCount = $video->userSessions->count(); @endphp
    <div class="flex border-b sticky" style="border-color: #E8E5E0; background-color: #FAF7F2; top: 56px; z-index: 10;">
        @foreach([
            ['id' => 'overview',  'label' => 'Overview'],
            ['id' => 'timeline',  'label' => 'Timeline'],
            ['id' => 'bodymap',   'label' => 'Body Map'],
            ['id' => 'history',   'label' => 'History'],
        ] as $tab)
        <button
            type="button"
            @click="activeTab = '{{ $tab['id'] }}'"
            class="flex-1 py-3 text-xs font-medium transition-colors border-b-2 relative"
            :style="activeTab === '{{ $tab['id'] }}' ?
                'color: #8FAF8F; border-color: #8FAF8F;' :
                'color: #6B6B67; border-color: transparent;'">
            {{ $tab['label'] }}
            @if($tab['id'] === 'history' && $sessionCount > 0)
                <span class="absolute top-1.5 right-1.5 flex items-center justify-center text-white rounded-full"
                      style="width:14px; height:14px; font-size:9px; background-color: #8FAF8F; line-height:1;">
                    {{ $sessionCount }}
                </span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- ── Overview Tab ── --}}
    <div x-show="activeTab === 'overview'" class="px-4 py-4 space-y-4">

        {{-- Your last session (if any) --}}
        @php $lastSession = $video->userSessions->first(); @endphp
        @if($lastSession)
            @php $ratingEmoji = ['😫','😕','😐','😊','🤩'][($lastSession->overall_rating ?? 1) - 1] ?? '—'; @endphp
            <div class="rounded-2xl p-3 flex items-center gap-3"
                 style="background-color: #F4FAF4; border: 1px solid #C5D5C5;">
                <span class="text-2xl flex-shrink-0">{{ $ratingEmoji }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold" style="color: #5A8F5A;">Your last session</p>
                    <p class="text-xs" style="color: #6B6B67;">
                        {{ $lastSession->watched_at->format('M j, Y') }}
                        @if($lastSession->notes) · {{ Str::limit($lastSession->notes, 40) }}@endif
                    </p>
                </div>
                <button type="button" @click="activeTab = 'history'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium flex-shrink-0 border"
                        style="border-color: #8FAF8F; color: #4A6B4A; background-color: #F4FAF4;">
                    See all →
                </button>
            </div>
        @endif

        @php
            $poseCount       = $video->segments->where('segment_type', 'pose')->count();
            $transitionCount = $video->segments->where('segment_type', 'transition')->count();
            $avgTransition   = $transitionCount > 0
                ? round($video->segments->where('segment_type', 'transition')->avg('duration_seconds'), 1)
                : null;
        @endphp

        <div class="grid grid-cols-3 gap-3">
            @foreach([
                ['label' => 'Poses', 'value' => $poseCount],
                ['label' => 'Transitions', 'value' => $transitionCount],
                ['label' => 'Avg transition', 'value' => $avgTransition ? $avgTransition . 's' : '—'],
            ] as $stat)
            <div class="text-center p-3 rounded-xl" style="background-color: #FFFFFF; box-shadow: 0 1px 4px rgba(61,61,58,0.06);">
                <div class="text-lg font-bold" style="color: #3D3D3A;">{{ $stat['value'] }}</div>
                <div class="text-xs mt-0.5" style="color: #6B6B67;">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>

        @if($avgTransition !== null)
            @php
                $flowLabel = match(true) {
                    $avgTransition < 3  => ['💨 Fast flow',       'red'],
                    $avgTransition < 6  => ['🧘 Moderate pace',   'sage'],
                    default             => ['🌊 Slow, gentle pace','green'],
                };
            @endphp
            <div class="flex gap-2 items-center">
                <x-chip :color="$flowLabel[1]">{{ $flowLabel[0] }}</x-chip>
            </div>
        @endif

        {{-- ── Pose Preview Animation ── --}}
        @php $posePreviewItems = $video->posePreviewData(40); @endphp
        @if(count($posePreviewItems) >= 2)
        <div>
            <x-section-title class="mb-2">Pose sequence</x-section-title>
            <div
                x-data='posePreviewComp(@json($posePreviewItems))'
                class="rounded-2xl overflow-hidden relative"
                style="height: 150px; background-color: #F4F1EC;">
                <img
                    :src="src"
                    :alt="poseName"
                    class="w-full h-full object-contain"
                    :style="'transition: opacity 0.1s ease; opacity: ' + (vis ? 1 : 0)"
                />
                <div class="absolute bottom-0 left-0 right-0 px-3 py-2 text-center"
                     style="background: linear-gradient(to top, rgba(244,241,236,0.9) 0%, transparent 100%);">
                    <p class="text-xs font-medium truncate" style="color: #6B6B67;" x-text="poseName"></p>
                </div>
            </div>
            <p class="text-xs mt-1.5 text-center" style="color: #9B9B97;">
                {{ count($posePreviewItems) }} poses with images · cycling through sequence
            </p>
        </div>
        @endif

        @if($video->description)
            <div>
                <x-section-title class="mb-2">Description</x-section-title>
                <p class="text-sm leading-relaxed line-clamp-4" style="color: #6B6B67;">{{ $video->description }}</p>
            </div>
        @endif
    </div>

    {{-- ── Timeline Tab ── --}}
    <div x-show="activeTab === 'timeline'" class="py-2">
        @forelse($video->segments as $segment)
            @php
                $duration = $segment->duration_seconds ?? 0;
                $mins     = floor($duration / 60);
                $secs     = $duration % 60;
                $durStr   = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
                $startMin = floor($segment->start_time_seconds / 60);
                $startSec = $segment->start_time_seconds % 60;
                $timeStr  = sprintf('%d:%02d', $startMin, $startSec);
            @endphp

            @if($segment->segment_type === 'pose')
                @php
                    $mainMove = $segment->segmentMoves->firstWhere('role', 'main');
                    $move     = $mainMove?->yogaMove;
                    $opinion  = $move ? ($opinions->get($move->id)) : null;
                    $isAvoided  = $opinion?->is_avoided ?? false;
                    $isFavorite = $opinion && $opinion->comfort_level >= 4;
                    $isUnrated  = $move && !$opinion;
                @endphp
                <div class="flex items-center gap-3 px-4 py-3"
                     style="{{ $isAvoided ? 'background-color: #FFF5F5;' : ($isFavorite ? 'background-color: #F4FAF4;' : '') }} border-bottom: 1px solid #F0EDE8;">
                    <span class="text-xs font-mono flex-shrink-0" style="color: #6B6B67; min-width: 36px;">{{ $timeStr }}</span>
                    {{-- Pose thumbnail --}}
                    <div class="flex-shrink-0 rounded-lg overflow-hidden flex items-center justify-center" style="width:40px; height:40px; background-color: #C5D5C5;">
                        @if($move?->image_url)
                            <img src="{{ $move->image_url }}" alt="{{ $move?->name }}" class="w-full h-full object-contain p-0.5" loading="lazy">
                        @else
                            <span class="text-sm font-semibold" style="color: #8FAF8F;">{{ mb_substr($move?->name ?? '?', 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium" style="color: #3D3D3A;">{{ $move?->name ?? 'Unknown pose' }}</span>
                            @if($isAvoided)
                                <x-chip color="red">Avoided</x-chip>
                            @elseif($isFavorite)
                                <x-chip color="green">Favourite</x-chip>
                            @endif
                        </div>
                        @if($move?->sanskrit_name)
                            <span class="text-xs italic" style="color: #6B6B67;">{{ $move->sanskrit_name }}</span>
                        @endif
                    </div>
                    <span class="text-xs flex-shrink-0" style="color: #6B6B67;">{{ $durStr }}</span>
                </div>
            @else
                {{-- Transition --}}
                <div class="flex items-center gap-3 px-4 py-2.5" style="opacity: 0.55; border-bottom: 1px solid #F0EDE8;">
                    <span class="text-xs font-mono flex-shrink-0" style="color: #9B9B97; min-width: 36px;">{{ $timeStr }}</span>
                    <div class="flex-shrink-0" style="width:40px; height:2px; background-color: #D0CCC8; border-radius: 1px;"></div>
                    <span class="text-xs italic flex-1" style="color: #9B9B97;">transition</span>
                    <span class="text-xs flex-shrink-0" style="color: #9B9B97;">{{ $durStr }}</span>
                </div>
            @endif
        @empty
            <div class="px-4 py-8 text-center">
                <p class="text-sm" style="color: #6B6B67;">No timeline data yet — this video hasn't been analyzed.</p>
            </div>
        @endforelse
    </div>

    {{-- ── Body Map Tab ── --}}
    <div x-show="activeTab === 'bodymap'" class="px-4 py-4">
        @if(count($bodyMap) > 0)
            <p class="text-sm mb-4" style="color: #6B6B67;">Where this video focuses your time:</p>
            @foreach($bodyMap as $zone)
                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span style="color: #3D3D3A;">{{ $zone['zone'] }}</span>
                        <span style="color: #6B6B67;">{{ $zone['pct'] }}%</span>
                    </div>
                    <div class="w-full rounded-full overflow-hidden" style="height: 8px; background-color: #E8E5E0;">
                        @php
                            $barColor = match(true) {
                                $zone['pct'] >= 30 => '#8FAF8F',
                                $zone['pct'] >= 15 => '#C5D5C5',
                                default            => '#D4C8C0',
                            };
                        @endphp
                        <div class="h-full rounded-full" style="width: {{ min(100, $zone['pct']) }}%; background-color: {{ $barColor }};"></div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="text-center py-8">
                <p class="text-sm" style="color: #6B6B67;">No body map data — video not analyzed yet.</p>
            </div>
        @endif
    </div>

    {{-- ── History Tab ── --}}
    <div x-show="activeTab === 'history'" class="px-4 py-4">
        @php $sessions = $video->userSessions; @endphp

        @if($sessions->count() > 0)
            <div class="space-y-3 mb-4">
                @foreach($sessions as $session)
                    @php
                        $ratingEmoji = ['😫','😕','😐','😊','🤩'][$session->overall_rating - 1] ?? '—';
                    @endphp
                    <x-card>
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-sm font-medium" style="color: #3D3D3A;">
                                    {{ $session->watched_at->format('M j, Y') }}
                                </div>
                                <div class="text-xs mt-0.5" style="color: #6B6B67;">
                                    {{ $session->watched_at->diffForHumans() }}
                                    @if($session->energy_level)
                                        · Energy {{ $session->energy_level }}/5
                                    @endif
                                </div>
                            </div>
                            @if($session->overall_rating)
                                <span class="text-2xl">{{ $ratingEmoji }}</span>
                            @endif
                        </div>
                        @if($session->notes)
                            <p class="text-sm mt-2 leading-relaxed" style="color: #6B6B67;">{{ $session->notes }}</p>
                        @endif
                        @if(!empty($session->tags))
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach($session->tags as $tag)
                                    <x-chip color="neutral">{{ $tag }}</x-chip>
                                @endforeach
                            </div>
                        @endif
                    </x-card>
                @endforeach
            </div>
        @else
            <div class="text-center py-6">
                <p class="text-sm mb-3" style="color: #6B6B67;">You haven't logged this video yet.</p>
                <p class="text-xs" style="color: #9B9B97;">Use the "Rate this practice" button above to log your first session.</p>
            </div>
        @endif
    </div>

</div>

@endsection

@push('scripts')
<script>
function videoRater(videoId, videoTitle) {
    return {
        open:      false,
        watchedAt: new Date().toISOString().slice(0, 16),
        rating:    null,
        tags:      [],
        notes:     '',
        completed: false,
        saving:    false,
        saved:     false,

        toggleTag(tag) {
            if (this.tags.includes(tag)) {
                this.tags = this.tags.filter(t => t !== tag);
            } else {
                this.tags.push(tag);
            }
        },

        async save() {
            if (!this.rating) return;
            this.saving = true;
            try {
                const resp = await fetch('/api/sessions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        video_id:             videoId,
                        watched_at:           this.watchedAt,
                        overall_rating:       this.rating,
                        completed_full_video: this.completed,
                        notes:                this.notes || null,
                        tags:                 this.tags,
                    }),
                });
                if (resp.ok) {
                    this.saved  = true;
                    this.saving = false;
                    // Reload after short delay to show updated History tab
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (e) {
                console.error('Failed to save session', e);
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
