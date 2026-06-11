{{-- Claim Ledger - Research OS Stage 8 (heratio#1223) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Claim Ledger')

@php
    $badges = $statusBadges ?? [];
    $totalClaims = is_array($claims) ? count($claims) : 0;
    $noCiteCount = is_array($noCitation) ? count($noCitation) : 0;
    $overDepCount = is_array($overDependent) ? count($overDependent) : 0;
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Claim Ledger') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2"><i class="fas fa-balance-scale text-primary me-2"></i>{{ __('Claim Ledger') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#createClaimForm"><i class="fas fa-plus me-1"></i>{{ __('Add Claim') }}</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('Every assertion in this project, tracked from idea to publishable. No unsupported claim passes silently.') }}</p>

{{-- Founding-principle surfaces --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-warning h-100">
            <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-exclamation-triangle text-warning me-1"></i>{{ __('Claims with NO citation') }}</span>
                <span class="badge bg-warning text-dark">{{ $noCiteCount }}</span>
            </div>
            <div class="card-body p-0">
                @if($noCiteCount > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($noCitation as $c)
                            <li class="list-group-item small">
                                <a href="{{ route('research.claims.show', [$project->id, $c->id]) }}">{{ e(\Illuminate\Support\Str::limit($c->object_value ?? $c->subject_label ?? 'Untitled claim', 120)) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-3 text-muted small"><i class="fas fa-check-circle text-success me-1"></i>{{ __('Every claim has at least one citation.') }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-info h-100">
            <div class="card-header bg-info-subtle d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-link text-info me-1"></i>{{ __('Over-dependent on one source') }}</span>
                <span class="badge bg-info text-dark">{{ $overDepCount }}</span>
            </div>
            <div class="card-body p-0">
                @if($overDepCount > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($overDependent as $c)
                            <li class="list-group-item small d-flex justify-content-between">
                                <a href="{{ route('research.claims.show', [$project->id, $c->id]) }}">{{ e(\Illuminate\Support\Str::limit($c->object_value ?? $c->subject_label ?? 'Untitled claim', 100)) }}</a>
                                <span class="text-muted">{{ $c->evidence_count ?? 0 }} {{ __('refs / 1 source') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-3 text-muted small"><i class="fas fa-check-circle text-success me-1"></i>{{ __('No claim leans on a single source.') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Create claim form (collapsed) --}}
<div class="collapse mb-4" id="createClaimForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Add Claim') }}</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('research.claims.store', $project->id) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('Claim') }} <span class="text-danger">*</span></label>
                    <textarea name="text" class="form-control" rows="2" required placeholder="{{ __('State the claim in one or two sentences.') }}"></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Status') }}</label>
                        <select name="status" class="form-select form-select-sm">
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ $key === 'idea' ? 'selected' : '' }}>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Confidence') }}</label>
                        <select name="confidence_level" class="form-select form-select-sm">
                            <option value="">{{ __('Not set') }}</option>
                            @foreach($confidenceLevels as $cl)
                                <option value="{{ $cl }}">{{ ucfirst($cl) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Evidence type') }}</label>
                        <select name="evidence_type" class="form-select form-select-sm">
                            <option value="">{{ __('Not set') }}</option>
                            @foreach($evidenceTypes as $et)
                                <option value="{{ $et }}">{{ ucfirst(str_replace('_', ' ', $et)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Originality') }}</label>
                        <select name="provenance_kind" class="form-select form-select-sm">
                            @foreach($provenanceKinds as $pk)
                                <option value="{{ $pk }}">{{ ucfirst($pk) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>{{ __('Add to ledger') }}</button>
            </form>
        </div>
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">{{ __('Status') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['status'] ?? '') === $key ? 'selected' : '' }}>{{ __($label) }} ({{ $statusCounts[$key] ?? 0 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">{{ __('Search') }}</label>
                <input type="text" name="q" value="{{ e($filters['search'] ?? '') }}" class="form-control form-control-sm" placeholder="{{ __('Search claim text...') }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('research.claims.index', $project->id) }}" class="btn btn-sm btn-outline-secondary">{{ __('Clear') }}</a>
            </div>
        </form>
    </div>
</div>

{{-- Claims table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold">{{ __('Claims') }}</span>
        <span class="badge bg-secondary">{{ $totalClaims }}</span>
    </div>
    <div class="card-body p-0">
        @if($totalClaims > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Claim') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Originality') }}</th>
                        <th class="text-center">{{ __('Evidence') }}</th>
                        <th>{{ __('Updated') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($claims as $c)
                    @php $st = $c->status ?? 'idea'; @endphp
                    <tr>
                        <td>{{ e(\Illuminate\Support\Str::limit($c->object_value ?? $c->subject_label ?? 'Untitled claim', 140)) }}</td>
                        <td><span class="badge bg-{{ $badges[$st] ?? 'secondary' }}">{{ __($statuses[$st] ?? ucfirst($st)) }}</span></td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst($c->meta->provenance_kind ?? 'original') }}</span></td>
                        <td class="text-center">
                            @if(($c->evidence_count ?? 0) > 0)
                                <span class="badge bg-success">{{ $c->evidence_count }}</span>
                            @else
                                <span class="badge bg-warning text-dark" title="{{ __('No citation') }}">0</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $c->updated_at ?? $c->created_at ?? '' }}</td>
                        <td class="text-end">
                            <a href="{{ route('research.claims.show', [$project->id, $c->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5 text-muted">
            <i class="fas fa-balance-scale fa-2x mb-2 opacity-50"></i>
            <p class="mb-0">{{ __('No claims yet. Add the first claim to start the ledger.') }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
