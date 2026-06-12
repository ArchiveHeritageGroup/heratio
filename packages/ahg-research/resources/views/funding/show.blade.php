{{-- Research Funding tracker - read-only detail (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Funding Record'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.funding.index', $project->id ?? 0) }}">{{ __('Research Funding') }}</a></li>
        <li class="breadcrumb-item active">{{ e($record['title']) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php
    $st = $record['status'] ?? 'applied';
    $badge = match($st) {
        'awarded' => 'success', 'active' => 'primary', 'completed' => 'secondary',
        'declined' => 'danger', 'applied' => 'warning', default => 'secondary',
    };
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-hand-holding-dollar text-primary me-2"></i>{{ e($record['title']) }}</h1>
        <div class="text-muted small">
            <span class="badge bg-info text-dark">{{ e($typeOptions[$record['funder_type']] ?? ucfirst(str_replace('_',' ',$record['funder_type']))) }}</span>
            <span class="badge bg-{{ $badge }} ms-1">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
            @if($isActive)<span class="badge bg-success ms-1">{{ __('Active now') }}</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('research.funding.edit', [$project->id ?? 0, $record['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <a href="{{ route('research.funding.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Funder') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Funder name') }}</dt>
            <dd class="col-sm-9">{{ e($record['funder_name']) }}</dd>
            @if($record['award_reference'] !== '')
                <dt class="col-sm-3">{{ __('Award reference') }}</dt>
                <dd class="col-sm-9">{{ e($record['award_reference']) }}</dd>
            @endif
        </dl>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Amount & period') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Amount') }}</dt>
            <dd class="col-sm-9">
                @if($record['amount'] !== '')
                    <span class="fw-semibold">{{ e($record['currency']) }} {{ number_format((float) $record['amount'], 2) }}</span>
                    <span class="text-muted small ms-1">({{ e($currencyOptions[$record['currency']] ?? $record['currency']) }})</span>
                @else
                    <span class="text-muted">-</span>
                @endif
            </dd>
            <dt class="col-sm-3">{{ __('Award start date') }}</dt>
            <dd class="col-sm-9">{{ e($record['start_date'] !== '' ? $record['start_date'] : '-') }}</dd>
            <dt class="col-sm-3">{{ __('Award end date') }}</dt>
            <dd class="col-sm-9">{{ e($record['end_date'] !== '' ? $record['end_date'] : '-') }}</dd>
        </dl>
    </div>
</div>

@if($dmp)
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Links') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Data management plan') }}</dt>
            <dd class="col-sm-9">
                @if(\Illuminate\Support\Facades\Route::has('research.dmp.show'))
                    <a href="{{ route('research.dmp.show', [$project->id ?? 0, $dmp->id]) }}"><i class="fas fa-database me-1"></i>{{ e($dmp->title) }}</a>
                @else
                    <i class="fas fa-database me-1"></i>{{ e($dmp->title) }}
                @endif
            </dd>
        </dl>
    </div>
</div>
@endif

@if(trim($record['notes']) !== '')
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Notes') }}</h6></div>
    <div class="card-body"><div style="white-space: pre-wrap;">{{ $record['notes'] }}</div></div>
</div>
@endif

<form method="POST" action="{{ route('research.funding.destroy', [$project->id ?? 0, $record['id']]) }}" onsubmit="return confirm('{{ __('Delete this funding record?') }}');" class="mb-5">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Delete record') }}</button>
</form>
@endsection
