@php $backUrl = route('poses'); @endphp

@extends('layouts.app')

@section('title', $move->name)

@section('content')

<div x-data="poseDetail({{ json_encode([
    'moveId'    => $move->id,
    'comfort'   => $move->userOpinion?->comfort_level,
    'difficulty'=> $move->userOpinion?->personal_difficulty ?? $move->difficulty_base,
    'isAvoided' => $move->userOpinion?->is_avoided ?? false,
    'avoidReason' => $move->userOpinion?->avoid_reason ?? '',
    'notes'     => $move->userOpinion?->personal_notes ?? '',
]) }})">

    {{-- ── Header ── --}}
    <div class="px-4 pt-5 pb-3">
        <div class="flex items-start gap-2">
            <a href="{{ route('poses') }}" class="flex items-center justify-center rounded-full flex-shrink-0 mt-0.5" style="width:32px; height:32px; background-color: #F0EDE8;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3D3D3A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold leading-tight" style="color: #3D3D3A; font-family: 'Lora', serif;">{{ $move->name }}</h1>
                @if($move->sanskrit_name)
                    <p class="text-sm italic mt-0.5" style="color: #6B6B67;">{{ $move->sanskrit_name }}</p>
                @endif
            </div>
        </div>

        {{-- Chips row --}}
        <div class="flex flex-wrap gap-2 mt-3">
            @if($move->category)
                <x-chip color="sage">{{ ucfirst($move->category) }}</x-chip>
            @endif
            @if($move->difficulty_base)
                <x-chip color="neutral">Difficulty {{ $move->difficulty_base }}/10</x-chip>
            @endif
            @if($move->is_inversion)
                <x-chip color="amber">🙃 Inversion</x-chip>
            @endif

            {{-- Back pain --}}
            @php
                $bpColors = ['helps' => 'green', 'neutral' => 'neutral', 'avoid' => 'red'];
                $bpLabels = ['helps' => '✓ Helps lower back', 'neutral' => 'Neutral for back', 'avoid' => '✗ Avoid with back pain'];
            @endphp
            @if($move->benefit_back_pain_lower)
                <x-chip :color="$bpColors[$move->benefit_back_pain_lower]">{{ $bpLabels[$move->benefit_back_pain_lower] }}</x-chip>
            @endif
        </div>
    </div>

    {{-- ── Pose Image ── --}}
    @if($move->image_url)
        <div class="px-4 pb-1">
            <div class="rounded-2xl overflow-hidden flex items-center justify-center" style="background-color: #C5D5C5; min-height: 200px;">
                <img src="{{ $move->image_url }}" alt="{{ $move->name }}" class="w-full max-h-64 object-contain p-4">
            </div>
        </div>
    @endif

    <div class="px-4 space-y-5 pb-8">

        {{-- ── Description ── --}}
        @if($move->description)
        <x-card>
            <p class="text-sm leading-relaxed" style="color: #6B6B67;">{{ $move->description }}</p>
        </x-card>
        @endif

        {{-- ── Body Areas ── --}}
        @php
            $bodyAreas = collect([
                'Lower back'  => $move->targets_lower_back,
                'Upper back'  => $move->targets_upper_back,
                'Mid back'    => $move->targets_mid_back,
                'Pelvis'      => $move->targets_pelvis,
                'Hips'        => $move->targets_hips,
                'Hamstrings'  => $move->targets_hamstrings,
                'Hip flexors' => $move->targets_hip_flexors,
                'Glutes'      => $move->targets_glutes,
                'Core'        => $move->targets_core,
                'Shoulders'   => $move->targets_shoulders,
                'Neck'        => $move->targets_neck,
                'Chest'       => $move->targets_chest,
                'Quads'       => $move->targets_quads,
                'Calves'      => $move->targets_calves,
                'Ankles'      => $move->targets_ankles,
                'Wrists'      => $move->targets_wrists,
            ])->filter()->keys();
        @endphp
        @if($bodyAreas->count() > 0)
        <x-card>
            <x-section-title class="mb-2">Body areas targeted</x-section-title>
            <div class="flex flex-wrap gap-1.5">
                @foreach($bodyAreas as $area)
                    <x-chip color="sage">{{ $area }}</x-chip>
                @endforeach
            </div>
        </x-card>
        @endif

        {{-- ── Spinal Actions ── --}}
        @php
            $spinal = collect([
                'Compression' => $move->spinal_compression,
                'Flexion (forward bend)' => $move->spinal_flexion,
                'Extension (backbend)' => $move->spinal_extension,
                'Rotation (twist)' => $move->spinal_rotation,
            ])->filter()->keys();
        @endphp
        @if($spinal->count() > 0)
        <x-card>
            <x-section-title class="mb-2">Spinal actions</x-section-title>
            <div class="flex flex-wrap gap-1.5">
                @foreach($spinal as $action)
                    <x-chip color="amber">{{ $action }}</x-chip>
                @endforeach
            </div>
        </x-card>
        @endif

        {{-- ── Contraindications ── --}}
        @if(!empty($move->contraindications))
        <x-card>
            <x-section-title class="mb-2">Contraindications</x-section-title>
            <ul class="space-y-1">
                @foreach($move->contraindications as $item)
                    <li class="text-sm flex items-start gap-2" style="color: #6B6B67;">
                        <span class="flex-shrink-0 mt-0.5">⚠</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
        @endif

        {{-- ── Modifications ── --}}
        @if($move->modifications_available && $move->modifications_description)
        <x-card>
            <x-section-title class="mb-2">Modifications</x-section-title>
            <p class="text-sm leading-relaxed" style="color: #6B6B67;">{{ $move->modifications_description }}</p>
        </x-card>
        @endif

        {{-- ── Personal Opinion ── --}}
        <x-card>
            <x-section-title class="mb-4">My opinion</x-section-title>

            {{-- Comfort stars --}}
            <div class="mb-4">
                <label class="text-xs font-medium block mb-2" style="color: #6B6B67;">How comfortable am I with this pose?</label>
                <div class="flex gap-3 justify-center">
                    @foreach([1 => '😫 Painful', 2 => '😕 Hard', 3 => '😐 Neutral', 4 => '😊 Enjoy', 5 => '🤩 Love'] as $val => $emoji)
                        <button
                            type="button"
                            @click="comfort = comfort === {{ $val }} ? null : {{ $val }}"
                            class="flex flex-col items-center gap-1 p-2 rounded-xl transition-all active:scale-95"
                            :style="comfort === {{ $val }} ? 'background-color: #C5D5C5;' : 'background-color: #FAF7F2;'"
                            style="min-width: 52px;">
                            <span class="text-xl">{{ explode(' ', $emoji)[0] }}</span>
                            <span class="text-xs" style="color: #6B6B67;">{{ explode(' ', $emoji)[1] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Personal difficulty --}}
            <div class="mb-4">
                <label class="text-xs font-medium block mb-1" style="color: #6B6B67;">
                    My personal difficulty: <span x-text="difficulty" class="font-bold" style="color: #3D3D3A;"></span>/10
                </label>
                <input
                    type="range"
                    x-model.number="difficulty"
                    min="1" max="10" step="1"
                    class="w-full h-2 rounded-full appearance-none cursor-pointer"
                    style="accent-color: #8FAF8F;"
                />
                <div class="flex justify-between text-xs mt-0.5" style="color: #6B6B67;">
                    <span>Easy</span>
                    <span>Hard</span>
                </div>
            </div>

            {{-- Avoid toggle --}}
            <div class="mb-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <div
                        @click="isAvoided = !isAvoided"
                        class="relative flex-shrink-0 transition-colors rounded-full"
                        :style="isAvoided ? 'background-color: #D4846A;' : 'background-color: #E8E5E0;'"
                        style="width: 44px; height: 24px; cursor: pointer;">
                        <div
                            class="absolute top-0.5 rounded-full transition-transform"
                            :style="isAvoided ? 'transform: translateX(22px);' : 'transform: translateX(2px);'"
                            style="width: 20px; height: 20px; background-color: white;"></div>
                    </div>
                    <span class="text-sm" style="color: #3D3D3A;">Avoid this pose</span>
                </label>
                <div x-show="isAvoided" x-transition class="mt-2">
                    <input
                        type="text"
                        x-model="avoidReason"
                        placeholder="Why? (e.g. triggers lower back)"
                        class="w-full px-3 py-2 rounded-xl text-sm border"
                        style="border-color: #E8E5E0; outline: none;"
                    />
                </div>
            </div>

            {{-- Personal notes --}}
            <div class="mb-4">
                <label class="text-xs font-medium block mb-1" style="color: #6B6B67;">Personal notes</label>
                <textarea
                    x-model="notes"
                    placeholder="e.g. Works best with a block, need to warm up first…"
                    rows="3"
                    class="w-full px-3 py-2 rounded-xl text-sm border resize-none"
                    style="border-color: #E8E5E0; outline: none;"></textarea>
            </div>

            {{-- Save button --}}
            <button
                type="button"
                @click="saveOpinion()"
                :disabled="saving"
                class="w-full py-3 rounded-xl text-sm font-semibold transition-all active:scale-[0.98]"
                style="background-color: #8FAF8F; color: #FFFFFF; min-height: 48px;"
                :style="saving ? 'opacity: 0.7;' : ''">
                <span x-show="!saving && !saved">Save my opinion</span>
                <span x-show="saving">Saving…</span>
                <span x-show="saved">✓ Saved!</span>
            </button>
        </x-card>

        {{-- ── Appears In ── --}}
        @if($appearsIn->count() > 0)
        <div>
            <x-section-title class="mb-3">Appears in {{ $appearsIn->count() }} video{{ $appearsIn->count() !== 1 ? 's' : '' }}</x-section-title>
            <div class="space-y-2">
                @foreach($appearsIn as $video)
                    @php
                        $vmins = floor($video->duration_seconds / 60);
                        $vsecs = $video->duration_seconds % 60;
                        $vdur  = $vmins > 0 ? "{$vmins} min" : "{$vsecs}s";
                    @endphp
                    <a href="{{ route('videos.show', $video) }}"
                       class="flex items-center gap-3 p-3 rounded-2xl transition-all active:scale-[0.98]"
                       style="background-color: #FFFFFF; box-shadow: 0 1px 4px rgba(61,61,58,0.06);">
                        @if($video->thumbnail_url)
                            <img src="{{ $video->thumbnail_url }}" alt="" class="rounded-xl object-cover flex-shrink-0"
                                 style="width: 64px; height: 44px;" loading="lazy"/>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate" style="color: #3D3D3A;">{{ $video->title }}</p>
                            <p class="text-xs" style="color: #6B6B67;">{{ $vdur }}</p>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C5C5C0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
function poseDetail(initial) {
    return {
        comfort:     initial.comfort,
        difficulty:  initial.difficulty ?? 5,
        isAvoided:   initial.isAvoided,
        avoidReason: initial.avoidReason,
        notes:       initial.notes,
        saving: false,
        saved:  false,

        async saveOpinion() {
            this.saving = true;
            this.saved  = false;
            try {
                await fetch('/api/moves/{{ $move->id }}/opinion', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        comfort_level:        this.comfort,
                        personal_difficulty:  this.difficulty,
                        is_avoided:           this.isAvoided,
                        avoid_reason:         this.avoidReason || null,
                        personal_notes:       this.notes || null,
                    }),
                });
                this.saved = true;
                setTimeout(() => this.saved = false, 2000);
            } catch (e) {
                console.error('Failed to save opinion', e);
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush

@endsection
