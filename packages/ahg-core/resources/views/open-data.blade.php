{{--
  Public "Open Data & APIs" hub. One jurisdiction-neutral landing page that gathers
  every open-data surface this platform exposes for researchers and developers -
  the linked-data graph, bulk dataset dumps, OAI-PMH harvesting, the VoID discovery
  document, the API reference, the content-credentials (C2PA) API, the RiC SPARQL
  endpoint, and ResourceSync.

  Cards are built in OpenDataController from Route::has(...) checks, so each card is
  only present when its feature's package is installed and at least one of its
  endpoints resolves. Every link is therefore live - a missing surface simply
  leaves a smaller grid, never a dead link, and the empty grid shows a friendly
  empty-state rather than 500-ing.

  Everything advertised here is PUBLIC (no API key), READ-ONLY, covers PUBLISHED
  records only, and is open data under CC-BY-4.0. Examples use url('/...') so no
  internal host is ever hardcoded; where a host placeholder is needed we use
  your-site.example.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Open data and APIs'))

@section('content')
<div class="container py-4" style="max-width:1040px">

  <header class="mb-4 text-center">
    <h1 class="mb-2"><i class="fas fa-database me-2 text-muted"></i>{{ __('Open data and APIs') }}</h1>
    <p class="lead text-muted mb-2" style="max-width:760px;margin:0 auto">
      {{ __('Take this collection as data. Every surface below is open, needs no API key, and covers published records only - for researchers, developers, aggregators, and linked-data clients.') }}
    </p>
    <p class="small text-muted mb-0">
      <i class="fab fa-creative-commons me-1"></i>{{ __('Licensed CC-BY-4.0') }}
      <span class="mx-2">&middot;</span>
      <i class="fas fa-lock-open me-1"></i>{{ __('No key required') }}
      <span class="mx-2">&middot;</span>
      <i class="fas fa-eye me-1"></i>{{ __('Read-only, published records only') }}
    </p>
  </header>

  @if(empty($cards))
    <div class="alert alert-info text-center" role="note">
      <i class="fas fa-info-circle me-1"></i>{{ __('No open-data endpoints are available just yet. Please check back soon.') }}
    </div>
  @else
    <div class="row row-cols-1 row-cols-md-2 g-4">
      @foreach($cards as $card)
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
              <p class="card-text text-muted">{{ $card['desc'] }}</p>

              @if(!empty($card['format']))
                <p class="small mb-2">
                  <span class="badge bg-light text-dark border">
                    <i class="fas fa-file-code me-1"></i>{{ $card['format'] }}
                  </span>
                </p>
              @endif

              <ul class="list-unstyled small mb-3 flex-grow-1">
                @foreach($card['endpoints'] as $endpoint)
                  <li class="mb-2">
                    <div class="text-muted">{{ $endpoint['label'] }}</div>
                    @if(!empty($endpoint['pattern']))
                      {{-- Illustrative URL pattern (contains a placeholder): show as
                           code, not a clickable link, since the id must be supplied. --}}
                      <code class="text-break d-block">{{ $endpoint['url'] }}</code>
                    @else
                      <a href="{{ $endpoint['url'] }}" class="text-break d-block" rel="noopener">
                        <code>{{ $endpoint['url'] }}</code>
                      </a>
                    @endif
                  </li>
                @endforeach
              </ul>

              <p class="small text-muted mb-0 mt-auto">
                <i class="fas fa-lock-open me-1"></i>{{ __('Open, no key. Published records only. CC-BY-4.0.') }}
              </p>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- Worked example: content negotiation against the graph front door. Shown only
       when the graph endpoint actually resolves. Uses the resolved URL (never a
       hardcoded host) so a copy-paste works against whatever host serves it. --}}
  @if(!empty($graphUrl))
    <section class="mt-5">
      <h2 class="h5 mb-2"><i class="fas fa-terminal me-2 text-muted"></i>{{ __('Try it') }}</h2>
      <p class="text-muted small mb-2">
        {{ __('Ask the linked-data graph for Turtle instead of the default JSON-LD by setting the Accept header:') }}
      </p>
      <div class="bg-dark text-light rounded p-3">
        <code class="text-break d-block" style="white-space:pre-wrap">curl -H "Accept: text/turtle" {{ $graphUrl }}</code>
      </div>
      <p class="text-muted small mt-2 mb-0">
        {{ __('Swap the header for application/ld+json (JSON-LD) or application/rdf+xml (RDF/XML), or append a .ttl / .jsonld / .rdf suffix to a per-record URL.') }}
      </p>
    </section>
  @endif

  {{-- How to cite / license. --}}
  <section class="mt-5">
    <h2 class="h5 mb-2"><i class="fas fa-balance-scale me-2 text-muted"></i>{{ __('How to cite and license') }}</h2>
    <p class="text-muted small mb-2">
      {{ __('This data is published under the Creative Commons Attribution 4.0 International licence (CC-BY-4.0). You are free to copy, redistribute, transform, and build on it for any purpose, including commercially, provided you give appropriate credit.') }}
    </p>
    <p class="text-muted small mb-0">
      {{ __('Please cite the holding institution and link back to the record or dataset URL you used. Example: "<Institution name>, <Collection or record title>, retrieved from <dataset or record URL>, licensed CC-BY-4.0."') }}
    </p>
  </section>

  <div class="text-center mt-5">
    @if(Route::has('explore.index'))
      <a href="{{ route('explore.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-compass me-1"></i>{{ __('Explore this collection') }}
      </a>
    @endif
  </div>

</div>
@endsection
