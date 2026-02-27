@props([
    'color' => 'neutral',  {{-- neutral | green | amber | red | sage | blue --}}
    'size' => 'sm',        {{-- sm | md --}}
])

@php
$colors = [
    'neutral' => 'background-color: #E8E5E0; color: #6B6B67;',
    'green'   => 'background-color: #D4EDDA; color: #2D6A4F;',
    'sage'    => 'background-color: #C5D5C5; color: #4A6B4A;',
    'amber'   => 'background-color: #FEF3CD; color: #92660D;',
    'red'     => 'background-color: #FDDEDE; color: #922B2B;',
    'blue'    => 'background-color: #DBEAFE; color: #1D4ED8;',
    'terra'   => 'background-color: #F9DDD6; color: #8B3E25;',
];
$style = $colors[$color] ?? $colors['neutral'];
$sizeClass = $size === 'md' ? 'text-sm px-3 py-1' : 'text-xs px-2.5 py-0.5';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 font-medium rounded-full $sizeClass", 'style' => $style]) }}>
    {{ $slot }}
</span>
