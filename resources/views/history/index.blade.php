@extends('layouts.app')

@section('title', 'Practice History')

@section('content')

<x-page-header title="Practice History" subtitle="Your yoga sessions over time." />

<div class="px-4 space-y-4 pb-8">

    {{-- ── Summary bar ── --}}
    @if($totalSessions > 0)
    <x-card>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div>
                <div class="text-lg font-bold" style="color: #3D3D3A;">{{ $totalSessions }}</div>
                <div class="text-xs" style="color: #6B6B67;">Total sessions</div>
            </div>
            <div>
                <div class="text-lg font-bold" style="color: #3D3D3A;">
                    @if($avgRating)
                        {{ ['😫','😕','😐','😊','🤩'][round($avgRating) - 1] ?? '—' }}
                    @else
                        —
                    @endif
                </div>
                <div class="text-xs" style="color: #6B6B67;">Avg rating</div>
            </div>
            <div>
                <div class="text-xs font-medium truncate" style="color: #3D3D3A;">
                    {{ $topVideo ? Str::limit($topVideo->title, 20) : '—' }}
                </div>
                <div class="text-xs" style="color: #6B6B67;">Most practiced</div>
            </div>
        </div>
    </x-card>
    @endif

    {{-- ── Insights ── --}}
    @if($lovedPoses->count() > 0)
    <x-card>
        <x-section-title class="mb-2">Your top poses</x-section-title>
        <div class="flex flex-wrap gap-2">
            @foreach($lovedPoses as $liked)
                @if($liked->yogaMove)
                    <x-chip color="green">⭐ {{ $liked->yogaMove->name }}</x-chip>
                @endif
            @endforeach
        </div>
    </x-card>
    @endif

    {{-- ── Log new session button ── --}}
    <div x-data="sessionLogger()" class="space-y-4">
        <button
            type="button"
            @click="open = !open"
            class="w-full py-3 rounded-2xl text-sm font-semibold transition-all active:scale-[0.98]"
            style="background-color: #8FAF8F; color: #FFFFFF; min-height: 48px;">
            + Log a session
        </button>

        {{-- ── Session Logger Modal ── --}}
        <div x-show="open" x-transition class="rounded-2xl p-4 space-y-4" style="background-color: #FFFFFF; box-shadow: 0 4px 16px rgba(61,61,58,0.12);">
            <x-section-title>Log a session</x-section-title>

            {{-- Step 1: Video --}}
            <div>
                <label class="text-xs font-medium block mb-1" style="color: #6B6B67;">Which video did you practice?</label>
                <input
                    type="text"
                    x-model="videoSearch"
                    @input.debounce.400ms="searchVideos()"
                    placeholder="Search for a video…"
                    class="w-full px-3 py-2 rounded-xl text-sm border"
                    style="border-color: #E8E5E0; outline: none;"
                />
                <div x-show="videoResults.length > 0" class="mt-1 rounded-xl overflow-hidden border" style="border-color: #E8E5E0;">
                    <template x-for="v in videoResults.slice(0, 5)" :key="v.id">
                        <button
                            type="button"
                            @click="selectVideo(v)"
                            class="w-full text-left px-3 py-2 text-sm transition-colors hover:bg-gray-50"
                            style="color: #3D3D3A; border-bottom: 1px solid #F0EDE8;"
                            x-text="v.title"></button>
                    </template>
                </div>
                <p x-show="selectedVideo" class="text-xs mt-1 font-medium" style="color: #8FAF8F;">
                    ✓ <span x-text="selectedVideo?.title"></span>
                </p>
            </div>

            {{-- Step 2: Date --}}
            <div>
                <label class="text-xs font-medium block mb-1" style="color: #6B6B67;">When did you practice?</label>
                <input
                    type="datetime-local"
                    x-model="watchedAt"
                    class="w-full px-3 py-2 rounded-xl text-sm border"
                    style="border-color: #E8E5E0; outline: none;"
                />
            </div>

            {{-- Step 3: Rating --}}
            <div>
                <label class="text-xs font-medium block mb-2" style="color: #6B6B67;">How did it feel?</label>
                <div class="flex gap-2 justify-center">
                    @foreach(['😫', '😕', '😐', '😊', '🤩'] as $i => $emoji)
                        <button
                            type="button"
                            @click="rating = {{ $i + 1 }}"
                            class="text-2xl p-2 rounded-xl transition-all active:scale-95"
                            :style="rating === {{ $i + 1 }} ? 'background-color: #C5D5C5;' : 'background-color: #FAF7F2;'"
                            style="min-width: 48px; min-height: 48px;">
                            {{ $emoji }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Step 4: Tags --}}
            <div>
                <label class="text-xs font-medium block mb-2" style="color: #6B6B67;">Tags (optional)</label>
                <div class="flex flex-wrap gap-2">
                    @foreach(['Morning practice', 'Post-work', 'Bad back day', 'High energy', 'Weekend flow', 'Quick fix'] as $tag)
                        <button
                            type="button"
                            @click="toggleTag('{{ $tag }}')"
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
                <textarea
                    x-model="notes"
                    placeholder="How did you feel? Anything stand out?"
                    rows="3"
                    class="w-full px-3 py-2 rounded-xl text-sm border resize-none"
                    style="border-color: #E8E5E0; outline: none;"></textarea>
            </div>

            {{-- Finished --}}
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer text-sm" style="color: #3D3D3A;">
                    <input type="checkbox" x-model="completed" class="rounded" style="accent-color: #8FAF8F;">
                    Completed full video
                </label>
            </div>

            <div class="flex gap-3">
                <button
                    type="button"
                    @click="open = false"
                    class="flex-1 py-2.5 rounded-xl text-sm border"
                    style="border-color: #E8E5E0; color: #6B6B67;">
                    Cancel
                </button>
                <button
                    type="button"
                    @click="saveSession()"
                    :disabled="saving || !selectedVideo || !rating"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all"
                    style="background-color: #D4846A; color: #FFFFFF;"
                    :style="(saving || !selectedVideo || !rating) ? 'opacity: 0.5;' : ''">
                    <span x-show="!saving">Save session</span>
                    <span x-show="saving">Saving…</span>
                </button>
            </div>
            <p x-show="!selectedVideo || !rating" class="text-xs text-center" style="color: #9B9B97;">
                <span x-show="!selectedVideo">Pick a video above to save your session.</span>
                <span x-show="selectedVideo && !rating">Choose a rating to save your session.</span>
            </p>
        </div>
    </div>

    {{-- ── Session List ── --}}
    @forelse($sessions as $session)
        @php
            $ratingEmoji = $session->overall_rating
                ? ['😫','😕','😐','😊','🤩'][$session->overall_rating - 1]
                : null;
        @endphp
        <x-card>
            <div class="flex items-start gap-3">
                @if($session->video?->thumbnail_url)
                    <img src="{{ $session->video->thumbnail_url }}" alt=""
                         class="rounded-xl object-cover flex-shrink-0"
                         style="width: 72px; height: 48px;" loading="lazy"/>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium leading-snug line-clamp-2" style="color: #3D3D3A;">
                        {{ $session->video?->title ?? 'Unknown video' }}
                    </p>
                    <p class="text-xs mt-0.5" style="color: #6B6B67;">
                        {{ $session->watched_at->format('M j, Y') }}
                        @if($session->energy_level)
                            · Energy {{ $session->energy_level }}/5
                        @endif
                    </p>
                </div>
                @if($ratingEmoji)
                    <span class="text-2xl flex-shrink-0">{{ $ratingEmoji }}</span>
                @endif
            </div>

            @if(!empty($session->tags))
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach($session->tags as $tag)
                        <x-chip color="neutral">{{ $tag }}</x-chip>
                    @endforeach
                </div>
            @endif

            @if($session->notes)
                <p class="text-sm mt-2 leading-relaxed" style="color: #6B6B67;">{{ Str::limit($session->notes, 120) }}</p>
            @endif
        </x-card>
    @empty
        <div class="text-center py-8">
            <p class="text-base mb-2" style="color: #6B6B67;">No sessions logged yet.</p>
            <p class="text-sm" style="color: #6B6B67;">After a yoga session, tap "Log a session" above to track how it felt.</p>
        </div>
    @endforelse

    {{-- Pagination --}}
    @if($sessions->hasPages())
        <div>{{ $sessions->links() }}</div>
    @endif

