{{-- Research Ethics & Consent register - read-only detail (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Ethics Record'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.ethics.index', $project->id ?? 0) }}">{{ __('Research Ethics & Consent') }}</a></li>
        <li class="breadcrumb-item active">{{ e($record['title']) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php
    $st = $record['status'] ?? 'pending';
    $badge = match($st) {
        'approved' => 'success', 'conditions' => 'info', 'pending' => 'warning',
        'rejected' => 'danger', 'expired' => 'dark', default => 'secondary',
    };
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-shield-alt text-primary me-2"></i>{{ e($record['title']) }}</h1>
        <div class="text-muted small">
            <span class="badge bg-info text-dark">{{ e($typeOptions[$record['approval_type']] ?? ucfirst(str_replace('_',' ',$record['approval_type']))) }}</span>
            <span class="badge bg-{{ $badge }} ms-1">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
            @if($expiryFlag === 'expired')<span class="badge bg-danger ms-1">{{ __('Expired') }}</span>
            @elseif($expiryFlag === 'soon')<span class="badge bg-warning text-dark ms-1">{{ __('Expiring soon') }}</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('research.ethics.edit', [$project->id ?? 0, $record['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <a href="{{ route('research.ethics.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Approval') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            @if($record['committee_name'] !== '')
                <dt class="col-sm-3">{{ __('Committee / review body') }}</dt>
                <dd class="col-sm-9">{{ e($record['committee_name']) }}</dd>
            @endif
            @if($record['reference_number'] !== '')
                <dt class="col-sm-3">{{ __('Reference number') }}</dt>
                <dd class="col-sm-9">{{ e($record['reference_number']) }}</dd>
            @endif
            <dt class="col-sm-3">{{ __('Decision date') }}</dt>
            <dd class="col-sm-9">{{ e($record['decision_date'] !== '' ? $record['decision_date'] : '-') }}</dd>
            <dt class="col-sm-3">{{ __('Expiry date') }}</dt>
            <dd class="col-sm-9">
                {{ e($record['expiry_date'] !== '' ? $record['expiry_date'] : '-') }}
                @if($expiryFlag === 'expired')<span class="badge bg-danger ms-1">{{ __('Expired') }}</span>
                @elseif($expiryFlag === 'soon')<span class="badge bg-warning text-dark ms-1">{{ __('Expiring soon') }}</span>@endif
            </dd>
        </dl>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Consent & data') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Consent basis') }}</dt>
            <dd class="col-sm-9">{{ e($consentOptions[$record['consent_basis']] ?? ucfirst(str_replace('_',' ',$record['consent_basis']))) }}</dd>
            <dt class="col-sm-3">{{ __('Data sensitivity') }}</dt>
            <dd class="col-sm-9">{{ e($sensOptions[$record['data_sensitivity']] ?? ucfirst(str_replace('_',' ',$record['data_sensitivity']))) }}</dd>
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

@if(trim($record['notes']) !== '')
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Notes') }}</h6></div>
    <div class="card-body"><div style="white-space: pre-wrap;">{{ $record['notes'] }}</div></div>
</div>
@endif

<form method="POST" action="{{ route('research.ethics.destroy', [$project->id ?? 0, $record['id']]) }}" onsubmit="return confirm('{{ __('Delete this ethics record?') }}');" class="mb-5">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Delete record') }}</button>
</form>
@endsection
