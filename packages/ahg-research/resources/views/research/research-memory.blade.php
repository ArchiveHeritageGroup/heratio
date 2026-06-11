{{--
  Research Memory - per-project view (heratio#1233, Research OS Stage 16)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Retains the researcher's intellectual memory after a project so the next one
  starts smarter: unresolved questions, future articles, unused sources,
  abandoned hypotheses, reusable datasets and collaboration / conference / grant
  leads. Curated items are grouped by kind. Read-only suggestions (e.g. from the
  Decision Log) can be accepted into memory - accepting is the only write a
  suggestion produces.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Research Memory')
@section('title-block')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="h2 mb-0"><i class="fas fa-brain text-primary me-2"></i>{{ __('Research Memory') }}</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('research.memory.carryForward') }}" class="btn btn-outline-secondary">
      <i class="fas fa-share-from-square me-1"></i>{{ __('Carry forward') }}
    </a>
    @if($canEdit ?? false)
    <a href="{{ route('research.memory.create', $project->id) }}" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i>{{ __('Add memory item') }}
    </a>
    @endif
  </div>
</div>
@endsection

@section('content')
@php
  $kinds       = $kinds ?? [];
  $statuses    = $statuses ?? [];
  $grouped     = $grouped ?? [];
  $suggestions = $suggestions ?? [];
  $totalItems  = collect($grouped)->map(fn($g) => count($g))->sum();
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Memory') }}</li>
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
  {{ __('What this project leaves behind beyond its findings: the questions it never resolved, the article ideas it spun off, the sources it gathered but never used, the hypotheses it abandoned, the datasets worth reusing, and the collaborations, conferences and grants worth chasing. Capture it here so your next project starts smarter instead of starting cold.') }}
</p>

{{-- Read-only suggestions drawn from existing artefacts (e.g. Decision Log). --}}
@if(($canEdit ?? false) && count($suggestions) > 0)
  <div class="card border-info mb-4">
    <div class="card-header bg-info bg-opacity-10 d-flex align-items-center">
      <i class="fas fa-wand-magic-sparkles text-info me-2"></i>
      <strong>{{ __('Suggested from this project') }}</strong>
      <span class="badge bg-info ms-2">{{ count($suggestions) }}</span>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-3">
        {{ __('Drawn read-only from your Decision Log. Accepting a suggestion copies it into your memory - it never changes the original entry.') }}
      </p>
      @foreach($suggestions as $s)
        @php $meta = $kinds[$s['kind']] ?? ['label' => ucfirst(str_replace('_',' ', $s['kind'])), 'color' => '#6c757d', 'icon' => 'circle-dot']; @endphp
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 border-bottom py-2">
          <div class="flex-grow-1">
            <span class="badge mb-1" style="background-color: {{ $meta['color'] }};">
              <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
            </span>
            <span class="badge bg-light text-dark border ms-1">{{ e($s['origin']) }}</span>
            <div class="fw-bold">{{ e($s['title']) }}</div>
            @if(!empty($s['body']))
              <div class="text-muted small mt-1" style="white-space: pre-line;">{{ e(\Illuminate\Support\Str::limit($s['body'], 240)) }}</div>
            @endif
          </div>
          <form method="POST" action="{{ route('research.memory.accept', $project->id) }}" class="d-inline">
            @csrf
            <input type="hidden" name="signature" value="{{ $s['signature'] }}">
            <button type="submit" class="btn btn-sm btn-outline-info">
              <i class="fas fa-plus me-1"></i>{{ __('Accept into memory') }}
            </button>
          </form>
        </div>
      @endforeach
    </div>
  </div>
@endif

{{-- Curated memory items, grouped by kind. --}}
@if($totalItems > 0)
  @foreach($kinds as $code => $meta)
    @php $items = $grouped[$code] ?? []; @endphp
    @if(count($items) > 0)
      <h2 class="h5 mt-4 mb-2" style="color: {{ $meta['color'] }};">
        <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
        <span class="badge bg-light text-dark border ms-1">{{ count($items) }}</span>
      </h2>
      @foreach($items as $item)
        @php $sMeta = $statuses[$item->status] ?? ['label' => ucfirst(str_replace('_',' ', (string) $item->status)), 'color' => '#6c757d']; @endphp
        <div class="card mb-2 border-start border-3" style="border-left-color: {{ $meta['color'] }} !important;">
          <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div class="flex-grow-1">
                <span class="badge mb-1" style="background-color: {{ $sMeta['color'] }};">{{ $sMeta['label'] }}</span>
                <div class="fw-bold">{{ e($item->title) }}</div>
                @if(!empty($item->body))
                  <div class="text-muted mt-1" style="white-space: pre-line;">{{ e($item->body) }}</div>
                @endif
                @if(!empty($item->source_ref))
                  <div class="small mt-1"><i class="fas fa-link me-1 text-muted"></i><span class="text-muted">{{ __('Source') }}:</span> {{ e($item->source_ref) }}</div>
                @endif
                <div class="small text-muted mt-2">
                  @if(!empty($item->created_by))<i class="fas fa-user me-1"></i>{{ e($item->created_by) }}@endif
                  @php $when = $item->updated_at ?? $item->created_at ?? null; @endphp
                  @if($when)
                    <span class="mx-1">&middot;</span>
                    <i class="fas fa-clock me-1"></i>{{ \Carbon\Carbon::parse($when)->format('j M Y, H:i') }}
                  @endif
                </div>
              </div>
              @if($canEdit ?? false)
              <div class="d-flex gap-1 align-items-start">
                @if($item->status !== 'carried_forward')
                <form method="POST" action="{{ route('research.memory.status', ['projectId' => $project->id, 'id' => $item->id]) }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="status" value="carried_forward">
                  <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('Carry forward to next project') }}">
                    <i class="fas fa-share-from-square"></i>
                  </button>
                </form>
                @endif
                @if($item->status !== 'done')
                <form method="POST" action="{{ route('research.memory.status', ['projectId' => $project->id, 'id' => $item->id]) }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="status" value="done">
                  <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Mark done') }}">
                    <i class="fas fa-check"></i>
                  </button>
                </form>
                @endif
                <a href="{{ route('research.memory.edit', ['projectId' => $project->id, 'id' => $item->id]) }}"
                   class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}">
                  <i class="fas fa-pen"></i>
                </a>
                <form method="POST" action="{{ route('research.memory.destroy', ['projectId' => $project->id, 'id' => $item->id]) }}"
                      onsubmit="return confirm('{{ __('Remove this memory item? This cannot be undone.') }}');" class="d-inline">
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
      @endforeach
    @endif
  @endforeach
@elseif(count($suggestions) === 0)
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-brain fa-3x mb-3 d-block opacity-50"></i>
      <p class="mb-1 fw-bold">{{ __('No memory captured yet.') }}</p>
      <p class="mb-3">{{ __('Record an unresolved question, a future-article idea, an unused source, an abandoned hypothesis or a reusable dataset so your next project does not start cold.') }}</p>
      @if($canEdit ?? false)
        <a href="{{ route('research.memory.create', $project->id) }}" class="btn btn-primary">
          <i class="fas fa-plus me-1"></i>{{ __('Add memory item') }}
        </a>
      @endif
    </div>
  </div>
@endif
@endsection
