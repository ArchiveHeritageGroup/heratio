{{-- Analysis Bridge - Research OS Stage 11 (heratio#1234) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Analysis Bridge'))

@php
    $typeBadges = $resultTypeBadges ?? [];
    $resultRows = is_array($results) ? $results : [];
    $themeTags  = $codes['theme_tag'] ?? [];
    $memos      = $codes['memo'] ?? [];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Analysis Bridge') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2"><i class="fas fa-project-diagram text-primary me-2"></i>{{ __('Analysis Bridge') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#registerResultForm"><i class="fas fa-plus me-1"></i>{{ __('Register result') }}</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('Register results produced elsewhere (Jupyter, R, QDA, statistics) with their full provenance, and link each to the claims it supports, weakens or contextualises. This bridge does not run analysis; it records where each result came from. No black-box outputs.') }}</p>

{{-- Register form --}}
<div class="collapse mb-4" id="registerResultForm">
    <div class="card card-body">
        <form method="POST" action="{{ route('research.analysis.store', $project->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="500" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Result type') }}</label>
                    <select name="result_type" class="form-select">
                        @foreach($resultTypes as $key => $label)
                            <option value="{{ $key }}" @selected($key === 'other')>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Source data') }}</label>
                    <input type="text" name="source_data_ref" class="form-control" maxlength="1000" placeholder="{{ __('Dataset, query, collection or file the result was produced from') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Source data version') }}</label>
                    <input type="text" name="source_data_version" class="form-control" maxlength="120" placeholder="{{ __('Snapshot / version / date') }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Method') }}</label>
                    <input type="text" name="method" class="form-control" maxlength="5000" placeholder="{{ __('The analytical method or technique applied') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Generated at') }}</label>
                    <input type="date" name="generated_at" class="form-control">
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Code / notebook reference') }}</label>
                    <input type="text" name="code_ref" class="form-control" maxlength="1000" placeholder="{{ __('Repo URL, notebook name or script path') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Artifact (optional)') }}</label>
                    <input type="file" name="artifact" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('Researcher decision') }}</label>
                    <textarea name="researcher_decision" class="form-control" rows="2" placeholder="{{ __('The interpretation or decision you drew from this result') }}"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('Register') }}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#registerResultForm">{{ __('Cancel') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('research.analysis.index', $project->id) }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('Search title, method or source') }}">
    </div>
    <div class="col-md-3">
        <select name="type" class="form-select form-select-sm">
            <option value="">{{ __('All types') }}</option>
            @foreach($resultTypes as $key => $label)
                <option value="{{ $key }}" @selected(($filters['result_type'] ?? '') === $key)>{{ __($label) }} ({{ (int) ($typeCounts[$key] ?? 0) }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100">{{ __('Filter') }}</button>
    </div>
</form>

{{-- Results register --}}
<div class="card mb-4">
    <div class="card-header fw-bold"><i class="fas fa-list me-1"></i>{{ __('Registered results') }} <span class="badge bg-secondary">{{ count($resultRows) }}</span></div>
    @if(count($resultRows) === 0)
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-flask fa-2x mb-2 d-block"></i>
            {{ __('No results registered yet. Register a chart, table, theme or statistic produced elsewhere to record its provenance.') }}
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Source data') }}</th>
                        <th>{{ __('Generated') }}</th>
                        <th>{{ __('Claims') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resultRows as $r)
                        <tr>
                            <td><a href="{{ route('research.analysis.show', [$project->id, $r->id]) }}">{{ e(\Illuminate\Support\Str::limit($r->title ?? __('Untitled'), 80)) }}</a></td>
                            <td><span class="badge bg-{{ $typeBadges[$r->result_type] ?? 'secondary' }}">{{ __($resultTypes[$r->result_type] ?? $r->result_type) }}</span></td>
                            <td class="small text-muted">{{ e(\Illuminate\Support\Str::limit($r->source_data_ref ?? '-', 50)) }}@if(!empty($r->source_data_version))<span class="text-muted"> · {{ e($r->source_data_version) }}</span>@endif</td>
                            <td class="small">{{ $r->generated_at ? \Illuminate\Support\Carbon::parse($r->generated_at)->format('Y-m-d') : '-' }}</td>
                            <td>@if(($r->link_count ?? 0) > 0)<span class="badge bg-info">{{ $r->link_count }}</span>@else<span class="text-muted small">{{ __('none') }}</span>@endif</td>
                            <td class="text-end">
                                <a href="{{ route('research.analysis.show', [$project->id, $r->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Light thematic-coding / memo panel --}}
<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-bold"><i class="fas fa-tags me-1"></i>{{ __('Theme tags') }} <span class="badge bg-secondary">{{ count($themeTags) }}</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('research.analysis.codes.add', $project->id) }}" class="row g-2 mb-3">
                    @csrf
                    <input type="hidden" name="kind" value="theme_tag">
                    <div class="col-8"><input type="text" name="label" class="form-control form-control-sm" maxlength="255" placeholder="{{ __('New theme tag') }}" required></div>
                    <div class="col-4"><button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-plus"></i></button></div>
                </form>
                @if(count($themeTags) === 0)
                    <p class="text-muted small mb-0">{{ __('No theme tags yet. Add codes here to organise thematic findings.') }}</p>
                @else
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($themeTags as $t)
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle p-2">
                                {{ e($t->label) }}
                                <form method="POST" action="{{ route('research.analysis.codes.delete', [$project->id, $t->id]) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm p-0 ms-1 text-danger" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                                </form>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-bold"><i class="fas fa-sticky-note me-1"></i>{{ __('Memos') }} <span class="badge bg-secondary">{{ count($memos) }}</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('research.analysis.codes.add', $project->id) }}" class="mb-3">
                    @csrf
                    <input type="hidden" name="kind" value="memo">
                    <input type="text" name="label" class="form-control form-control-sm mb-2" maxlength="255" placeholder="{{ __('Memo title') }}" required>
                    <textarea name="body" class="form-control form-control-sm mb-2" rows="2" placeholder="{{ __('Analytic note') }}"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Add memo') }}</button>
                </form>
                @if(count($memos) === 0)
                    <p class="text-muted small mb-0">{{ __('No memos yet. Record analytic notes alongside your results.') }}</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($memos as $m)
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between">
                                    <strong class="small">{{ e($m->label) }}</strong>
                                    <form method="POST" action="{{ route('research.analysis.codes.delete', [$project->id, $m->id]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                                @if(!empty($m->body))<div class="small text-muted">{{ e(\Illuminate\Support\Str::limit($m->body, 300)) }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
