{{-- Research Team & Collaborators register - per-project list + summary (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Research Team'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Research Team') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-users text-primary me-2"></i>{{ __('Research Team') }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.team.create', $project->id ?? 0) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Member') }}</a>
        @if(($summary['total'] ?? 0) > 0)
            <a href="{{ route('research.team.export', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('Export JSON') }}</a>
        @endif
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('The register of the people on this project - co-investigators, students, partners, technicians and external collaborators - each with their role, affiliation and ORCID iD. This documents the wider contributor team alongside the project owner; it does not replace the owner. The role list is informed by the international CRediT contributor-roles taxonomy, and each member can carry a free-text contribution note.') }}</p>

{{-- Per-project summary --}}
@if(($summary['total'] ?? 0) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ __('Summary') }}</h6>
        <span class="badge bg-primary rounded-pill">{{ $summary['total'] }} {{ __('total') }}</span>
    </div>
    <div class="card-body">
        @if(($summary['active'] ?? 0) > 0)
        <div class="alert alert-success d-flex align-items-center mb-3 py-2">
            <i class="fas fa-user-check me-2"></i>
            <div class="small"><strong>{{ $summary['active'] }}</strong> {{ trans_choice('member is|members are', $summary['active']) }} {{ __('currently active.') }}</div>
        </div>
        @endif

        {{-- Leads highlighted. --}}
        @if(! empty($summary['leads']))
        <div class="mb-3">
            <div class="text-muted small text-uppercase mb-2">{{ __('Project lead(s)') }}</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($summary['leads'] as $lead)
                <a href="{{ route('research.team.show', [$project->id ?? 0, $lead['id']]) }}" class="text-decoration-none">
                    <span class="badge bg-warning text-dark border"><i class="fas fa-star me-1"></i>{{ e($lead['name']) }} <span class="text-muted">&middot; {{ e($lead['role_label']) }}</span></span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By role') }}</div>
                @foreach($summary['by_role'] as $r)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($r['label']) }} <span class="badge bg-secondary ms-1">{{ $r['count'] }}</span></span>
                @endforeach
            </div>
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By status') }}</div>
                @foreach($summary['by_status'] as $s)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($s['label']) }} <span class="badge bg-secondary ms-1">{{ $s['count'] }}</span></span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- Member list --}}
@if(empty($members))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No team members yet. Record the people on this project - each contributor\'s name, role, affiliation and ORCID iD.') }}</div>
@else
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Person') }}</th>
                <th>{{ __('Role') }}</th>
                <th>{{ __('Affiliation') }}</th>
                <th>{{ __('ORCID') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($members as $m)
            @php
                $st = $m['status'] ?? 'active';
                $badge = match($st) {
                    'active' => 'success', 'inactive' => 'warning', 'former' => 'secondary',
                    default => 'secondary',
                };
                $orcidUrl = $orcidUrls[$m['id']] ?? null;
            @endphp
            <tr>
                <td>
                    <a href="{{ route('research.team.show', [$project->id ?? 0, $m['id']]) }}">{{ e($m['person_name']) }}</a>
                    @if($m['is_lead'])<span class="badge bg-warning text-dark border ms-1"><i class="fas fa-star me-1"></i>{{ __('Lead') }}</span>@endif
                    @if($m['email'] !== '')<div class="text-muted small">{{ e($m['email']) }}</div>@endif
                </td>
                <td><span class="text-muted small">{{ e($roleOptions[$m['role']] ?? ucfirst(str_replace('_',' ',$m['role']))) }}</span></td>
                <td class="small">{{ $m['affiliation'] !== '' ? e($m['affiliation']) : '-' }}</td>
                <td class="small">
                    @if($orcidUrl)
                        <a href="{{ $orcidUrl }}" target="_blank" rel="noopener noreferrer"><i class="fab fa-orcid text-success me-1"></i>{{ e($m['orcid']) }}</a>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td><span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span></td>
                <td class="text-end">
                    <a href="{{ route('research.team.edit', [$project->id ?? 0, $m['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.team.show', [$project->id ?? 0, $m['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
