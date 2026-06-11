{{--
  Research Impact Tracking - per-project Impact panel (heratio#1241, Research OS #19, moonshot 25)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  The downstream impact of this project's PUBLISHED outputs: citation count and
  citing works, mentions, and dataset reuse. Fed by the scheduled
  ahg:research-impact-refresh command which polls the public OpenAlex and
  Crossref Event Data APIs directly (never the AI gateway). Empty-states
  throughout; never 500.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Impact Tracking')
@section('title-block')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="h2 mb-0">
    <i class="fas fa-chart-line text-primary me-2"></i>{{ __('Impact Tracking') }}
  </h1>
  @if(($canManage ?? false) && !empty($outputs))
    <form method="POST" action="{{ route('research.impact.refresh', $project->id) }}" class="m-0">
      @csrf
      <button type="submit" class="btn btn-outline-primary">
        <i class="fas fa-rotate me-1"></i>{{ __('Refresh now') }}
      </button>
    </form>
  @endif
</div>
@endsection

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Impact Tracking') }}</li>
  </ol>
</nav>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
  </div>
@endif

<p class="text-muted">
  {{ __('Tracks the downstream impact of this project\'s published outputs. After a submission is published with a DOI, Heratio polls the public OpenAlex and Crossref Event Data services for citations, mentions and dataset reuse, and groups what it finds below. Public bibliographic services are used directly - no AI gateway is involved.') }}
</p>

@php
  $types        = $types ?? \AhgResearch\Services\ImpactTrackingService::TYPES;
  $counts       = $counts ?? [];
  $signals      = $signals ?? [];
  $grouped      = $grouped ?? [];
  $outputs      = $outputs ?? [];
  $activeType   = $activeType ?? '';
  $citationCount= (int) ($citationCount ?? 0);
  $totalCount   = (int) ($totalCount ?? 0);
  $lastScanned  = $lastScanned ?? null;
@endphp

@if(empty($outputs))
  {{-- No published outputs: the feature has nothing to track yet. --}}
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-file-circle-check fa-3x mb-3 d-block opacity-50"></i>
      <p class="mb-1 fw-bold">{{ __('No published outputs yet') }}</p>
      <p class="mb-3">{{ __('Impact tracking starts once this project has a published submission with a DOI. Record one in Publication Studio, then come back here.') }}</p>
      <a href="{{ route('research.publication.index', $project->id) }}" class="btn btn-outline-primary">
        <i class="fas fa-book me-1"></i>{{ __('Open Publication Studio') }}
      </a>
    </div>
  </div>
@else
  {{-- Headline metrics --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="display-6 fw-bold text-primary">{{ number_format($citationCount) }}</div>
          <div class="text-muted small text-uppercase">{{ __('Citations') }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="display-6 fw-bold text-secondary">{{ number_format($totalCount) }}</div>
          <div class="text-muted small text-uppercase">{{ __('Total signals') }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="display-6 fw-bold text-success">{{ count($outputs) }}</div>
          <div class="text-muted small text-uppercase">{{ __('Tracked outputs') }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card h-100">
        <div class="card-body text-center">
          @if($lastScanned)
            <div class="fw-bold">{{ \Carbon\Carbon::parse($lastScanned)->diffForHumans() }}</div>
            <div class="text-muted small">{{ \Carbon\Carbon::parse($lastScanned)->format('j M Y, H:i') }}</div>
          @else
            <div class="fw-bold text-muted">{{ __('Not yet scanned') }}</div>
          @endif
          <div class="text-muted small text-uppercase">{{ __('Last refresh') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Tracked published outputs (DOIs) --}}
  <div class="card mb-3">
    <div class="card-header bg-white fw-bold">
      <i class="fas fa-file-lines me-1 text-muted"></i>{{ __('Tracked published outputs') }}
    </div>
    <ul class="list-group list-group-flush">
      @foreach($outputs as $out)
        <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span>{{ e($out['title'] ?? __('Published output')) }}</span>
          <a href="https://doi.org/{{ e($out['doi']) }}" target="_blank" rel="noopener noreferrer" class="small">
            <i class="fas fa-up-right-from-square me-1"></i>{{ e($out['doi']) }}
          </a>
        </li>
      @endforeach
    </ul>
  </div>

  {{-- Type filter chips --}}
  <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="{{ route('research.impact.index', $project->id) }}"
       class="btn btn-sm {{ $activeType === '' ? 'btn-secondary' : 'btn-outline-secondary' }}">
      {{ __('All') }} <span class="badge bg-light text-dark ms-1">{{ $totalCount }}</span>
    </a>
    @foreach($types as $code => $meta)
      @php $n = (int) ($counts[$code] ?? 0); @endphp
      <a href="{{ route('research.impact.index', ['projectId' => $project->id, 'type' => $code]) }}"
         class="btn btn-sm {{ $activeType === $code ? 'btn-secondary' : 'btn-outline-secondary' }}"
         style="{{ $activeType === $code ? 'background-color:'.$meta['color'].';border-color:'.$meta['color'] : 'border-color:'.$meta['color'].';color:'.$meta['color'] }}">
        <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ __($meta['label']) }}
        <span class="badge bg-light text-dark ms-1">{{ $n }}</span>
      </a>
    @endforeach
  </div>

  {{-- Signals grouped by type --}}
  @php
    // When a single type is active, only that group is present; otherwise show
    // every type that has at least one visible signal, in canonical order.
    $orderedCodes = array_keys($types);
  @endphp

  @if(empty($signals))
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="fas fa-chart-line fa-3x mb-3 d-block opacity-50"></i>
        <p class="mb-1 fw-bold">{{ __('No impact signals yet') }}</p>
        <p class="mb-3">{{ __('Heratio checks the public bibliographic services every day. Citations, mentions and dataset reuse of this project\'s published outputs will appear here as they are detected.') }}</p>
        @if(($canManage ?? false))
          <form method="POST" action="{{ route('research.impact.refresh', $project->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
              <i class="fas fa-rotate me-1"></i>{{ __('Check now') }}
            </button>
          </form>
        @endif
      </div>
    </div>
  @else
    @foreach($orderedCodes as $code)
      @php $rows = $grouped[$code] ?? []; @endphp
      @if(!empty($rows))
        @php $meta = $types[$code]; @endphp
        <div class="card mb-3">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold" style="color: {{ $meta['color'] }};">
              <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ __($meta['label']) }}
            </span>
            <span class="badge" style="background-color: {{ $meta['color'] }};">{{ count($rows) }}</span>
          </div>
          <ul class="list-group list-group-flush">
            @foreach($rows as $sig)
              @php $when = $sig->detected_at ?? $sig->created_at ?? null; @endphp
              <li class="list-group-item">
                <div class="fw-bold">{{ e($sig->title ?: __('Impact signal')) }}</div>
                @if(!empty($sig->detail))
                  <div class="text-muted small mt-1" style="white-space: pre-line;">{{ e($sig->detail) }}</div>
                @endif
                <div class="small mt-1 d-flex flex-wrap gap-3">
                  @if(!empty($sig->url))
                    <span>
                      <i class="fas fa-up-right-from-square me-1 text-muted"></i>
                      <a href="{{ e($sig->url) }}" target="_blank" rel="noopener noreferrer">{{ e($sig->url) }}</a>
                    </span>
                  @endif
                  @if(!empty($sig->source))
                    <span class="text-muted"><i class="fas fa-tag me-1"></i>{{ e($sig->source) }}</span>
                  @endif
                  @if($when)
                    <span class="text-muted"><i class="fas fa-clock me-1"></i>{{ \Carbon\Carbon::parse($when)->format('j M Y, H:i') }}</span>
                  @endif
                </div>
              </li>
            @endforeach
          </ul>
        </div>
      @endif
    @endforeach
  @endif
@endif
@endsection
