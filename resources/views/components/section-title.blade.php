@props([])

<h2 {{ $attributes->merge(['class' => 'text-lg font-semibold', 'style' => "color: #3D3D3A; font-family: 'Lora', serif;"]) }}>
    {{ $slot }}
</h2>
