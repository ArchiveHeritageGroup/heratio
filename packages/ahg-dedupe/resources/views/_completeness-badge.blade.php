{{-- Partial: Completeness badge --}}
@props(['score' => 0, 'level' => 'stub'])
@php $colors = ['stub'=>'danger','minimal'=>'warning','partial'=>'info','full'=>'success']; @endphp
<span class="badge bg-{{ $colors[$level] ?? 'secondary' }}" title="Completeness: {{ $score }}%">{{ $score }}%</span>
