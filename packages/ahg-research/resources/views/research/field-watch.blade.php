{{--
  Research Living Field Alerts - per-project watch list (heratio#1235, Research OS Stage 3)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  The works this project watches: cited DOIs auto-seeded read-only from the
  bibliography, plus any added by hand. The scheduled ahg:research-field-alerts
  command polls Crossref/OpenAlex for each. Empty-state friendly; never 500.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Watch list')
@section('title-block')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="h2 mb-0"><i class="fas fa-binoculars text-primary me-2"></i>{{ __('Watch list') }}</h1>
  <a href="{{ route('research.alerts.index', $project->id) }}" class="btn btn-outline-secondary">
    <i class="fas fa-satellite-dish me-1"></i>{{ __('Field alerts') }}
  </a>
</div>
@endsection

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.alerts.index', $project->id) }}">{{ __('Field Alerts') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Watch list') }}</li>
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
  {{ __('The works Heratio watches for this project. DOIs you cite are added automatically from the project bibliography; you can also watch a work by hand. Each is checked daily against the public Crossref and OpenAlex catalogues for retractions, corrections and new related work.') }}
</p>

@php $watches = $watches ?? []; @endphp

@if($canManage ?? false)
<div class="card mb-3">
  <div class="card-body">
    <h2 class="h6 mb-3"><i class="fas fa-plus me-1 text-primary"></i>{{ __('Watch a work') }}</h2>
    <form method="POST" action="{{ route('research.alerts.watches.add', $project->id) }}" class="row g-2 align-items-end">
      @csrf
      <div class="col-md-5">
        <label class="form-label small" for="watch-doi">{{ __('DOI') }}</label>
        <input type="text" class="form-control" id="watch-doi" name="doi"
               placeholder="{{ __('10.1000/xyz123') }}" maxlength="255">
        <div class="form-text">{{ __('A bare DOI or a doi.org URL. Leave blank to watch by title only.') }}</div>
      </div>
      <div class="col-md-5">
        <label class="form-label small" for="watch-title">{{ __('Title') }}</label>
        <input type="text" class="form-control" id="watch-title" name="title"
               placeholder="{{ __('Title of the work') }}" maxlength="500">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fas fa-binoculars me-1"></i>{{ __('Watch') }}
        </button>
      </div>
    </form>
  </div>
</div>
@endif

@if(count($watches) > 0)
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th scope="col">{{ __('Work') }}</th>
          <th scope="col">{{ __('DOI') }}</th>
          <th scope="col">{{ __('Source') }}</th>
          <th scope="col">{{ __('Last checked') }}</th>
          @if($canManage ?? false)<th scope="col" class="text-end">{{ __('Actions') }}</th>@endif
        </tr>
      </thead>
      <tbody>
        @foreach($watches as $w)
          <tr>
            <td>{{ e($w->title ?: __('(untitled)')) }}</td>
            <td>
              @if(!empty($w->doi))
                <a href="https://doi.org/{{ e($w->doi) }}" target="_blank" rel="noopener noreferrer" class="font-monospace small">{{ e($w->doi) }}</a>
              @else
                <span class="text-muted small">{{ __('none') }}</span>
              @endif
            </td>
            <td>
              @if(($w->source_ref ?? '') === 'bibliography')
                <span class="badge bg-info-subtle text-info-emphasis">{{ __('Bibliography') }}</span>
              @elseif(($w->source_ref ?? '') === 'manual')
                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Manual') }}</span>
              @else
                <span class="text-muted small">{{ e($w->source_ref ?? '') }}</span>
              @endif
            </td>
            <td class="small text-muted">
              @if(!empty($w->last_checked_at))
                {{ \Carbon\Carbon::parse($w->last_checked_at)->format('j M Y, H:i') }}
              @else
                {{ __('not yet') }}
              @endif
            </td>
            @if($canManage ?? false)
            <td class="text-end">
              <form method="POST" action="{{ route('research.alerts.watches.remove', ['projectId' => $project->id, 'id' => $w->id]) }}"
                    onsubmit="return confirm('{{ __('Stop watching this work?') }}');" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@else
  <div class="card">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-binoculars fa-3x mb-3 d-block opacity-50"></i>
      <p class="mb-1 fw-bold">{{ __('Nothing on the watch list yet.') }}</p>
      <p class="mb-0">{{ __('Add DOIs to this project bibliography and they will appear here automatically, or watch a work by hand using the form above.') }}</p>
    </div>
  </div>
@endif
@endsection
