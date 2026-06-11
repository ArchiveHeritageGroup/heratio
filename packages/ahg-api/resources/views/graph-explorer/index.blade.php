{{--
  Graph Explorer - landing page (north-star #1204, next slice).

  A human entry point into the open linked-data graph. A visitor can search for
  a record, a person / organisation, or a subject / place / genre, or pick one
  of a few high-degree starting entities, then walk the graph hop by hop from
  the entity page (graph-explorer/show.blade.php).

  Everything here is PUBLIC, READ-ONLY and covers PUBLISHED records only. Links
  are built from url() in the controller, so no host is ever hardcoded. The page
  never 500s: an empty catalogue simply shows a friendly empty-state.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column theme layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Graph explorer'))

@section('content')
<div class="container py-4" style="max-width:960px">

  <header class="mb-4 text-center">
    <h1 class="mb-2"><i class="fas fa-project-diagram me-2 text-muted"></i>{{ __('Graph explorer') }}</h1>
    <p class="lead text-muted mb-2" style="max-width:720px;margin:0 auto">
      {{ __('Walk the collection as a connected graph. Start anywhere, then follow the links between records, people, places and subjects, one hop at a time.') }}
    </p>
    <p class="small text-muted mb-0">
      <i class="fas fa-lock-open me-1"></i>{{ __('No key required') }}
      <span class="mx-2">&middot;</span>
      <i class="fas fa-eye me-1"></i>{{ __('Read-only, published records only') }}
    </p>
  </header>

  <form method="get" action="{{ url('/graph-explorer') }}" class="mb-4" role="search">
    <div class="input-group input-group-lg shadow-sm">
      <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
      <input type="text" name="q" value="{{ $query }}" class="form-control"
             placeholder="{{ __('Search records, people, places, subjects...') }}"
             aria-label="{{ __('Search the graph') }}" autocomplete="off">
      <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
    </div>
  </form>

  @php
    $typeBadge = [
      'record' => ['label' => __('Record'), 'class' => 'bg-primary-subtle text-primary', 'icon' => 'fas fa-file-alt'],
      'actor'  => ['label' => __('Agent'),  'class' => 'bg-success-subtle text-success', 'icon' => 'fas fa-user'],
      'term'   => ['label' => __('Concept'),'class' => 'bg-info-subtle text-info',       'icon' => 'fas fa-tag'],
    ];
  @endphp

  @if($query !== '')
    <h2 class="h5 mb-3">{{ __('Results for') }} &ldquo;{{ $query }}&rdquo;</h2>
    @if(empty($results))
      <div class="alert alert-info" role="note">
        <i class="fas fa-info-circle me-1"></i>{{ __('No matching entities were found. Try a different word, or browse a starting point below.') }}
        <a href="{{ url('/graph-explorer') }}" class="alert-link ms-1">{{ __('Clear search') }}</a>
      </div>
    @else
      <div class="list-group shadow-sm mb-4">
        @foreach($results as $item)
          @php $b = $typeBadge[$item['type']] ?? $typeBadge['record']; @endphp
          @if(!empty($item['url']))
            <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action d-flex align-items-center">
              <span class="badge {{ $b['class'] }} me-3"><i class="{{ $b['icon'] }} me-1"></i>{{ $b['label'] }}</span>
              <span class="flex-grow-1">{{ $item['label'] }}</span>
              <i class="fas fa-chevron-right text-muted small"></i>
            </a>
          @else
            <span class="list-group-item d-flex align-items-center text-muted">
              <span class="badge {{ $b['class'] }} me-3"><i class="{{ $b['icon'] }} me-1"></i>{{ $b['label'] }}</span>
              <span class="flex-grow-1">{{ $item['label'] }}</span>
            </span>
          @endif
        @endforeach
      </div>
    @endif
  @else
    <h2 class="h5 mb-3"><i class="fas fa-star text-warning me-2"></i>{{ __('Starting points') }}</h2>
    @if(empty($starting))
      <div class="alert alert-info" role="note">
        <i class="fas fa-info-circle me-1"></i>{{ __('No published, connected records are available yet. Once records are published and linked, they will appear here as starting points.') }}
      </div>
    @else
      <p class="text-muted small mb-3">{{ __('These richly-connected records are good places to begin walking the graph.') }}</p>
      <div class="row row-cols-1 row-cols-md-2 g-3">
        @foreach($starting as $item)
          <div class="col">
            <a href="{{ $item['url'] }}" class="card h-100 shadow-sm text-decoration-none">
              <div class="card-body d-flex align-items-center">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light me-3"
                      style="width:2.5rem;height:2.5rem">
                  <i class="fas fa-file-alt text-primary"></i>
                </span>
                <span class="flex-grow-1">
                  <span class="d-block text-body">{{ $item['label'] }}</span>
                  @if(!empty($item['degree']))
                    <span class="small text-muted">{{ $item['degree'] }} {{ __('connections') }}</span>
                  @endif
                </span>
                <i class="fas fa-chevron-right text-muted small"></i>
              </div>
            </a>
          </div>
        @endforeach
      </div>
    @endif
  @endif

  <hr class="my-4">
  <p class="small text-muted text-center mb-0">
    <i class="fas fa-database me-1"></i>{{ __('Every page here also links to its machine-readable linked-data view') }}
    (<code>/id/...</code> - JSON-LD, Turtle, RDF/XML).
    @if(\Illuminate\Support\Facades\Route::has('open-data.index'))
      <a href="{{ url('/open-data') }}" class="ms-1">{{ __('Open data and APIs') }}</a>
    @endif
  </p>

</div>
@endsection
