@extends('layouts.admin')

@section('title', 'Poses')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-900">Yoga Poses</h1>
    <form method="POST" action="{{ route('admin.moves.reenrich-all-pending') }}">
        @csrf
        <button type="submit" class="text-sm px-3 py-1.5 bg-amber-500 text-white rounded hover:bg-amber-600 transition-colors">
            Re-enrich All Pending
        </button>
    </form>
</div>

{{-- Summary --}}
<div class="flex flex-wrap gap-3 mb-5">
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 rounded-full text-sm">
        <span class="font-medium">{{ number_format($totalCount) }}</span> <span class="text-gray-500">total</span>
    </span>
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-50 text-green-800 rounded-full text-sm">
        <span class="font-medium">{{ number_format($enrichedCount) }}</span> enriched
    </span>
    @if($pendingCount > 0)
    <span class="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-800 rounded-full text-sm">
        <span class="font-medium">{{ number_format($pendingCount) }}</span> pending stubs
    </span>
    @endif
</div>

{{-- Status tabs --}}
<div class="flex gap-1 mb-4 border-b border-gray-200">
    @foreach(['all' => 'All', 'enriched' => 'Enriched', 'pending' => 'Pending'] as $key => $label)
        <a href="{{ route('admin.moves', ['status' => $key]) }}"
           class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
               {{ $status === $key
                   ? 'border-blue-600 text-blue-600'
                   : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Table --}}
<div class="bg-white border border-gray-200 rounded overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-2">ID</th>
                <th class="text-left px-4 py-2">Name</th>
                <th class="text-left px-4 py-2">Sanskrit</th>
                <th class="text-left px-4 py-2">Category</th>
                <th class="text-left px-4 py-2">Status</th>
                <th class="text-left px-4 py-2">Back Pain</th>
                <th class="text-left px-4 py-2">Spinal</th>
                <th class="text-left px-4 py-2">Source</th>
                <th class="text-left px-4 py-2">Videos</th>
                <th class="text-left px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($moves as $move)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $move->id }}</td>
                    <td class="px-4 py-2 font-medium text-gray-800">{{ $move->name }}</td>
                    <td class="px-4 py-2 text-gray-500 text-xs italic">{{ $move->sanskrit_name ?? '—' }}</td>
                    <td class="px-4 py-2 text-gray-600 text-xs capitalize">{{ $move->category ?? '—' }}</td>
                    <td class="px-4 py-2">
                        @if($move->enrichment_status === 'enriched')
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">enriched</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">pending</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        @php
                            $backStyles = [
                                'helps'   => 'bg-green-100 text-green-800',
                                'neutral' => 'bg-gray-100 text-gray-600',
                                'avoid'   => 'bg-red-100 text-red-800',
                            ];
                            $bp = $move->benefit_back_pain_lower;
                        @endphp
                        @if($bp)
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $backStyles[$bp] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $bp }}
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500 space-x-1">
                        @if($move->spinal_compression) <span title="compression" class="text-orange-500">C</span> @endif
                        @if($move->spinal_flexion)     <span title="flexion"     class="text-yellow-600">F</span> @endif
                        @if($move->spinal_extension)   <span title="extension"   class="text-blue-500">E</span>  @endif
                        @if($move->spinal_rotation)    <span title="rotation"    class="text-purple-500">R</span>@endif
                        @if(!$move->spinal_compression && !$move->spinal_flexion && !$move->spinal_extension && !$move->spinal_rotation)
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500">{{ $move->data_source ?? '—' }}</td>
                    <td class="px-4 py-2 text-center font-mono text-sm">{{ $move->videos_count }}</td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex gap-2">
                            <a href="{{ route('admin.moves.show', $move) }}"
                               class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">
                                View
                            </a>
                            <form method="POST" action="{{ route('admin.moves.reenrich', $move) }}">
                                @csrf
                                <button type="submit" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                    Re-enrich
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-gray-400 italic">No poses found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $moves->links() }}
</div>
@endsection
