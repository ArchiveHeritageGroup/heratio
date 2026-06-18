{{--
  Public "Explore" hub. One jurisdiction-neutral landing page that makes the
  collection's public capabilities discoverable in one place - ask it questions,
  read it in your language, verify its authenticity, walk its reconstructions,
  and see how it all connects.

  Cards are built in ExploreController from Route::has(...) checks, so each card is
  only present when its feature's package is installed. Every link is therefore
  live - a missing feature simply leaves a smaller grid, never a dead link.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Explore this collection'))

{{-- heratio#1211 - apply the visitor's accessibility preferences on first paint
     (no JS needed). The AccessibilityPreferences middleware shares the resolved
     body-class string; the always-on applier script keeps it in sync site-wide. --}}
@section('body-class', $ahgA11yBodyClass ?? '')

@section('content')
<div class="container py-4" style="max-width:1040px">

  <header class="mb-4 text-center">
    <h1 class="mb-2"><i class="fas fa-compass me-2 text-muted"></i>{{ __('Explore this collection') }}</h1>
    <p class="lead text-muted mb-0" style="max-width:760px;margin:0 auto">
      {{ __('Ask it questions, read it in your language, walk its exhibitions in 3D, verify its authenticity, and see how it all connects.') }}
    </p>
  </header>

  {{-- heratio#1211 - "every museum for everyone": a small, public, no-account
       panel that lets any visitor choose their reading language and reading-
       comfort preferences and have them remembered (session + 1-year cookie),
       applied across the site. Works without JavaScript. --}}
  <section class="card border-0 shadow-sm mb-4" aria-labelledby="ahg-access-heading">
    <div class="card-body">
      <h2 id="ahg-access-heading" class="h6 text-uppercase text-muted mb-3">
        <i class="fas fa-universal-access me-1" aria-hidden="true"></i>{{ __('Make this collection easier to use') }}
      </h2>
      <div class="row g-3 align-items-start">
        <div class="col-12 col-lg-7">
          @includeIf('ahg-core::partials.reading-language-picker', ['rlpRedirect' => request()->getRequestUri()])
        </div>
        <div class="col-12 col-lg-5">
          @includeIf('ahg-core::partials.accessibility-picker', ['apRedirect' => request()->getRequestUri()])
        </div>
      </div>
      <p class="small text-muted mb-0 mt-3">
        {{ __('Your choices are remembered on this device and applied across the site. No account needed.') }}
      </p>
    </div>
  </section>

  @if(empty($cards))
    <div class="alert alert-info text-center" role="note">
      <i class="fas fa-info-circle me-1"></i>{{ __('No public features are available to explore just yet. Please check back soon.') }}
    </div>
  @else
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      @foreach($cards as $card)
        @if(!empty($card['url']))
          <div class="col">
            <div class="card h-100 shadow-sm">
              <div class="card-body d-flex flex-column">
                <div class="mb-3">
                  <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light"
                        style="width:3rem;height:3rem">
                    <i class="{{ $card['icon'] }} fs-4 text-primary"></i>
                  </span>
                </div>
                <h2 class="h5 card-title">{{ $card['title'] }}</h2>
                <p class="card-text text-muted flex-grow-1">{{ $card['desc'] }}</p>

                @if(!empty($card['developer']))
                  {{-- Open-data / developer card: show a copyable example endpoint --}}
                  <div class="bg-light border rounded p-2 mb-3">
                    <div class="small text-muted mb-1">{{ __('Example') }}</div>
                    <code class="small text-break d-block">GET {{ $card['url'] }}</code>
                  </div>
                @elseif(!empty($card['note']))
                  <p class="small text-muted fst-italic mb-3">
                    <i class="fas fa-lightbulb me-1"></i>{{ $card['note'] }}
                  </p>
                @endif

                <div class="mt-auto">
                  <a href="{{ $card['url'] }}" class="btn btn-outline-primary w-100"
                     @if(!empty($card['developer'])) rel="noopener" @endif>
                    {{ $card['cta'] ?? __('Open') }}
                    <i class="fas fa-arrow-right ms-1"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        @endif
      @endforeach
    </div>
  @endif

</div>
@endsection
