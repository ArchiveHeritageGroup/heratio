{{--
    Source Triage board - Heratio ahg-research (heratio#1227, Research OS Stage 5)

    Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
    Licensed under the GNU Affero General Public License v3 or later.

    Per-project triage board over the project's sources (bibliography entries + collection items).
    The researcher sets a triage category + an HONEST read-status by hand; the system never marks
    anything 'read'. The optional AI preview is ALWAYS shown with its "not human verified" label.
--}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Source Triage'))

@php
    $catBadge = [
        'essential' => 'success', 'useful' => 'primary', 'background' => 'secondary',
        'contested' => 'warning', 'weak' => 'warning', 'duplicate' => 'dark',
        'excluded' => 'danger', 'read-later' => 'info', 'method-source' => 'info',
        'theory-source' => 'info', 'evidence-source' => 'primary',
    ];
    $readBadge = [
        'unread' => 'secondary', 'previewed' => 'info', 'skimmed' => 'warning',
        'read' => 'success', 'deeply-read' => 'success',
    ];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Source Triage') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2"><i class="fas fa-clipboard-check text-primary me-2"></i>{{ __('Source Triage') }}</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to project') }}</a>
</div>

<p class="text-muted">
    {{ __('Categorise each source and record how far you have honestly read it. The system never marks a source as read on your behalf - you set the read-status yourself.') }}
</p>

{{-- Filter by category and by read-status (server-side, deep-linkable) --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">{{ __('Triage category') }}</label>
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ ($categoryFilter ?? '') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Read-status') }}</label>
                <select name="read" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('All read-statuses') }}</option>
                    @foreach($readStatuses as $key => $label)
                        <option value="{{ $key }}" {{ ($readFilter ?? '') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('research.triage.index', $project->id ?? 0) }}" class="btn btn-sm btn-outline-secondary">{{ __('Clear') }}</a>
            </div>
        </form>
    </div>
</div>

@if(empty($sources))
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
            <h5>{{ __('No sources yet') }}</h5>
            <p class="mb-0">
                {{ __('This project has no bibliography entries or collection items to triage yet. Add sources to a bibliography or an evidence collection for this project, and they will appear here.') }}
            </p>
        </div>
    </div>
@else
    <div class="text-muted small mb-2">{{ count($sources) }} {{ __('source(s)') }}</div>

    @foreach($sources as $s)
        @php
            $cat = $s['triage_category'] ?? null;
            $read = $s['read_status'] ?? 'unread';
        @endphp
        <div class="card mb-2">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div class="me-3" style="min-width:0;">
                        <div class="fw-semibold text-truncate">{{ $s['title'] }}</div>
                        @if(trim((string)($s['subtitle'] ?? '')) !== '')
                            <div class="text-muted small text-truncate">{{ $s['subtitle'] }}</div>
                        @endif
                        <div class="small text-muted mt-1">
                            <span class="badge bg-light text-dark border">
                                {{ $s['source_type'] === 'bibliography_entry' ? __('Bibliography entry') : __('Collection item') }}
                            </span>
                            @if(trim((string)($s['group_name'] ?? '')) !== '')
                                <span class="ms-1">{{ $s['group_name'] }}</span>
                            @endif
                            @if(trim((string)($s['kind'] ?? '')) !== '')
                                <span class="ms-1">&middot; {{ $s['kind'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        @if($cat)
                            <span class="badge bg-{{ $catBadge[$cat] ?? 'secondary' }}">{{ __($categories[$cat] ?? $cat) }}</span>
                        @else
                            <span class="badge bg-light text-dark border">{{ __('Uncategorised') }}</span>
                        @endif
                        <span class="badge bg-{{ $readBadge[$read] ?? 'secondary' }}">{{ __($readStatuses[$read] ?? $read) }}</span>
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    {{-- Category control --}}
                    <div class="col-md-4">
                        <form method="POST" action="{{ route('research.triage.category', $project->id ?? 0) }}" class="d-flex gap-1">
                            @csrf
                            <input type="hidden" name="source_type" value="{{ $s['source_type'] }}">
                            <input type="hidden" name="source_id" value="{{ $s['source_id'] }}">
                            <select name="triage_category" class="form-select form-select-sm">
                                <option value="">{{ __('Uncategorised') }}</option>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" {{ $cat === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('Set category') }}"><i class="fas fa-tag"></i></button>
                        </form>
                    </div>

                    {{-- Read-status control (explicit human action only) --}}
                    <div class="col-md-4">
                        <form method="POST" action="{{ route('research.triage.readStatus', $project->id ?? 0) }}" class="d-flex gap-1">
                            @csrf
                            <input type="hidden" name="source_type" value="{{ $s['source_type'] }}">
                            <input type="hidden" name="source_id" value="{{ $s['source_id'] }}">
                            <select name="read_status" class="form-select form-select-sm">
                                @foreach($readStatuses as $key => $label)
                                    <option value="{{ $key }}" {{ $read === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Set read-status') }}"><i class="fas fa-book-reader"></i></button>
                        </form>
                    </div>

                    {{-- Optional AI preview trigger --}}
                    <div class="col-md-4 text-md-end">
                        <form method="POST" action="{{ route('research.triage.aiPreview', $project->id ?? 0) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="source_type" value="{{ $s['source_type'] }}">
                            <input type="hidden" name="source_id" value="{{ $s['source_id'] }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-wand-magic-sparkles me-1"></i>{{ empty($s['ai_preview']) ? __('AI preview') : __('Refresh AI preview') }}
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mt-2">
                    <form method="POST" action="{{ route('research.triage.notes', $project->id ?? 0) }}">
                        @csrf
                        <input type="hidden" name="source_type" value="{{ $s['source_type'] }}">
                        <input type="hidden" name="source_id" value="{{ $s['source_id'] }}">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-pen"></i></span>
                            <input type="text" name="notes" class="form-control" maxlength="5000"
                                   value="{{ $s['notes'] ?? '' }}" placeholder="{{ __('Triage notes (your own words)') }}">
                            <button type="submit" class="btn btn-outline-secondary">{{ __('Save notes') }}</button>
                        </div>
                    </form>
                </div>

                {{-- AI preview block - ALWAYS labelled, never presented as verified --}}
                @if(!empty($s['ai_preview']))
                    <div class="alert alert-warning mt-2 mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong><i class="fas fa-robot me-1"></i>{{ $aiPreviewLabel }}</strong>
                            @if(!empty($s['ai_preview_at']))
                                <small class="text-muted">{{ $s['ai_preview_at'] }}</small>
                            @endif
                        </div>
                        <div class="small" style="white-space:pre-wrap;">{{ $s['ai_preview'] }}</div>
                        <div class="small text-muted mt-1">{{ __('Generating this preview did NOT change the read-status - that stays whatever you set.') }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endif
@endsection
