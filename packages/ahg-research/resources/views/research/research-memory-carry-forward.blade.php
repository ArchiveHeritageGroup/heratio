{{--
  Research Memory - cross-project carry-forward pool (heratio#1233, ROS Stage 16)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Every open / carried-forward memory item across all of this researcher's
  projects. This is the pool a new project starts from, so the next one starts
  smarter instead of cold.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Carry forward')
@section('title-block')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="h2 mb-0"><i class="fas fa-share-from-square text-primary me-2"></i>{{ __('Carry forward') }}</h1>
  <a href="{{ route('research.projects') }}" class="btn btn-primary">
    <i class="fas fa-plus me-1"></i>{{ __('Start a new project') }}
  </a>
</div>
@endsection

@section('content')
@php
  $kinds  = $kinds ?? [];
  $items  = $items ?? [];
  $counts = $counts ?? [];
  $total  = collect($counts)->sum();
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Carry forward') }}</li>
  </ol>
</nav>

<p class="text-muted">
  {{ __('Your intellectual memory across every project: the open and carried-forward questions, future articles, unused sources, abandoned hypotheses, reusable datasets and leads. Start your next project from these so nothing of value is lost when a project closes.') }}
</p>

@if($total > 0)
  {{-- Kind summary chips. --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($kinds as $code => $meta)
      @php $n = (int) ($counts[$code] ?? 0); @endphp
      @if($n > 0)
        <span class="badge rounded-pill" style="background-color: {{ $meta['color'] }};">
          <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }} {{ $n }}
        </span>
      @endif
    @endforeach
  </div>

  @foreach($items as $item)
    @php $meta = $kinds[$item->kind] ?? ['label' => ucfirst(str_replace('_',' ', (string) $item->kind)), 'color' => '#6c757d', 'icon' => 'circle-dot']; @endphp
    <div class="card mb-2 border-start border-3" style="border-left-color: {{ $meta['color'] }} !important;">
      <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div class="flex-grow-1">
            <span class="badge mb-1" style="background-color: {{ $meta['color'] }};">
              <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
            </span>
            @if($item->status === 'carried_forward')
              <span class="badge bg-primary ms-1"><i class="fas fa-share-from-square me-1"></i>{{ __('Carried forward') }}</span>
            @endif
            <div class="fw-bold">{{ e($item->title) }}</div>
            @if(!empty($item->body))
              <div class="text-muted mt-1" style="white-space: pre-line;">{{ e($item->body) }}</div>
            @endif
            @if(!empty($item->source_ref))
              <div class="small mt-1"><i class="fas fa-link me-1 text-muted"></i><span class="text-muted">{{ __('Source') }}:</span> {{ e($item->source_ref) }}</div>
            @endif
            <div class="small text-muted mt-2">
              @if(!empty($item->project_id))
                <i class="fas fa-folder-open me-1"></i>
                <a href="{{ route('research.memory.index', $item->project_id) }}">{{ e($item->project_title ?? (__('Project') . ' #' . $item->project_id)) }}</a>
              @else
                <i class="fas fa-user me-1"></i>{{ __('Not tied to a project') }}
              @endif
              @php $when = $item->updated_at ?? $item->created_at ?? null; @endphp
              @if($when)
                <span class="mx-1">&middot;</span>
                <i class="fas fa-clock me-1"></i>{{ \Carbon\Carbon::parse($when)->format('j M Y') }}
              @endif
            </div>
          </div>
          <a href="{{ route('research.projects') }}" class="btn btn-sm btn-outline-primary" title="{{ __('Start a new project from this') }}">
            <i class="fas fa-arrow-right me-1"></i>{{ __('Start new project from this') }}
          </a>
        </div>
      </div>
    </div>
  @endforeach
@else
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-share-from-square fa-3x mb-3 d-block opacity-50"></i>
      <p class="mb-1 fw-bold">{{ __('Nothing to carry forward yet.') }}</p>
      <p class="mb-3">{{ __('As you capture memory in your projects - open questions, reusable datasets, leads - the open and carried-forward items gather here for your next project to start from.') }}</p>
      <a href="{{ route('research.projects') }}" class="btn btn-primary">
        <i class="fas fa-folder-open me-1"></i>{{ __('Go to your projects') }}
      </a>
    </div>
  </div>
@endif
@endsection
