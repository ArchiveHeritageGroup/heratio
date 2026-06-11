{{--
  heratio#1206 - "Reconstructions: walk through what no longer exists".
  Public gallery: every catalogue record (a lost / destroyed / no-longer-extant
  place) linked to a walkable exhibition-space twin, with a walk-in link.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Reconstructions: walk through what no longer exists'))
@section('body-class', 'exhibition-space reconstructions-gallery')

@section('content')
  <div class="mb-3">
    <h1 class="mb-1">
      <i class="fas fa-archway me-2"></i>{{ __('Reconstructions') }}
    </h1>
    <p class="lead mb-1">{{ __('Walk through what no longer exists.') }}</p>
    <p class="text-muted small mb-0" style="max-width: 60rem;">
      {{ __('Each record below describes a place or building that is lost, destroyed or no longer standing. Where the collection holds enough evidence, it has been linked to a virtual reconstruction you can walk through.') }}
    </p>
    <p class="text-muted small fst-italic mt-2 mb-0" style="max-width: 60rem;">
      {{ __('A reconstruction is a virtual reconstruction for interpretation. It is one informed reading of the evidence, not a claim about the original\'s exact appearance.') }}
    </p>
    <p class="mt-2 mb-0">
      <a href="{{ route('reconstruction.demo') }}" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-play-circle me-1"></i>{{ __('See how it works (demonstration)') }}
      </a>
    </p>
  </div>

  @if(empty($reconstructions))
    <div class="alert alert-light border text-center py-5 my-4">
      <p class="h5 text-muted mb-2">
        <i class="far fa-compass me-2"></i>{{ __('No reconstructions have been published yet.') }}
      </p>
      <p class="text-muted small mb-0">
        {{ __('When a curator links a record about a lost place to a walkable reconstruction, it will appear here.') }}
      </p>
    </div>
  @else
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mt-1">
      @foreach($reconstructions as $r)
        <div class="col">
          <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column">
              <h2 class="h5 card-title mb-1">
                {{ $r->record_title ?: __('Untitled record') }}
              </h2>
              <p class="text-muted small mb-2">
                <i class="fas fa-map-marker-alt me-1"></i>{{ __('A lost place') }}
                @if($r->space_name)
                  &middot; {{ $r->space_name }}
                @endif
              </p>
              @if($r->note)
                <p class="card-text small flex-grow-1">{{ $r->note }}</p>
              @else
                <div class="flex-grow-1"></div>
              @endif
              <div class="d-grid gap-2 mt-2">
                <a href="{{ route('reconstruction.show', $r->id) }}" class="btn btn-primary">
                  <i class="fas fa-play me-1"></i>{{ __('Watch it rebuild') }}
                </a>
                @if($r->space_slug)
                  <a href="{{ route('exhibition-space.walkthrough', $r->space_slug) }}"
                     class="btn btn-outline-primary">
                    <i class="fas fa-walking me-1"></i>{{ __('Walk the reconstruction') }}
                  </a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
