{{--
  Research Decision Log - per-project timeline (heratio#1224, Research OS Stage 9)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  The recorded memory of every loop: scope changes, exclusions, hypothesis
  revisions, method pivots, question reformulations and supervisor instructions
  acted on - each with its reason. Distinct from the system activity log.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Decision Log')
@section('title-block')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="h2 mb-0"><i class="fas fa-clipboard-list text-primary me-2"></i>{{ __('Decision Log') }}</h1>
  @if($canEdit ?? false)
  <a href="{{ route('research.decisions.create', $project->id) }}" class="btn btn-primary">
    <i class="fas fa-plus me-1"></i>{{ __('Record a decision') }}
  </a>
  @endif
</div>
@endsection

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Decision Log') }}</li>
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
  {{ __('The recorded memory of every loop in this project: every scope change, exclusion, revised hypothesis, method pivot, question reformulation and supervisor instruction acted on - each with its reason. This is the trail of your thinking, separate from the system activity log. It answers "why did you exclude X?" with receipts and feeds your limitations section.') }}
</p>

@php
  $types      = $types ?? [];
  $counts     = $counts ?? [];
  $entries    = $entries ?? [];
  $activeType = $activeType ?? '';
  $totalCount = collect($counts)->sum();
@endphp

{{-- Type filter chips --}}
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="{{ route('research.decisions.index', $project->id) }}"
     class="btn btn-sm {{ $activeType === '' ? 'btn-secondary' : 'btn-outline-secondary' }}">
    {{ __('All') }} <span class="badge bg-light text-dark ms-1">{{ $totalCount }}</span>
  </a>
  @foreach($types as $code => $meta)
    @php $n = (int) ($counts[$code] ?? 0); @endphp
    <a href="{{ route('research.decisions.index', ['projectId' => $project->id, 'type' => $code]) }}"
       class="btn btn-sm {{ $activeType === $code ? 'btn-secondary' : 'btn-outline-secondary' }}"
       style="{{ $activeType === $code ? 'background-color:'.$meta['color'].';border-color:'.$meta['color'] : 'border-color:'.$meta['color'].';color:'.$meta['color'] }}">
      <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
      <span class="badge bg-light text-dark ms-1">{{ $n }}</span>
    </a>
  @endforeach
</div>

@forelse($entries as $entry)
  @php
    $meta  = $types[$entry->decision_type] ?? ['label' => ucfirst(str_replace('_',' ', (string) $entry->decision_type)), 'color' => '#6c757d', 'icon' => 'circle-dot'];
    $when  = $entry->decided_at ?? $entry->created_at ?? null;
  @endphp
  <div class="card mb-2 border-start border-3" style="border-left-color: {{ $meta['color'] }} !important;">
    <div class="card-body py-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div class="flex-grow-1">
          <span class="badge mb-1" style="background-color: {{ $meta['color'] }};">
            <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
          </span>
          <div class="fw-bold">{{ e($entry->summary) }}</div>
          @if(!empty($entry->reason))
            <div class="text-muted mt-1" style="white-space: pre-line;">{{ e($entry->reason) }}</div>
          @endif
          @if(!empty($entry->related_ref))
            <div class="small mt-1"><i class="fas fa-link me-1 text-muted"></i><span class="text-muted">{{ __('Related') }}:</span> {{ e($entry->related_ref) }}</div>
          @endif
          <div class="small text-muted mt-2">
            <i class="fas fa-user me-1"></i>{{ e($entry->decided_by ?: __('Unknown')) }}
            @if($when)
              <span class="mx-1">&middot;</span>
              <i class="fas fa-clock me-1"></i>{{ \Carbon\Carbon::parse($when)->format('j M Y, H:i') }}
            @endif
          </div>
        </div>
        @if($canEdit ?? false)
        <div class="d-flex gap-1">
          <a href="{{ route('research.decisions.edit', ['projectId' => $project->id, 'id' => $entry->id]) }}"
             class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}">
            <i class="fas fa-pen"></i>
          </a>
          <form method="POST" action="{{ route('research.decisions.destroy', ['projectId' => $project->id, 'id' => $entry->id]) }}"
                onsubmit="return confirm('{{ __('Remove this decision from the log? This cannot be undone.') }}');" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
              <i class="fas fa-trash"></i>
            </button>
          </form>
        </div>
        @endif
      </div>
    </div>
  </div>
@empty
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-clipboard-list fa-3x mb-3 d-block opacity-50"></i>
      <p class="mb-1 fw-bold">{{ __('No decisions recorded yet - the log is the memory of every loop.') }}</p>
      <p class="mb-3">{{ __('Record your first scope change, exclusion, hypothesis revision or method pivot to start the trail.') }}</p>
      @if($canEdit ?? false)
        <a href="{{ route('research.decisions.create', $project->id) }}" class="btn btn-primary">
          <i class="fas fa-plus me-1"></i>{{ __('Record a decision') }}
        </a>
      @endif
    </div>
  </div>
@endforelse
@endsection
