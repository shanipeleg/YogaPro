<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FAF7F2">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'YogaPro')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" style="background-color: #FAF7F2; color: #3D3D3A; font-family: 'Inter', sans-serif;">

{{-- Mobile-first container: max 430px, centered on wider screens --}}
<div class="mx-auto flex flex-col min-h-screen" style="max-width: 430px; position: relative;">

    {{-- Top header bar --}}
    <header class="sticky top-0 z-20 px-4 py-3 flex items-center gap-3"
            style="background-color: #FFFFFF; box-shadow: 0 1px 3px rgba(61,61,58,0.08);">

        @if($backUrl ?? false)
            <a href="{{ $backUrl }}"
               class="flex items-center justify-center rounded-full text-[#6B6B67] hover:text-[#3D3D3A] transition-colors"
               style="width:36px; height:36px; min-width:36px; min-height:36px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
        @endif

        <div class="flex-1 min-w-0">
            @if($pageTitle ?? false)
                <h1 class="text-base font-semibold truncate" style="color: #3D3D3A;">{{ $pageTitle }}</h1>
                @if($pageSubtitle ?? false)
                    <p class="text-xs truncate" style="color: #6B6B67;">{{ $pageSubtitle }}</p>
                @endif
            @else
                <span class="text-base font-semibold" style="color: #8FAF8F; font-family: 'Lora', serif;">YogaPro</span>
            @endif
        </div>

        @if(isset($headerRight))
            <div class="flex-shrink-0">
                {{ $headerRight }}
            </div>
        @endif
    </header>

    {{-- Page content --}}
    <main class="flex-1 overflow-y-auto pb-20">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    {{-- Sticky bottom navigation bar --}}
    <nav class="fixed bottom-0 z-20 flex items-stretch"
         style="background-color: #FFFFFF; box-shadow: 0 -1px 3px rgba(61,61,58,0.08); left: 50%; transform: translateX(-50%); width: 100%; max-width: 430px;">

        @php
            $currentRoute = request()->route()?->getName() ?? '';
            $navItems = [
                ['route' => 'home',    'label' => 'Find',    'icon' => 'search',  'match' => ['home', 'recommendations.*']],
                ['route' => 'videos',  'label' => 'Videos',  'icon' => 'video',   'match' => ['videos', 'videos.show']],
                ['route' => 'poses',   'label' => 'Poses',   'icon' => 'leaf',    'match' => ['poses', 'poses.*']],
                ['route' => 'history', 'label' => 'History', 'icon' => 'clock',   'match' => ['history', 'sessions.*']],
                ['route' => 'stats',   'label' => 'Stats',   'icon' => 'chart',   'match' => ['stats']],
            ];
        @endphp

        @foreach($navItems as $item)
            @php
                $isActive = collect($item['match'])->contains(fn($m) => request()->routeIs($m));
            @endphp
            <a href="{{ route($item['route']) }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-xs font-medium transition-colors"
               style="{{ $isActive ? 'color: #8FAF8F;' : 'color: #6B6B67;' }}">

                {{-- Icons --}}
                @if($item['icon'] === 'video')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $isActive ? '2.5' : '2' }}" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="15" height="10" rx="2"/><polygon points="17 9 22 7 22 17 17 15"/>
                    </svg>
                @elseif($item['icon'] === 'search')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $isActive ? '2.5' : '2' }}" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                @elseif($item['icon'] === 'leaf')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $isActive ? '2.5' : '2' }}" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/>
                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                    </svg>
                @elseif($item['icon'] === 'clock')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $isActive ? '2.5' : '2' }}" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                @elseif($item['icon'] === 'chart')
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $isActive ? '2.5' : '2' }}" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                @endif

                <span>{{ $item['label'] }}</span>

                @if($isActive)
                    <span class="block rounded-full" style="width:4px; height:4px; background-color: #8FAF8F;"></span>
                @else
                    <span class="block" style="width:4px; height:4px;"></span>
                @endif
            </a>
        @endforeach
    </nav>

</div>

<script>
function posePreviewComp(items) {
    return {
        items: items || [],
        idx: 0,
        vis: true,
        init() {
            if (this.items.length >= 2) {
                const t = setInterval(() => {
                    this.vis = false;
                    setTimeout(() => {
                        this.idx = (this.idx + 1) % this.items.length;
                        this.vis = true;
                    }, 100);
                }, 600);
                return () => clearInterval(t);
            }
        },
        get src() { return this.items[this.idx]?.image_url ?? null; },
        get poseName() { return this.items[this.idx]?.name ?? ''; },
    };
}
</script>
@stack('scripts')
</body>
</html>
