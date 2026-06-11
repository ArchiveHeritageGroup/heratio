{{--
  Publication Studio - Heratio ahg-research (heratio#1232, ROS Stage 15)
  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Per-project studio home: existing submissions + a venue MATCHING panel that
  reads the target-journal directory (research_target_journal). Empty-states
  everywhere; never 500s.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title-block')
    <h1><i class="fas fa-feather-pointed me-2"></i>{{ __('Publication Studio') }} - {{ e($project->title) }}</h1>
    <p class="text-muted mb-0">{{ __('Match your project to a publication venue, then manage the submission, compliance checklist, reviewer responses, and deposit.') }}</p>
@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">{{ __('Publication Studio') }}</li>
    </ol>
</nav>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if(! $ready)
<div class="alert alert-warning">
    <i class="fas fa-database me-2"></i>{{ __('The Publication Studio storage is still being prepared. Reload this page in a moment.') }}
</div>
@else

<div class="row">
    {{-- =================== EXISTING SUBMISSIONS =================== --}}
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>{{ __('Submissions') }}</h5>
                <span class="badge bg-light text-dark">{{ count($submissions) }}</span>
            </div>
            <div class="card-body">
                @forelse($submissions as $s)
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                        <div class="me-2">
                            <a href="{{ route('research.publication.submission', [$project->id, $s['id']]) }}" class="fw-semibold text-decoration-none">
                                {{ e($s['venue_name']) }}
                            </a>
                            @if(!empty($s['manuscript_title']))
                                <div class="text-muted small">{{ e($s['manuscript_title']) }}</div>
                            @endif
                            @if(!empty($s['submitted_at']))
                                <div class="text-muted small">{{ __('Submitted') }}: {{ $s['submitted_at'] }}</div>
                            @endif
                        </div>
                        @include('research::publication-studio._status_badge', ['status' => $s['status']])
                    </div>
                @empty
                    <p class="text-muted mb-0"><i class="fas fa-circle-info me-1"></i>{{ __('No submissions yet. Match a venue on the right and create your first submission.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- =================== VENUE MATCHING =================== --}}
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-magnifying-glass me-2"></i>{{ __('Matching venues') }}</h5>
            </div>
            <div class="card-body">

                @if(! $directoryReady)
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-circle-info me-1"></i>{{ __('The target-journal directory is not available yet. An administrator can build it under Research, then matching will appear here.') }}
                    </div>
                @else
                    <form method="get" class="row g-2 align-items-end mb-3">
                        <div class="col-md-5">
                            <label class="form-label small mb-1">{{ __('Extra scope terms (optional)') }}</label>
                            <input type="text" name="scope_text" value="{{ e($filters['scope_text'] ?? '') }}" class="form-control form-control-sm" placeholder="{{ __('e.g. digital preservation, oral history') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">{{ __('Accreditation market') }}</label>
                            <select name="market" class="form-select form-select-sm">
                                <option value="">{{ __('Any') }}</option>
                                @foreach($markets as $m)
                                    <option value="{{ e($m) }}" @selected(($filters['market'] ?? '') === $m)>{{ e($m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="oaFilter" name="open_access" value="1" @checked(!empty($filters['open_access']))>
                                <label class="form-check-label small" for="oaFilter">{{ __('Open access') }}</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>{{ __('Match') }}</button>
                        </div>
                    </form>

                    @forelse($matches as $j)
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="me-2">
                                    <div class="fw-semibold">{{ e($j['title'] ?? __('Untitled journal')) }}</div>
                                    @if(!empty($j['publisher']))<div class="text-muted small">{{ e($j['publisher']) }}</div>@endif
                                    @if(!empty($j['subject_scope']))<div class="small mt-1">{{ \Illuminate\Support\Str::limit($j['subject_scope'], 160) }}</div>@endif
                                    <div class="mt-1">
                                        @if(!empty($j['accreditation_market']))<span class="badge bg-secondary">{{ e($j['accreditation_market']) }}</span>@endif
                                        @if(!empty($j['open_access']))<span class="badge bg-success">{{ __('Open access') }}</span>@endif
                                        @if(!empty($j['reference_style']))<span class="badge bg-light text-dark">{{ e($j['reference_style']) }}</span>@endif
                                        @if(($j['match_score'] ?? 0) > 0)<span class="badge bg-info text-dark">{{ __('fit') }} {{ $j['match_score'] }}</span>@endif
                                    </div>
                                </div>
                                <form method="post" action="{{ route('research.publication.submissions.store', $project->id) }}" class="ms-2">
                                    @csrf
                                    <input type="hidden" name="venue_ref" value="{{ $j['id'] ?? '' }}">
                                    <input type="hidden" name="venue_name" value="{{ e($j['title'] ?? '') }}">
                                    <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="fas fa-plus me-1"></i>{{ __('Submit to') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0"><i class="fas fa-circle-info me-1"></i>{{ __('No venues matched. Add scope terms above or broaden the filters.') }}</p>
                    @endforelse
                @endif

                {{-- Free-text venue (preprints, edited volumes, venues not in the directory). --}}
                <hr>
                <form method="post" action="{{ route('research.publication.submissions.store', $project->id) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label small mb-1">{{ __('Or record a venue not in the directory') }}</label>
                        <input type="text" name="venue_name" class="form-control form-control-sm" placeholder="{{ __('Venue name (journal, conference, edited volume, preprint server)') }}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-plus me-1"></i>{{ __('Add submission') }}</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

@endif
@endsection