</div>

@push('scripts')
<script>
function sessionLogger() {
    return {
        open:          false,
        videoSearch:   '',
        videoResults:  [],
        selectedVideo: null,
        watchedAt:     new Date().toISOString().slice(0, 16),
        rating:        null,
        tags:          [],
        notes:         '',
        completed:     false,
        saving:        false,

        async searchVideos() {
            if (this.videoSearch.length < 2) { this.videoResults = []; return; }
            const resp = await fetch(`/api/videos/search?q=${encodeURIComponent(this.videoSearch)}`);
            if (resp.ok) this.videoResults = (await resp.json()).results ?? [];
        },

        selectVideo(v) {
            this.selectedVideo = v;
            this.videoSearch   = v.title;
            this.videoResults  = [];
        },

        toggleTag(tag) {
            if (this.tags.includes(tag)) {
                this.tags = this.tags.filter(t => t !== tag);
            } else {
                this.tags.push(tag);
            }
        },

        async saveSession() {
            if (!this.selectedVideo || !this.rating) return;
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
                        video_id:              this.selectedVideo.id,
                        watched_at:            this.watchedAt,
                        overall_rating:        this.rating,
                        completed_full_video:  this.completed,
                        notes:                 this.notes || null,
                        tags:                  this.tags,
                    }),
                });
                if (resp.ok) {
                    location.reload();
                }
            } catch (e) {
                console.error('Failed to save session', e);
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush

@endsection
