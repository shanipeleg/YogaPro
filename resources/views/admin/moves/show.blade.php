@extends('layouts.admin')

@section('title', $move->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.moves') }}" class="text-sm text-blue-600 hover:underline">← Back to Poses</a>
</div>

<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">{{ $move->name }}</h1>
        @if($move->sanskrit_name)
            <p class="text-gray-500 italic text-sm mt-0.5">{{ $move->sanskrit_name }}</p>
        @endif
    </div>
    <form method="POST" action="{{ route('admin.moves.reenrich', $move) }}">
        @csrf
        <button type="submit" class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700">
            Re-enrich
        </button>
    </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Meta --}}
    <div class="bg-white border border-gray-200 rounded p-5">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Meta</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Category</dt>
                <dd class="font-medium capitalize">{{ $move->category ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Difficulty</dt>
                <dd class="font-medium">{{ $move->difficulty_base ?? '—' }} / 10</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Enrichment</dt>
                <dd>
                    @if($move->enrichment_status === 'enriched')
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">enriched</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">pending</span>
                    @endif
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Source</dt>
                <dd class="font-medium">{{ $move->data_source ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Appears in</dt>
                <dd class="font-medium">{{ $videosCount }} video(s)</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Inversion</dt>
                <dd class="font-medium">{{ $move->is_inversion ? 'Yes' : 'No' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">High impact</dt>
                <dd class="font-medium">{{ $move->high_impact ? 'Yes' : 'No' }}</dd>
            </div>
        </dl>

        @if($move->description)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Description</p>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $move->description }}</p>
            </div>
        @endif
    </div>

    {{-- Back Pain & Health Benefits --}}
    <div class="bg-white border border-gray-200 rounded p-5">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Back Pain & Benefits</h2>

        @php
            $backStyles = ['helps' => 'bg-green-100 text-green-800', 'neutral' => 'bg-gray-100 text-gray-600', 'avoid' => 'bg-red-100 text-red-800'];
        @endphp

        <dl class="space-y-2 text-sm mb-4">
            @foreach(['benefit_back_pain_lower' => 'Lower back', 'benefit_back_pain_upper' => 'Upper back', 'benefit_back_pain_general' => 'General back', 'benefit_pelvic_floor' => 'Pelvic floor', 'benefit_hip_mobility' => 'Hip mobility'] as $col => $label)
                <div class="flex justify-between items-center">
                    <dt class="text-gray-500">{{ $label }}</dt>
                    <dd>
                        @if($move->$col)
                            <span class="px-2 py-0.5 rounded text-xs font-medium {{ $backStyles[$move->$col] ?? '' }}">{{ $move->$col }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>

        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Boolean Benefits</p>
        <div class="flex flex-wrap gap-2">
            @foreach(['benefit_flexibility' => 'Flexibility', 'benefit_strength' => 'Strength', 'benefit_balance' => 'Balance', 'benefit_stress_relief' => 'Stress Relief', 'benefit_circulation' => 'Circulation', 'benefit_digestion' => 'Digestion', 'benefit_posture' => 'Posture'] as $col => $label)
                @if($move->$col)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">{{ $label }}</span>
                @endif
            @endforeach
        </div>

        <p class="text-xs text-gray-400 uppercase tracking-wide mb-2 mt-4">Spinal Actions</p>
        <div class="flex flex-wrap gap-2">
            @foreach(['spinal_compression' => 'Compression', 'spinal_flexion' => 'Flexion', 'spinal_extension' => 'Extension', 'spinal_rotation' => 'Rotation'] as $col => $label)
                @if($move->$col)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-700">{{ $label }}</span>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Body Areas & Contraindications --}}
    <div class="bg-white border border-gray-200 rounded p-5">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Body Areas Targeted</h2>
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach([
                'targets_lower_back' => 'Lower Back', 'targets_upper_back' => 'Upper Back', 'targets_mid_back' => 'Mid Back',
                'targets_pelvis' => 'Pelvis', 'targets_hips' => 'Hips', 'targets_hamstrings' => 'Hamstrings',
                'targets_hip_flexors' => 'Hip Flexors', 'targets_glutes' => 'Glutes', 'targets_core' => 'Core',
                'targets_shoulders' => 'Shoulders', 'targets_neck' => 'Neck', 'targets_chest' => 'Chest',
                'targets_quads' => 'Quads', 'targets_calves' => 'Calves', 'targets_ankles' => 'Ankles', 'targets_wrists' => 'Wrists',
            ] as $col => $label)
                @if($move->$col)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-sage-50 bg-teal-50 text-teal-700">{{ $label }}</span>
                @endif
            @endforeach
        </div>

        @if(!empty($move->weight_bearing_joints))
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Weight-Bearing Joints</p>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($move->weight_bearing_joints as $joint)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">{{ $joint }}</span>
                @endforeach
            </div>
        @endif

        @if(!empty($move->contraindications))
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Contraindications</p>
            <ul class="text-sm text-red-700 space-y-1">
                @foreach($move->contraindications as $item)
                    <li class="text-xs">• {{ $item }}</li>
                @endforeach
            </ul>
        @endif

        @if($move->modifications_available && $move->modifications_description)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Modifications</p>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $move->modifications_description }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
