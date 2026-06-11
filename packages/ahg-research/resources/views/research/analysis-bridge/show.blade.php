{{-- Analysis Bridge: result detail - Research OS Stage 11 (heratio#1234) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Analysis result'))

@php
    $typeBadges  = $resultTypeBadges ?? [];
    $relBadges   = $relationshipBadges ?? [];
    $links       = is_array($linkedClaims) ? $linkedClaims : [];
    $claims      = is_array($availableClaims) ? $availableClaims : [];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.analysis.index', $project->id ?? 0) }}">{{ __('Analysis Bridge') }}</a></li>
        <li class="breadcrumb-item active">{{ e(\Illuminate\Support\Str::limit($result->title ?? __('Result'), 40)) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1">{{ e($result->title ?? __('Untitled result')) }}</h1>
        <span class="badge bg-{{ $typeBadges[$result->result_type] ?? 'secondary' }}">{{ __($resultTypes[$result->result_type] ?? $result->result_type) }}</span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#editResultForm"><i class="fas fa-edit me-1"></i>{{ __('Edit') }}</button>
        <a href="{{ route('research.analysis.index', $project->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

{{-- Provenance card - every result shows its origin, no black box --}}
<div class="card mb-4 border-primary-subtle">
    <div class="card-header bg-primary-subtle fw-bold"><i class="fas fa-fingerprint me-1"></i>{{ __('Provenance') }}</div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Source data') }}</dt>
            <dd class="col-sm-9">{{ e($result->source_data_ref ?? '') ?: '-' }}</dd>

            <dt class="col-sm-3">{{ __('Source data version') }}</dt>
            <dd class="col-sm-9">{{ e($result->source_data_version ?? '') ?: '-' }}</dd>

            <dt class="col-sm-3">{{ __('Method') }}</dt>
            <dd class="col-sm-9">{{ e($result->method ?? '') ?: '-' }}</dd>

            <dt class="col-sm-3">{{ __('Code / notebook') }}</dt>
            <dd class="col-sm-9">
                @php $codeRef = (string) ($result->code_ref ?? ''); @endphp
                @if($codeRef === '')
                    -
                @elseif(\Illuminate\Support\Str::startsWith($codeRef, ['http://', 'https://']))
                    <a href="{{ e($codeRef) }}" target="_blank" rel="noopener noreferrer">{{ e($codeRef) }}</a>
                @else
                    <code>{{ e($codeRef) }}</code>
                @endif
            </dd>

            <dt class="col-sm-3">{{ __('Generated at') }}</dt>
            <dd class="col-sm-9">{{ $result->generated_at ? \Illuminate\Support\Carbon::parse($result->generated_at)->format('Y-m-d') : '-' }}</dd>

            <dt class="col-sm-3">{{ __('Researcher decision') }}</dt>
            <dd class="col-sm-9">{{ e($result->researcher_decision ?? '') ?: '-' }}</dd>

            <dt class="col-sm-3">{{ __('Artifact') }}</dt>
            <dd class="col-sm-9">
                @if(!empty($result->artifact_path))
                    <a href="{{ route('research.analysis.artifact', [$project->id, $result->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>{{ __('Download') }}</a>
                @else
                    <span class="text-muted">{{ __('None attached') }}</span>
                @endif
            </dd>

            <dt class="col-sm-3">{{ __('Registered') }}</dt>
            <dd class="col-sm-9 text-muted">{{ $result->created_at ? \Illuminate\Support\Carbon::parse($result->created_at)->format('Y-m-d H:i') : '-' }}</dd>
        </dl>
    </div>
</div>

{{-- Edit form --}}
<div class="collapse mb-4" id="editResultForm">
    <div class="card card-body">
        <form method="POST" action="{{ route('research.analysis.update', [$project->id, $result->id]) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="500" value="{{ e($result->title ?? '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Result type') }}</label>
                    <select name="result_type" class="form-select">
                        @foreach($resultTypes as $key => $label)
                            <option value="{{ $key }}" @selected(($result->result_type ?? 'other') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Source data') }}</label>
                    <input type="text" name="source_data_ref" class="form-control" maxlength="1000" value="{{ e($result->source_data_ref ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Source data version') }}</label>
                    <input type="text" name="source_data_version" class="form-control" maxlength="120" value="{{ e($result->source_data_version ?? '') }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Method') }}</label>
                    <input type="text" name="method" class="form-control" maxlength="5000" value="{{ e($result->method ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Generated at') }}</label>
                    <input type="date" name="generated_at" class="form-control" value="{{ $result->generated_at ? \Illuminate\Support\Carbon::parse($result->generated_at)->format('Y-m-d') : '' }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Code / notebook reference') }}</label>
                    <input type="text" name="code_ref" class="form-control" maxlength="1000" value="{{ e($result->code_ref ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Replace artifact') }}</label>
                    <input type="file" name="artifact" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('Researcher decision') }}</label>
                    <textarea name="researcher_decision" class="form-control" rows="2">{{ e($result->researcher_decision ?? '') }}</textarea>
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-between">
                <div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#editResultForm">{{ __('Cancel') }}</button>
                </div>
            </div>
        </form>
        <hr>
        <form method="POST" action="{{ route('research.analysis.destroy', [$project->id, $result->id]) }}" onsubmit="return confirm('{{ __('Delete this result and its claim links?') }}');">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Delete result') }}</button>
        </form>
    </div>
</div>

{{-- Linked claims --}}
<div class="card mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-link me-1"></i>{{ __('Linked claims') }} <span class="badge bg-info">{{ count($links) }}</span></span>
    </div>
    <div class="card-body">
        @if(count($links) === 0)
            <p class="text-muted mb-3">{{ __('This result is not yet linked to any claim. Link it to the claim(s) it supports, weakens or contextualises so the evidence trail is explicit.') }}</p>
        @else
            <ul class="list-group list-group-flush mb-3">
                @foreach($links as $l)
                    <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                        <div>
                            <span class="badge bg-{{ $relBadges[$l->relationship] ?? 'secondary' }} me-2">{{ __($relationships[$l->relationship] ?? $l->relationship) }}</span>
                            <a href="{{ route('research.claims.show', [$project->id, $l->assertion_id]) }}">{{ e(\Illuminate\Support\Str::limit($l->claim_label ?? ('Claim #' . $l->assertion_id), 110)) }}</a>
                            @if(!empty($l->claim_status))<span class="badge bg-light text-dark ms-1">{{ e($l->claim_status) }}</span>@endif
                            @if(!empty($l->note))<div class="small text-muted mt-1">{{ e($l->note) }}</div>@endif
                        </div>
                        <form method="POST" action="{{ route('research.analysis.unlink', [$project->id, $result->id, $l->id]) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="{{ __('Remove link') }}"><i class="fas fa-times"></i></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        @if(count($claims) === 0)
            <p class="text-muted small mb-0">{{ __('No claims exist in this project yet. Add claims in the Claim Ledger to link results to them.') }}</p>
        @else
            <form method="POST" action="{{ route('research.analysis.link', [$project->id, $result->id]) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label class="form-label small">{{ __('Claim') }}</label>
                    <select name="assertion_id" class="form-select form-select-sm" required>
                        <option value="">{{ __('Select a claim') }}</option>
                        @foreach($claims as $c)
                            <option value="{{ $c->id }}">{{ e(\Illuminate\Support\Str::limit($c->label, 90)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">{{ __('Relationship') }}</label>
                    <select name="relationship" class="form-select form-select-sm">
                        @foreach($relationships as $key => $label)
                            <option value="{{ $key }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-link me-1"></i>{{ __('Link claim') }}</button>
                </div>
                <div class="col-12">
                    <input type="text" name="note" class="form-control form-control-sm" maxlength="2000" placeholder="{{ __('Optional note on how this result bears on the claim') }}">
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
