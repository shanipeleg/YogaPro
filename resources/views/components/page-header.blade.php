@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'px-4 pt-5 pb-3']) }}>
    <h1 class="text-2xl font-semibold leading-tight" style="color: #3D3D3A; font-family: 'Lora', serif;">
        {{ $title }}
    </h1>
    @if($subtitle)
        <p class="mt-1 text-sm" style="color: #6B6B67;">{{ $subtitle }}</p>
    @endif
</div>
