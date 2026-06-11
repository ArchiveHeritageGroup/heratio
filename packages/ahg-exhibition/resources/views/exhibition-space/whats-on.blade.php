{{--
  heratio#1192 deepened - PUBLIC "What's on": upcoming + live exhibition openings.

  One page that lists every upcoming and currently-live public opening across all
  exhibition spaces, in start-time order, grouped by calendar date. Each card shows
  the opening's title + host, its exhibition space (name + link), date/time, whether
  it is free or priced, and the seats remaining, with a "Details / RSVP" link to the
  existing tokenised opening page. A "Live now" badge marks a currently-live opening.

  Read-only. Every link is gated on Route::has() AND a real token/slug, so a feature
  that is not wired in a given install - or an event/space with no token/slug - never
  produces a dead link. Empty-state when there is nothing on.

  International / jurisdiction-neutral copy. Self-hosted assets only (no CDN):
  Font Awesome + Bootstrap 5 come from the central theme bundle.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __("What's on"))
@section('body-class', 'exhibition-space whats-on')

@section('content')
  <div class="whats-on-hero rounded-3 border bg-light p-4 p-md-5 mb-4">
    <h1 class="mb-2">
      <i class="far fa-calendar-check me-2"></i>{{ __("What's on") }}
    </h1>
    <p class="lead mb-1">{{ __('Upcoming openings and live events.') }}</p>
    <p class="text-muted mb-0" style="max-width: 60rem;">
      {{ __('Every upcoming and currently-live exhibition opening, in one place. Pick an event to see the details and reserve your place.') }}
    </p>
    @if($hasSpaceIndex)
      <a href="{{ route('exhibition-space.index') }}" class="btn btn-sm btn-outline-secondary mt-3">
        <i class="fas fa-landmark me-1"></i>{{ __('Browse all exhibitions') }}
      </a>
    @endif
  </div>

  @if(empty($events))
    <div class="alert alert-light border text-center py-5 my-4">
      <p class="h5 text-muted mb-2">
        <i class="far fa-calendar me-2"></i>{{ __('No upcoming openings right now.') }}
      </p>
      <p class="text-muted small mb-0">
        {{ __('When a curator schedules a public opening, it will appear here.') }}
      </p>
    </div>
  @else
    @foreach($grouped as $dateLabel => $rows)
      <h2 class="h5 text-muted mt-4 mb-3">
        <i class="far fa-calendar-alt me-2"></i>{{ $dateLabel }}
      </h2>

      <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        @foreach($rows as $ev)
          @php
            $hasDetails = $hasOpeningPublic && !empty($ev['token']);
            $detailsUrl = $hasDetails ? route('exhibition-space.opening-public', ['token' => $ev['token']]) : null;
            $hasSpaceLink = $hasSpaceShow && !empty($ev['space_slug']);
            $spaceUrl = $hasSpaceLink ? route('exhibition-space.show', ['slug' => $ev['space_slug']]) : null;
          @endphp
          <div class="col">
            <div class="card h-100 shadow-sm whats-on-card {{ $ev['is_live'] ? 'border-danger' : '' }}">
              <div class="card-body d-flex flex-column">

                <div class="d-flex justify-content-between align-items-start mb-1">
                  <span class="text-muted small">
                    <i class="far fa-clock me-1"></i>{{ $ev['time_label'] }}
                  </span>
                  @if($ev['is_live'])
                    <span class="badge text-bg-danger">
                      <i class="fas fa-circle me-1" style="font-size:.55em; vertical-align:middle;"></i>{{ __('Live now') }}
                    </span>
                  @endif
                </div>

                <h3 class="h5 card-title mb-1">
                  {{ $ev['title'] }}
                </h3>

                @if(!empty($ev['host_name']))
                  <p class="text-muted small mb-2">
                    <i class="fas fa-user me-1"></i>{{ __('Hosted by :host', ['host' => $ev['host_name']]) }}
                  </p>
                @endif

                <p class="small mb-2">
                  <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                  @if($hasSpaceLink)
                    <a href="{{ $spaceUrl }}">{{ $ev['space_name'] }}</a>
                  @else
                    {{ $ev['space_name'] }}
                  @endif
                </p>

                @if(!empty($ev['description']))
                  <p class="card-text small flex-grow-1">{{ \Illuminate\Support\Str::limit(trim(strip_tags($ev['description'])), 160) }}</p>
                @else
                  <div class="flex-grow-1"></div>
                @endif

                <p class="mb-3 d-flex flex-wrap gap-1">
                  @if($ev['is_paid'] && $ev['price'] !== null)
                    <span class="badge text-bg-primary">
                      <i class="fas fa-ticket-alt me-1"></i>{{ $ev['currency'] }} {{ number_format($ev['price'], 2) }}
                    </span>
                  @else
                    <span class="badge text-bg-success">
                      <i class="fas fa-gift me-1"></i>{{ __('Free') }}
                    </span>
                  @endif

                  @if($ev['sold_out'])
                    <span class="badge text-bg-secondary">
                      <i class="fas fa-ban me-1"></i>{{ __('Fully booked') }}
                    </span>
                  @else
                    <span class="badge text-bg-light border text-muted">
                      <i class="fas fa-chair me-1"></i>{{ trans_choice(':count seat left|:count seats left', $ev['remaining'], ['count' => number_format($ev['remaining'])]) }}
                    </span>
                  @endif
                </p>

                <div class="d-grid mt-auto">
                  @if($hasDetails)
                    <a href="{{ $detailsUrl }}" class="btn {{ $ev['is_live'] ? 'btn-danger' : 'btn-primary' }}">
                      <i class="far fa-calendar-check me-1"></i>{{ $ev['is_live'] ? __('Details / Join') : __('Details / RSVP') }}
                    </a>
                  @else
                    <span class="btn btn-outline-secondary disabled">
                      <i class="fas fa-info-circle me-1"></i>{{ __('Details unavailable') }}
                    </span>
                  @endif
                </div>

              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endforeach
  @endif
@endsection
