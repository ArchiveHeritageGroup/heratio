{{--
  Research Living Field Alerts - per-project alerts panel (heratio#1235, Research OS Stage 3)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Alerts on the works this project cites: retractions (prominent / red),
  corrections and new versions (updates), and new related work. Fed by the
  scheduled ahg:research-field-alerts command which polls the public Crossref
  and OpenAlex APIs directly. Empty-states throughout; never 500.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Field Alerts')
@section('title-block')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="h2 mb-0">
    <i class="fas fa-satellite-dish text-primary me-2"></i>{{ __('Field Alerts') }}
    @if(($unread ?? 0) > 0)
      <span class="badge bg-danger align-middle ms-1">{{ $unread }} {{ __('unread') }}</span>
    @endif
  </h1>
  <a href="{{ route('research.alerts.watches', $project->id) }}" class="btn btn-outline-secondary">
    <i class="fas fa-binoculars me-1"></i>{{ __('Watch list') }}
  </a>
</div>
@endsection

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Field Alerts') }}</li>
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
  {{ __('Living alerts on the works this project cites. Heratio watches each cited DOI and flags it when it is retracted, corrected or superseded, or when a new related work appears. Retractions are shown first and in red - check every claim that relies on a retracted source.') }}
</p>

@php
  $types      = $types ?? \AhgResearch\Services\FieldAlertService::TYPES;
  $counts     = $counts ?? [];
  $alerts     = $alerts ?? [];
  $activeType = $activeType ?? '';
  $totalCount = collect($counts)->sum();
@endphp

{{-- Type filter chips + mark-all-read --}}
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
  <a href="{{ route('research.alerts.index', $project->id) }}"
     class="btn btn-sm {{ $activeType === '' ? 'btn-secondary' : 'btn-outline-secondary' }}">
    {{ __('All') }} <span class="badge bg-light text-dark ms-1">{{ $totalCount }}</span>
  </a>
  @foreach($types as $code => $meta)
    @php $n = (int) ($counts[$code] ?? 0); @endphp
    <a href="{{ route('research.alerts.index', ['projectId' => $project->id, 'type' => $code]) }}"
       class="btn btn-sm {{ $activeType === $code ? 'btn-secondary' : 'btn-outline-secondary' }}"
       style="{{ $activeType === $code ? 'background-color:'.$meta['color'].';border-color:'.$meta['color'] : 'border-color:'.$meta['color'].';color:'.$meta['color'] }}">
      <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ __($meta['label']) }}
      <span class="badge bg-light text-dark ms-1">{{ $n }}</span>
    </a>
  @endforeach

  @if(($unread ?? 0) > 0)
    <form method="POST" action="{{ route('research.alerts.read-all', $project->id) }}" class="ms-auto">
      @csrf
      <button type="submit" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-check-double me-1"></i>{{ __('Mark all read') }}
      </button>
    </form>
  @endif
</div>

@forelse($alerts as $alert)
  @php
    $meta   = $types[$alert->alert_type] ?? ['label' => ucfirst(str_replace('_',' ', (string) $alert->alert_type)), 'color' => '#6c757d', 'icon' => 'bell'];
    $when   = $alert->detected_at ?? $alert->created_at ?? null;
    $isRetr = ($alert->alert_type ?? '') === 'retraction';
    $unreadRow = empty($alert->is_read);
  @endphp
  <div class="card mb-2 border-start border-3 {{ $isRetr ? 'bg-danger-subtle' : '' }}"
       style="border-left-color: {{ $meta['color'] }} !important;">
    <div class="card-body py-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div class="flex-grow-1">
          <span class="badge mb-1" style="background-color: {{ $meta['color'] }};">
            <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ __($meta['label']) }}
          </span>
          @if($unreadRow)
            <span class="badge bg-secondary mb-1">{{ __('Unread') }}</span>
          @endif
          <div class="fw-bold {{ $isRetr ? 'text-danger' : '' }}">{{ e($alert->title ?: __('Field alert')) }}</div>
          @if(!empty($alert->detail))
            <div class="text-muted mt-1" style="white-space: pre-line;">{{ e($alert->detail) }}</div>
          @endif
          @if(!empty($alert->url))
            <div class="small mt-1">
              <i class="fas fa-up-right-from-square me-1 text-muted"></i>
              <a href="{{ e($alert->url) }}" target="_blank" rel="noopener noreferrer">{{ e($alert->url) }}</a>
            </div>
          @endif
          <div class="small text-muted mt-2">
            @if($when)
              <i class="fas fa-clock me-1"></i>{{ \Carbon\Carbon::parse($when)->format('j M Y, H:i') }}
            @endif
          </div>
        </div>
        @if($unreadRow)
        <form method="POST" action="{{ route('research.alerts.read', ['projectId' => $project->id, 'id' => $alert->id]) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Mark read') }}">
            <i class="fas fa-check"></i>
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>
@empty
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-satellite-dish fa-3x mb-3 d-block opacity-50"></i>
      <p class="mb-1 fw-bold">{{ __('No field alerts yet.') }}</p>
      <p class="mb-3">{{ __('Heratio checks the works this project cites every day. When one is retracted, corrected or has new related work, it will appear here. Add the works you cite to the watch list to get started.') }}</p>
      <a href="{{ route('research.alerts.watches', $project->id) }}" class="btn btn-outline-primary">
        <i class="fas fa-binoculars me-1"></i>{{ __('Open the watch list') }}
      </a>
    </div>
  </div>
@endforelse
@endsection
