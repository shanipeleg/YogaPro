@extends('layouts.app')

@section('title', 'Pose Library')

@section('content')

<div x-data="{ showFilters: false }">

    {{-- ── Header ── --}}
    <div class="px-4 pt-5 pb-3">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold" style="color: #3D3D3A; font-family: 'Lora', serif;">Pose Library</h1>
            @if($unratedCount > 0)
                <a href="{{ route('poses.rate') }}" class="flex items-center gap-1">
                    <x-chip color="terra">{{ $unratedCount }} unrated</x-chip>
                </a>
            @endif
        </div>
    </div>

    {{-- ── Search + Filters ── --}}
    <div class="px-4 pb-3">
        <form method="GET" action="{{ route('poses') }}" id="filter-form">
            {{-- Search bar --}}
            <div class="flex gap-2 mb-3">
                <div class="flex-1 flex items-center gap-2 px-3 py-2 rounded-xl" style="background-color: #FFFFFF; border: 1.5px solid #E8E5E0;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B6B67" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search poses…"
                        class="flex-1 bg-transparent text-sm outline-none"
                        style="color: #3D3D3A;"
                    />
                </div>
                <button type="button" @click="showFilters = !showFilters"
                        class="px-3 py-2 rounded-xl text-sm font-medium"
                        :style="showFilters ? 'background-color: #8FAF8F; color: #FFFFFF;' : 'background-color: #FFFFFF; color: #6B6B67; border: 1.5px solid #E8E5E0;'">
                    Filter
                </button>
            </div>

            {{-- Expanded filters --}}
            <div x-show="showFilters" x-transition class="space-y-3 mb-3">
                <div>
                    <label class="text-xs font-medium block mb-1.5" style="color: #6B6B67;">Category</label>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ rtrim(url()->current() . '?' . http_build_query(request()->except('category')), '?') }}" class="px-3 py-2.5 rounded-full text-xs font-medium {{ !request('category') ? 'text-white' : 'text-[#6B6B67]' }}"
                           style="{{ !request('category') ? 'background-color: #8FAF8F;' : 'background-color: #FFFFFF; border: 1px solid #E8E5E0;' }}">All</a>
                        @foreach($categories as $cat)
                            <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->query(), ['category' => $cat])) }}"
                               class="px-3 py-2.5 rounded-full text-xs font-medium {{ request('category') === $cat ? 'text-white' : '' }}"
                               style="{{ request('category') === $cat ? 'background-color: #8FAF8F;' : 'background-color: #FFFFFF; border: 1px solid #E8E5E0; color: #6B6B67;' }}">
                                {{ ucfirst($cat) }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium block mb-1.5" style="color: #6B6B67;">My ratings</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['' => 'All', 'favorites' => '⭐ Favorites', 'avoided' => '🚫 Avoided', 'unrated' => '❓ Unrated'] as $val => $label)
                            <a href="{{ rtrim(url()->current() . '?' . http_build_query($val ? array_merge(request()->query(), ['rating' => $val]) : request()->except('rating')), '?') }}"
                               class="px-3 py-2.5 rounded-full text-xs font-medium {{ request('rating', '') === $val ? 'text-white' : '' }}"
                               style="{{ request('rating', '') === $val ? 'background-color: #8FAF8F;' : 'background-color: #FFFFFF; border: 1px solid #E8E5E0; color: #6B6B67;' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium block mb-1.5" style="color: #6B6B67;">Sort by</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['name' => 'Name A–Z', 'comfort_desc' => 'Comfort ↓', 'difficulty_asc' => 'Easiest', 'difficulty_desc' => 'Hardest'] as $val => $label)
                            <a href="{{ url()->current() . '?' . http_build_query(array_merge(request()->query(), ['sort' => $val])) }}"
                               class="px-3 py-2.5 rounded-full text-xs font-medium {{ request('sort', 'name') === $val ? 'text-white' : '' }}"
                               style="{{ request('sort', 'name') === $val ? 'background-color: #8FAF8F;' : 'background-color: #FFFFFF; border: 1px solid #E8E5E0; color: #6B6B67;' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>

        <p class="text-xs" style="color: #6B6B67;">{{ number_format($moves->total()) }} poses</p>
    </div>

    {{-- ── Pose List ── --}}
    <div class="divide-y" style="border-color: #F0EDE8;">
        @forelse($moves as $move)
            @php
                $opinion    = $move->userOpinion;
                $isAvoided  = $opinion?->is_avoided ?? false;
                $isFavorite = $opinion && $opinion->comfort_level >= 4;
                $comfort    = $opinion?->comfort_level;

                $backPainColor = match($move->benefit_back_pain_lower) {
                    'helps'   => ['chip' => 'green',   'label' => '✓ Helps back'],
                    'avoid'   => ['chip' => 'red',     'label' => '✗ Avoid'],
                    default   => null,
                };
            @endphp
            <a href="{{ route('poses.show', $move) }}"
               class="flex items-center gap-3 px-4 py-3 transition-colors active:bg-gray-50">
                {{-- Pose thumbnail --}}
                <div class="flex-shrink-0 w-12 h-12 rounded-xl overflow-hidden flex items-center justify-center" style="background-color: #C5D5C5;">
                    @if($move->image_url)
                        <img src="{{ $move->image_url }}" alt="{{ $move->name }}" class="w-full h-full object-contain p-1" loading="lazy">
                    @else
                        <span class="text-lg font-semibold" style="color: #8FAF8F;">{{ mb_substr($move->name, 0, 1) }}</span>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium" style="color: #3D3D3A;">{{ $move->name }}</span>
                        @if($isAvoided)
                            <x-chip color="red">🚫</x-chip>
                        @elseif($isFavorite)
                            <x-chip color="green">⭐</x-chip>
                        @endif
                        @if($backPainColor)
                            <x-chip :color="$backPainColor['chip']">{{ $backPainColor['label'] }}</x-chip>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        @if($move->sanskrit_name)
                            <span class="text-xs italic" style="color: #6B6B67;">{{ $move->sanskrit_name }}</span>
                        @endif
                        @if($move->category)
                            <x-chip color="neutral">{{ ucfirst($move->category) }}</x-chip>
                        @endif
                    </div>
                </div>

                {{-- Comfort stars --}}
                <div class="flex-shrink-0 text-right">
                    @if($comfort !== null)
                        <div class="flex gap-0.5 justify-end">
                            @for($i = 1; $i <= 5; $i++)
                                <span class="text-xs" style="color: {{ $i <= $comfort ? '#D4846A' : '#E8E5E0' }};">★</span>
                            @endfor
                        </div>
                    @else
                        <span class="text-xs" style="color: #C5C5C0;">unrated</span>
                    @endif
                    <svg class="ml-auto mt-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C5C5C0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </div>
            </a>
        @empty
            <div class="px-4 py-8 text-center">
                <p class="text-sm" style="color: #6B6B67;">No poses found. Try adjusting your search.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($moves->hasPages())
        <div class="px-4 py-4">
            {{ $moves->links() }}
        </div>
    @endif

</div>

@endsection
