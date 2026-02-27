{{--
  Pose Preview Animation Component
  Props:
    $items    — array of ['image_url' => ..., 'name' => ...], ordered by video sequence
    $fallback — fallback image URL (thumbnail) when < 2 images available
    $showName — whether to show pose name caption (default false)
  Usage: <x-pose-preview :items="$items" :fallback="$video->thumbnail_url"
                          class="rounded-xl flex-shrink-0" style="width: 88px; height: 58px;" />
--}}
@props(['items' => [], 'fallback' => null, 'showName' => false])

@if(count($items) >= 2)
<div
    x-data='posePreviewComp(@json($items))'
    {{ $attributes->merge(['style' => 'background-color: #F4F1EC; position: relative;', 'class' => 'overflow-hidden']) }}>
    <img
        :src="src"
        :alt="poseName"
        class="w-full h-full object-contain"
        :style="'transition: opacity 0.1s ease; opacity: ' + (vis ? 1 : 0)"
    />
    @if($showName)
    <div class="absolute bottom-0 left-0 right-0 px-2 py-1 text-center"
         style="background: linear-gradient(to top, rgba(244,241,236,0.95) 0%, transparent 100%);">
        <p class="text-xs font-medium truncate" style="color: #6B6B67;" x-text="poseName"></p>
    </div>
    @endif
</div>
@elseif($fallback)
<img
    src="{{ $fallback }}"
    alt=""
    {{ $attributes->merge(['class' => 'object-cover']) }}
/>
@else
<div {{ $attributes->merge(['class' => 'flex items-center justify-center text-2xl', 'style' => 'background-color: #E8E5E0;']) }}>
    🧘
</div>
@endif
