@props([
    'padding' => true,
    'hover' => false,
])

<div {{ $attributes->merge([
    'class' => 'rounded-2xl ' .
        ($padding ? 'p-4 ' : '') .
        ($hover ? 'transition-transform active:scale-[0.98] cursor-pointer ' : ''),
    'style' => 'background-color: #FFFFFF; box-shadow: 0 2px 8px rgba(61,61,58,0.08);',
]) }}>
    {{ $slot }}
</div>
