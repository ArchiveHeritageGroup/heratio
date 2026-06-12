{{-- Research Team & Collaborators register - read-only detail (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Team Member'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.team.index', $project->id ?? 0) }}">{{ __('Research Team') }}</a></li>
        <li class="breadcrumb-item active">{{ e($member['person_name']) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php
    $st = $member['status'] ?? 'active';
    $badge = match($st) {
        'active' => 'success', 'inactive' => 'warning', 'former' => 'secondary',
        default => 'secondary',
    };
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-user text-primary me-2"></i>{{ e($member['person_name']) }}</h1>
        <div class="text-muted small">
            <span class="badge bg-info text-dark">{{ e($roleOptions[$member['role']] ?? ucfirst(str_replace('_',' ',$member['role']))) }}</span>
            <span class="badge bg-{{ $badge }} ms-1">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
            @if($member['is_lead'])<span class="badge bg-warning text-dark border ms-1"><i class="fas fa-star me-1"></i>{{ __('Project lead') }}</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('research.team.edit', [$project->id ?? 0, $member['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <a href="{{ route('research.team.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Contributor') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Affiliation') }}</dt>
            <dd class="col-sm-9">{{ $member['affiliation'] !== '' ? e($member['affiliation']) : '-' }}</dd>
            <dt class="col-sm-3">{{ __('Email') }}</dt>
            <dd class="col-sm-9">
                @if($member['email'] !== '')<a href="mailto:{{ e($member['email']) }}">{{ e($member['email']) }}</a>@else - @endif
            </dd>
            <dt class="col-sm-3">{{ __('ORCID iD') }}</dt>
            <dd class="col-sm-9">
                @if($orcidUrl)
                    <a href="{{ $orcidUrl }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-orcid text-success me-1"></i>{{ e($member['orcid']) }}</a>
                    <span class="text-muted small ms-1">({{ e($orcidUrl) }})</span>
                @else
                    <span class="text-muted">-</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Involvement') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Joined') }}</dt>
            <dd class="col-sm-9">{{ e($member['start_date'] !== '' ? $member['start_date'] : '-') }}</dd>
            <dt class="col-sm-3">{{ __('Left') }}</dt>
            <dd class="col-sm-9">{{ e($member['end_date'] !== '' ? $member['end_date'] : '-') }}</dd>
        </dl>
    </div>
</div>

@if(trim($member['contribution_note']) !== '')
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Contribution') }}</h6></div>
    <div class="card-body">
        <div style="white-space: pre-wrap;">{{ $member['contribution_note'] }}</div>
        <div class="form-text mt-2">{{ __('Contributions may be described using the international CRediT taxonomy; this field is free text.') }}</div>
    </div>
</div>
@endif

<form method="POST" action="{{ route('research.team.destroy', [$project->id ?? 0, $member['id']]) }}" onsubmit="return confirm('{{ __('Remove this team member?') }}');" class="mb-5">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Remove member') }}</button>
</form>
@endsection
