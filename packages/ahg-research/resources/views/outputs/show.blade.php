{{-- Research Outputs register - read-only detail with resolvable link (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Research Output'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.outputs.index', $project->id ?? 0) }}">{{ __('Research Outputs') }}</a></li>
        <li class="breadcrumb-item active">{{ e($output['title']) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php
    $st = $output['status'] ?? 'planned';
    $badge = match($st) { 'published' => 'success', 'in_progress' => 'warning', default => 'secondary' };
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-book text-primary me-2"></i>{{ e($output['title']) }}</h1>
        <div class="text-muted small">
            <span class="badge bg-info text-dark">{{ e($typeOptions[$output['output_type']] ?? ucfirst(str_replace('_',' ',$output['output_type']))) }}</span>
            <span class="badge bg-{{ $badge }} ms-1">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
            @if($output['output_date'] !== '')<span class="ms-2"><i class="fas fa-calendar me-1"></i>{{ e($output['output_date']) }}</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('research.outputs.edit', [$project->id ?? 0, $output['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <a href="{{ route('research.outputs.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            @if($output['authors'] !== '')
                <dt class="col-sm-3">{{ __('Authors') }}</dt>
                <dd class="col-sm-9">{{ e($output['authors']) }}</dd>
            @endif
            @if($output['venue'] !== '')
                <dt class="col-sm-3">{{ __('Venue') }}</dt>
                <dd class="col-sm-9">{{ e($output['venue']) }}</dd>
            @endif
            <dt class="col-sm-3">{{ __('Identifier') }}</dt>
            <dd class="col-sm-9">
                @if($resolvedUrl)
                    @if($output['identifier_type'] !== '')<span class="badge bg-secondary me-1">{{ e($identifierOptions[$output['identifier_type']] ?? strtoupper($output['identifier_type'])) }}</span>@endif
                    <a href="{{ e($resolvedUrl) }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt me-1"></i>{{ e($output['identifier'] !== '' ? $output['identifier'] : $resolvedUrl) }}</a>
                @elseif($output['identifier'] !== '')
                    {{ e($output['identifier']) }}
                @else
                    <span class="text-muted">{{ __('None recorded') }}</span>
                @endif
            </dd>
            @if($dmp)
                <dt class="col-sm-3">{{ __('Data management plan') }}</dt>
                <dd class="col-sm-9">
                    @if(\Illuminate\Support\Facades\Route::has('research.dmp.show'))
                        <a href="{{ route('research.dmp.show', [$project->id ?? 0, $dmp->id]) }}"><i class="fas fa-database me-1"></i>{{ e($dmp->title) }}</a>
                    @else
                        <i class="fas fa-database me-1"></i>{{ e($dmp->title) }}
                    @endif
                </dd>
            @endif
        </dl>
    </div>
</div>

@if(trim($output['notes']) !== '')
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Abstract / notes') }}</h6></div>
    <div class="card-body"><div style="white-space: pre-wrap;">{{ $output['notes'] }}</div></div>
</div>
@endif

<form method="POST" action="{{ route('research.outputs.destroy', [$project->id ?? 0, $output['id']]) }}" onsubmit="return confirm('{{ __('Delete this research output?') }}');" class="mb-5">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Delete output') }}</button>
</form>
@endsection
