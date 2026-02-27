@extends('layouts.app')

@section('title', 'Find My Practice')

@section('content')

<div x-data="momentFinder()" x-init="init()" class="pb-4">

    {{-- ── Header ── --}}
    <div class="px-4 pt-5 pb-2">
        <p class="text-sm mb-1" style="color: #8FAF8F;" x-text="greeting"></p>
        <h1 class="text-2xl font-semibold leading-tight" style="color: #3D3D3A; font-family: 'Lora', serif;">
            What do you need today?
        </h1>
        <p class="text-sm mt-1" style="color: #6B6B67;">Tell me how you're feeling and I'll find the right practice.</p>
    </div>

    {{-- ── Body Check-In ── --}}
    <div class="px-4 mt-4">
        <x-section-title class="mb-1">How's your body feeling?</x-section-title>
        <p class="text-xs mb-3" style="color: #9B9B97;">Tap once = sore (avoid) &nbsp;·&nbsp; Tap again = focus area</p>

        {{-- Presets --}}
        @if($presets->count() > 0)
        <div class="flex gap-2 overflow-x-auto pb-2 mb-3 -mx-1 px-1" style="scrollbar-width: none;">
            @foreach($presets as $preset)
                <button
                    type="button"
                    @click="applyPreset({{ $preset->zones->toJson() }}, '{{ addslashes($preset->name) }}')"
                    class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
                    style="border-color: #C5D5C5; color: #4A6B4A; background-color: #FFFFFF;"
                    :style="activePresetName === '{{ addslashes($preset->name) }}' ? 'background-color: #C5D5C5;' : ''">
                    {{ $preset->name }}
                </button>
            @endforeach
        </div>
        @endif

        {{-- Body zones grid --}}
        <div class="grid grid-cols-2 gap-2">
            @php
            $zones = [
                ['id' => 'lower_back',  'label' => 'Lower back', 'emoji' => '🔵'],
                ['id' => 'upper_back',  'label' => 'Upper back', 'emoji' => '🔵'],
                ['id' => 'shoulders',   'label' => 'Shoulders',  'emoji' => '💪'],
                ['id' => 'neck',        'label' => 'Neck',       'emoji' => '🦢'],
                ['id' => 'hips',        'label' => 'Hips',       'emoji' => '🦵'],
                ['id' => 'hamstrings',  'label' => 'Hamstrings', 'emoji' => '🦵'],
                ['id' => 'hip_flexors', 'label' => 'Hip flexors','emoji' => '🦵'],
                ['id' => 'core',        'label' => 'Core',       'emoji' => '⚡'],
                ['id' => 'chest',       'label' => 'Chest',      'emoji' => '💪'],
                ['id' => 'wrists',      'label' => 'Wrists',     'emoji' => '✋'],
                ['id' => 'calves',      'label' => 'Calves',     'emoji' => '🦵'],
                ['id' => 'glutes',      'label' => 'Glutes',     'emoji' => '🍑'],
            ];
            @endphp

            @foreach($zones as $zone)
            <button
                type="button"
                @click="toggleZone('{{ $zone['id'] }}')"
                class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium text-left transition-all active:scale-95"
                :style="getZoneStyle('{{ $zone['id'] }}')"
                style="min-height: 48px;">
                <span class="text-base leading-none">{{ $zone['emoji'] }}</span>
                <div class="flex-1 min-w-0">
                    <span>{{ $zone['label'] }}</span>
                    <span class="block text-xs font-medium mt-0.5" x-text="getZoneLabel('{{ $zone['id'] }}')" x-show="bodyState['{{ $zone['id'] }}']"></span>
                </div>
            </button>
            @endforeach
        </div>

        {{-- Save preset button --}}
        <div class="mt-3" x-show="hasAnyZone()">
            <button
                type="button"
                @click="savePresetPrompt = !savePresetPrompt"
                class="px-3 py-1.5 rounded-full text-xs font-medium border"
                style="border-color: #C5D5C5; color: #4A6B4A; background-color: #FFFFFF;">
                Save as preset
            </button>
            <div x-show="savePresetPrompt" x-transition class="mt-2 flex gap-2">
                <input
                    type="text"
                    x-model="newPresetName"
                    placeholder="Name (e.g. Bad back day)"
                    class="flex-1 px-3 py-2 rounded-xl text-sm border"
                    style="border-color: #C5D5C5; background-color: #FFFFFF; outline: none;"
                    @keydown.enter="savePreset()"
                />
                <button
                    type="button"
                    @click="savePreset()"
                    class="px-4 py-2 rounded-xl text-sm font-medium"
                    style="background-color: #8FAF8F; color: #FFFFFF;">
                    Save
                </button>
            </div>
        </div>
    </div>

    {{-- ── Energy Slider ── --}}
    <div class="px-4 mt-6">
        <div class="flex items-baseline gap-2 mb-3">
            <x-section-title>Energy level</x-section-title>
            <span class="text-xs font-semibold" style="color: #8FAF8F;" x-text="energyLabel"></span>
        </div>
        <div class="flex gap-2">
            <template x-for="(item, i) in energyLevels" :key="i">
                <button
                    type="button"
                    @click="energy = i + 1"
                    class="flex-1 py-3 rounded-xl text-xs font-medium border transition-all active:scale-95 text-center"
                    :style="energy === (i + 1)
                        ? 'background-color: #8FAF8F; color: #FFFFFF; border-color: #8FAF8F;'
                        : 'background-color: #FFFFFF; color: #6B6B67; border-color: #C5D5C5;'"
                    style="min-height: 52px; line-height: 1.2;"
                    x-text="item.label">
                </button>
            </template>
        </div>
    </div>

    {{-- ── Time Available ── --}}
    <div class="px-4 mt-6">
        <div class="flex items-baseline gap-2 mb-3">
            <x-section-title>Time available</x-section-title>
            <span class="text-xs" style="color: #9B9B97;"
                  x-text="timeRangeLabel"></span>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="range in timeRanges" :key="range.label">
                <button
                    type="button"
                    @click="selectTimeRange(range)"
                    class="px-4 py-2 rounded-full text-sm font-medium border transition-all active:scale-95"
                    :style="isTimeRangeSelected(range) ?
                        'background-color: #8FAF8F; color: #FFFFFF; border-color: #8FAF8F;' :
                        'background-color: #FFFFFF; color: #3D3D3A; border-color: #C5D5C5;'"
                    style="min-height: 44px;"
                    x-text="range.label">
                </button>
            </template>
            <button
                type="button"
                @click="timeMin = null; timeMax = null"
                class="px-4 py-2 rounded-full text-sm font-medium border transition-all active:scale-95"
                :style="timeMin === null && timeMax === null ?
                    'background-color: #8FAF8F; color: #FFFFFF; border-color: #8FAF8F;' :
                    'background-color: #FFFFFF; color: #3D3D3A; border-color: #C5D5C5;'"
                style="min-height: 44px;">
                Any
            </button>
        </div>
    </div>

    {{-- ── Goal Chips ── --}}
    <div class="px-4 mt-6">
        <x-section-title class="mb-3">What's your goal? <span class="text-sm font-normal" style="color: #6B6B67;">(optional)</span></x-section-title>
        <div class="flex flex-wrap gap-2">
            @php
            $goalOptions = [
                ['id' => 'stretch',           'label' => '🧘 Stretch'],
                ['id' => 'strengthen',         'label' => '💪 Strengthen'],
                ['id' => 'relax',              'label' => '😌 Relax'],
                ['id' => 'back_pain_relief',   'label' => '🔵 Back relief'],
                ['id' => 'try_something_new',  'label' => '🆕 Try something new'],
                ['id' => 'challenge_me',       'label' => '🔥 Challenge me'],
                ['id' => 'my_favorites',       'label' => '⭐ My favorites'],
            ];
            @endphp
            @foreach($goalOptions as $goal)
                <button
                    type="button"
                    @click="toggleGoal('{{ $goal['id'] }}')"
                    class="px-4 py-2 rounded-full text-sm font-medium border transition-all active:scale-95"
                    :style="goals.includes('{{ $goal['id'] }}') ?
                        'background-color: #8FAF8F; color: #FFFFFF; border-color: #8FAF8F;' :
                        'background-color: #FFFFFF; color: #3D3D3A; border-color: #C5D5C5;'"
                    style="min-height: 44px;">
                    {{ $goal['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ── CTA Button ── --}}
    <div class="px-4 mt-6">
        <button
            type="button"
            @click="findPractice()"
            :disabled="loading"
            class="w-full py-4 rounded-2xl text-base font-semibold transition-all active:scale-[0.98]"
            :style="`background-color: #D4846A; color: #FFFFFF; min-height: 56px; border: 2px solid #B8664E; box-shadow: 0 4px 12px rgba(180,100,70,0.30); opacity: ${loading ? '0.7' : '1'};`">
            <span x-show="!loading">Find My Practice</span>
            <span x-show="loading">Finding your perfect practice…</span>
        </button>
    </div>

    {{-- ── Results ── --}}
    <div class="px-4 mt-6" x-show="results.length > 0 || searched" x-transition>

        <div class="flex items-center justify-between mb-3">
            <div>
                <x-section-title>
                    <span x-show="results.length > 0">Here's what I found</span>
                    <span x-show="results.length === 0 && searched">No matches found</span>
                </x-section-title>
                <p x-show="results.length > 0" class="text-xs mt-0.5" style="color: #6B6B67;">
                    <span x-text="results.length"></span> practices match your mood
                </p>
            </div>
            <button type="button" @click="clearResults()"
                    class="px-3 py-1.5 rounded-full text-xs font-medium border"
                    style="border-color: #D0CCC8; color: #6B6B67; background-color: #FFFFFF;">Clear</button>
        </div>

        {{-- Shimmer placeholder while loading --}}
        <div x-show="loading" class="space-y-3">
            @for($i = 0; $i < 3; $i++)
            <div class="rounded-2xl overflow-hidden animate-pulse" style="background-color: #FFFFFF; height: 120px;"></div>
            @endfor
        </div>

        {{-- Result cards --}}
        <div x-show="!loading" class="space-y-3">
            <template x-for="result in results" :key="result.video.id">
                <div class="rounded-2xl overflow-hidden" style="background-color: #FFFFFF; box-shadow: 0 2px 8px rgba(61,61,58,0.08);">
                    <div class="flex gap-3 p-3">
                        {{-- Thumbnail / Pose Preview --}}
                        <div
                            class="flex-shrink-0 rounded-xl overflow-hidden"
                            style="width: 88px; height: 60px;"
                            x-data="posePreviewComp(result.video.pose_preview)">
                            <img
                                :src="src || result.video.thumbnail_url"
                                :alt="result.video.title"
                                :class="src ? 'w-full h-full object-contain' : 'w-full h-full object-cover'"
                                :style="(src ? 'background-color: #F4F1EC;' : 'background-color: #E8E5E0;') + ' transition: opacity 0.1s ease; opacity: ' + (vis ? 1 : 0)"
                            />
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold leading-snug line-clamp-2" style="color: #3D3D3A;" x-text="result.video.title"></p>
                                <div class="flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-bold"
                                     style="background-color: #C5D5C5; color: #2D5A2D;"
                                     x-text="result.score + '%'"></div>
                            </div>
                            <p class="text-xs mt-0.5" style="color: #6B6B67;" x-text="formatDuration(result.video.duration_seconds)"></p>
                            {{-- Session history context --}}
                            <p x-show="result.session_count > 0"
                               class="text-xs mt-0.5 font-medium"
                               style="color: #8FAF8F;"
                               x-text="sessionContextLabel(result)"></p>
                        </div>
                    </div>

                    {{-- Chips --}}
                    <div class="px-3 pb-2 flex flex-wrap gap-1.5">
                        <template x-for="chip in result.chips" :key="chip">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: #F0EDE8; color: #6B6B67;"
                                  x-text="chip"></span>
                        </template>
                    </div>

                    {{-- Actions --}}
                    <div class="flex border-t" style="border-color: #F0EDE8;">
                        <a
                            :href="result.video.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex-1 flex items-center justify-center gap-1.5 py-3 text-sm font-semibold transition-colors"
                            style="color: #D4846A;">
                            ▶ Open in YouTube
                        </a>
                        <div style="width: 1px; background-color: #F0EDE8;"></div>
                        <a
                            :href="`/videos/${result.video.id}`"
                            class="flex-1 flex items-center justify-center gap-1.5 py-3 text-sm font-medium transition-colors"
                            style="color: #6B6B67;">
                            Details
                        </a>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="!loading && results.length === 0 && searched" class="text-center py-8">
            <p class="text-sm" style="color: #6B6B67;">No analyzed videos match your current filters.</p>
            <p class="text-xs mt-1" style="color: #6B6B67;">Try adjusting your time or energy selection.</p>
        </div>
    </div>

</div>

@push('scripts')
<script>
function momentFinder() {
    return {
        // State
        bodyState: {},     // { zone_id: 'sore' | 'target' }
        energy: 3,
        timeMin: null,
        timeMax: null,
        timeRanges: [
            { label: '10–20 min', min: 10, max: 20 },
            { label: '20–30 min', min: 20, max: 30 },
            { label: '30–45 min', min: 30, max: 45 },
            { label: '45–60 min', min: 45, max: 60 },
            { label: '60+ min',   min: 60, max: null },
        ],
        goals: [],
        results: [],
        loading: false,
        searched: false,
        savePresetPrompt: false,
        newPresetName: '',
        activePresetName: null,

        get greeting() {
            const h = new Date().getHours();
            if (h < 12) return 'Good morning.';
            if (h < 17) return 'Good afternoon.';
            if (h < 21) return 'Good evening.';
            return 'Good night.';
        },

        get timeRangeLabel() {
            if (this.timeMin === null && this.timeMax === null) return 'Showing all durations';
            if (this.timeMax === null) return this.timeMin + '+ min';
            return this.timeMin + '–' + this.timeMax + ' min';
        },

        selectTimeRange(range) {
            if (this.isTimeRangeSelected(range)) {
                this.timeMin = null; this.timeMax = null;
            } else {
                this.timeMin = range.min; this.timeMax = range.max;
            }
        },

        isTimeRangeSelected(range) {
            return this.timeMin === range.min && this.timeMax === range.max;
        },

        init() {
            // Nothing to load on init
        },

        // Body zone toggling: neutral → sore → target → neutral
        toggleZone(zone) {
            const current = this.bodyState[zone];
            if (!current) {
                this.bodyState[zone] = 'sore';
            } else if (current === 'sore') {
                this.bodyState[zone] = 'target';
            } else {
                delete this.bodyState[zone];
            }
            this.bodyState = { ...this.bodyState };
            this.activePresetName = null;
        },

        getZoneStyle(zone) {
            const mode = this.bodyState[zone];
            if (mode === 'sore')   return 'background-color: #FDDEDE; color: #922B2B; border: 1.5px solid #F5C2C2;';
            if (mode === 'target') return 'background-color: #C5D5C5; color: #2D5A2D; border: 1.5px solid #8FAF8F;';
            return 'background-color: #FFFFFF; color: #3D3D3A; border: 1.5px solid #E8E5E0;';
        },

        getZoneLabel(zone) {
            const mode = this.bodyState[zone];
            if (mode === 'sore')   return 'Sore — avoid';
            if (mode === 'target') return 'Focus area';
            return '';
        },

        hasAnyZone() {
            return Object.keys(this.bodyState).length > 0;
        },

        // Presets
        applyPreset(zones, name) {
            this.bodyState = {};
            zones.forEach(z => {
                this.bodyState[z.zone] = z.mode;
            });
            this.bodyState = { ...this.bodyState };
            this.activePresetName = name;
        },

        async savePreset() {
            if (!this.newPresetName.trim()) return;
            const zones = Object.entries(this.bodyState).map(([zone, mode]) => ({ zone, mode }));
            try {
                await fetch('/api/presets', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ name: this.newPresetName.trim(), zones }),
                });
                this.savePresetPrompt = false;
                this.newPresetName = '';
                // Reload page to show new preset
                location.reload();
            } catch (e) {
                console.error('Failed to save preset', e);
            }
        },

        // Energy
        energyLevels: [
            { label: 'Need rest' },
            { label: 'Low' },
            { label: 'Moderate' },
            { label: 'Good' },
            { label: "Let's go!" },
        ],
        get energyLabel() {
            return this.energyLevels[this.energy - 1]?.label ?? '';
        },

        // Session context label for recommendation cards
        sessionContextLabel(result) {
            const count  = result.session_count;
            const rating = result.last_rating;
            const parts  = [];
            if (count === 1)      parts.push('Done once');
            else if (count > 1)   parts.push(`Done ${count} times`);
            if (rating === 5)     parts.push('You love this one 🤩');
            else if (rating === 4) parts.push('You enjoyed this 😊');
            else if (rating && rating <= 2) parts.push('Didn\'t enjoy it 😕');
            return parts.join(' · ');
        },

        // Goals
        toggleGoal(goal) {
            if (this.goals.includes(goal)) {
                this.goals = this.goals.filter(g => g !== goal);
            } else {
                this.goals.push(goal);
            }
        },

        // Main action
        async findPractice() {
            this.loading = true;
            this.searched = false;

            const bodyState = Object.entries(this.bodyState).map(([zone, mode]) => ({ zone, mode }));

            try {
                const resp = await fetch('/api/recommendations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        body_state:   bodyState,
                        energy_level: this.energy,
                        time_min:     this.timeMin,
                        time_max:     this.timeMax,
                        goals:        this.goals,
                    }),
                });

                const data = await resp.json();
                this.results  = data.results || [];
                this.searched = true;

                // Scroll to results
                this.$nextTick(() => {
                    document.querySelector('[x-show="results.length > 0 || searched"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            } catch (e) {
                console.error('Recommendation request failed', e);
                this.results  = [];
                this.searched = true;
            } finally {
                this.loading = false;
            }
        },

        clearResults() {
            this.results  = [];
            this.searched = false;
        },

        // Helpers
        formatDuration(seconds) {
            if (!seconds) return '';
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return m > 0 ? `${m} min` : `${s}s`;
        },
    };
}
</script>
@endpush

@endsection
