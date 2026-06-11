{{-- Method Design Studio - protocol summary / print view (heratio#1231) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Method Protocol'))

@section('content')
@php
    $proto = $reuse['protocol'] ?? [];
    $tmpl  = $reuse['template'] ?? null;
    $areas = $reuse['areas'] ?? [];
    $st = $proto['status'] ?? 'draft';
    $badge = match($st) { 'final' => 'success', 'in_review' => 'info', default => 'secondary' };
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="d-print-none">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.method.index', $project->id ?? 0) }}">{{ __('Method Studio') }}</a></li>
        <li class="breadcrumb-item active">{{ e($proto['title'] ?? __('Protocol')) }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h2 mb-1">{{ e($proto['title'] ?? __('Method Protocol')) }}</h1>
        <div>
            @if($tmpl)<span class="badge bg-light text-dark border">{{ e($tmpl['name']) }}</span>@endif
            @if($tmpl && !empty($tmpl['discipline']))<span class="text-muted small ms-1">{{ e($tmpl['discipline']) }}</span>@endif
            <span class="badge bg-{{ $badge }} ms-1">{{ ucfirst(str_replace('_',' ', $st)) }}</span>
        </div>
    </div>
    <div class="d-flex gap-2 d-print-none">
        <a href="{{ route('research.method.edit', [$project->id ?? 0, $proto['id'] ?? 0]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
        <a href="{{ route('research.method.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

@if(empty($areas))
    <div class="alert alert-info">{{ __('This protocol has no guidance areas to display.') }}</div>
@else
    @php $hasAnyAnswer = collect($areas)->contains(fn($a) => trim($a['answer'] ?? '') !== ''); @endphp
    @if(!$hasAnyAnswer)
        <div class="alert alert-info d-print-none">
            <i class="fas fa-info-circle me-1"></i>{{ __('No areas have been filled in yet.') }}
            <a href="{{ route('research.method.edit', [$project->id ?? 0, $proto['id'] ?? 0]) }}">{{ __('Open the editor') }}</a>.
        </div>
    @endif

    @foreach($areas as $area)
    <div class="card mb-3">
        <div class="card-header"><strong>{{ e($area['label'] ?? '') }}</strong></div>
        <div class="card-body">
            @if(!empty($area['prompt']))
                <p class="text-muted small fst-italic mb-2">{{ e($area['prompt']) }}</p>
            @endif
            @if(trim($area['answer'] ?? '') !== '')
                <div style="white-space:pre-wrap;">{{ $area['answer'] }}</div>
            @else
                <span class="text-muted fst-italic">{{ __('Not yet addressed.') }}</span>
            @endif
        </div>
    </div>
    @endforeach
@endif
@endsection
