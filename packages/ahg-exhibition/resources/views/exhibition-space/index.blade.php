{{--
  heratio#exhibitions-index - PUBLIC "Explore our exhibitions" landing.

  One page that lists every public exhibition space so a visitor can find them
  all in one place (until now each space was only reachable by its own URL).
  Each card offers the visitor-facing actions for that space: walk through, find
  your way (wayfinding) and take the catalogue. Read-only. Every action link is
  gated on Route::has() AND the space carrying a slug, so a feature that is not
  wired in a given install - or a space with no slug - never produces a dead link.

  International / jurisdiction-neutral copy. Self-hosted assets only (no CDN):
  Font Awesome + Bootstrap 5 come from the central theme bundle.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Explore our exhibitions'))
@section('body-class', 'exhibition-space exhibitions-index')

@section('content')
  @php
    $hasWalkthrough = !empty($links['walkthrough'] ?? false);
    $hasWayfinding  = !empty($links['wayfinding'] ?? false);
    $hasCatalogue   = !empty($links['catalogue'] ?? false);
  @endphp

  <div class="exhibitions-hero rounded-3 border bg-light p-4 p-md-5 mb-4">
    <h1 class="mb-2">
      <i class="fas fa-landmark me-2"></i>{{ __('Explore our exhibitions') }}
    </h1>
    <p class="lead mb-1">{{ __('Step inside, find your way, take the catalogue.') }}</p>
    <p class="text-muted mb-0" style="max-width: 60rem;">
      {{ __('Every public exhibition space, in one place. Choose a space to walk through it in 3D, find your way around with the floor plan, or take its catalogue with you.') }}
    </p>
  </div>

  @if(empty($spaces))
    <div class="alert alert-light border text-center py-5 my-4">
      <p class="h5 text-muted mb-2">
        <i class="far fa-compass me-2"></i>{{ __('No exhibitions are open to visitors yet.') }}
      </p>
      <p class="text-muted small mb-0">
        {{ __('When a curator opens an exhibition space to the public, it will appear here.') }}
      </p>
    </div>
  @else
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
      @foreach($spaces as $sp)
        @php
          $slug = $sp['slug'] ?? '';
          $count = (int) ($sp['object_count'] ?? 0);
          $typeLabel = $sp['space_type'] ?? null;
        @endphp
        <div class="col">
          <div class="card h-100 shadow-sm exhibition-card">
            <div class="card-body d-flex flex-column">
              <h2 class="h5 card-title mb-1">
                {{ $sp['name'] ?: __('Untitled exhibition space') }}
              </h2>

              @if(($sp['building'] ?? null) || ($sp['floor'] ?? null))
                <p class="text-muted small mb-2">
                  <i class="fas fa-map-marker-alt me-1"></i>{{ trim(implode(', ', array_filter([$sp['building'] ?? null, $sp['floor'] ?? null]))) }}
                </p>
              @endif

              @if(!empty($sp['intro']))
                <p class="card-text small flex-grow-1">{{ $sp['intro'] }}</p>
              @else
                <div class="flex-grow-1"></div>
              @endif

              <p class="mb-3">
                @if($count > 0)
                  <span class="badge text-bg-secondary">
                    <i class="fas fa-images me-1"></i>{{ trans_choice(':count object|:count objects', $count, ['count' => number_format($count)]) }}
                  </span>
                @else
                  <span class="badge text-bg-light border text-muted">
                    <i class="far fa-folder-open me-1"></i>{{ __('No objects placed yet') }}
                  </span>
                @endif
              </p>

              <div class="d-grid gap-2 mt-auto">
                @if($slug && $hasWalkthrough)
                  <a href="{{ route('exhibition-space.walkthrough', ['slug' => $slug]) }}" class="btn btn-primary">
                    <i class="fas fa-vr-cardboard me-1"></i>{{ __('Walk through') }}
                  </a>
                @endif

                @if($slug && ($hasWayfinding || $hasCatalogue))
                  <div class="d-flex gap-2">
                    @if($hasWayfinding)
                      <a href="{{ route('exhibition-space.wayfinding', ['slug' => $slug]) }}" class="btn btn-outline-primary flex-fill">
                        <i class="fas fa-map-location-dot me-1"></i>{{ __('Wayfinding') }}
                      </a>
                    @endif
                    @if($hasCatalogue)
                      <a href="{{ route('exhibition-space.catalogue', ['slug' => $slug]) }}" class="btn btn-outline-primary flex-fill">
                        <i class="fas fa-book-open me-1"></i>{{ __('Catalogue') }}
                      </a>
                    @endif
                  </div>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
