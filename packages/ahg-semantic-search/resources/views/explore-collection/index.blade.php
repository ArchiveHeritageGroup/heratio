{{--
  Explore the collection - public discovery hub.

  One coherent public entry point into the browse-by surfaces this package ships:
  Explore by theme, Browse by place, People and organisations, and the Collection
  timeline. This page is a HUB - it shows a small teaser from each (drawn READ-ONLY
  from the existing slice services) and links onward to the full slice page.

  Each section is Route::has-gated in the controller (passed here as *Enabled
  flags), so a section appears only when that slice is installed and every onward
  link resolves - never a dead link. When every slice is empty, a calm
  "exploration tools are warming up" state is shown instead.

  Read-only; published records only; international, jurisdiction-neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Explore the collection'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero / intro --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-compass fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Explore the collection') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('Ways to explore the collection - start from a theme, a place, a person, or a period rather than a search box.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('These are different doors into the same holdings. Each panel below is a small taste of one way in; follow it through to browse everything behind that door. All of it is drawn from the published collection and updates automatically as records are described.') }}
        </p>
    </div>

    @if(! $hasAny)
        {{-- Calm warming-up state: nothing themed/placed/credited/dated yet, or no
             slice installed. Never an error - the hub simply has nothing to tease. --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-compass fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('Exploration tools are warming up') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('Ways into the collection appear here as published records are described with subjects, places, creators and dates. As the catalogue grows and records are made public, these doors open automatically.') }}
                </p>
            </div>
        </div>
    @else

        {{-- Top themes ------------------------------------------------------- --}}
        @if($themesEnabled && ! empty($themes))
            <section class="mb-5">
                <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
                    <div>
                        <h2 class="h4 mb-1">
                            <i class="fas fa-shapes me-2 text-primary"></i>{{ __('Explore by theme') }}
                        </h2>
                        <p class="text-muted small mb-0">
                            {{ __('The collection strongest subjects - the topics the most published records are about.') }}
                        </p>
                    </div>
                    <a href="{{ route('themes.index') }}" class="btn btn-sm btn-outline-primary flex-shrink-0">
                        {{ __('Browse all themes') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="row g-3">
                    @foreach($themes as $theme)
                        @php $records = (int) ($theme['record_count'] ?? 0); @endphp
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex justify-content-between align-items-start gap-2">
                                    <div class="me-2">
                                        <div class="text-uppercase text-muted small fw-semibold">
                                            <i class="fas fa-tag me-1 text-primary"></i>{{ __('Theme') }}
                                        </div>
                                        <h3 class="h6 mb-0">
                                            @if(Route::has('themes.show'))
                                                <a href="{{ route('themes.show', ['termId' => $theme['term_id']]) }}" class="text-decoration-none">{{ $theme['label'] }}</a>
                                            @else
                                                {{ $theme['label'] }}
                                            @endif
                                        </h3>
                                    </div>
                                    <span class="badge rounded-pill bg-primary flex-shrink-0" title="{{ __('Published records under this theme') }}">
                                        {{ number_format($records) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Top places ------------------------------------------------------- --}}
        @if($placesEnabled && ! empty($places))
            <section class="mb-5">
                <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
                    <div>
                        <h2 class="h4 mb-1">
                            <i class="fas fa-map-location-dot me-2 text-success"></i>{{ __('Browse by place') }}
                        </h2>
                        <p class="text-muted small mb-0">
                            {{ __('The places the most published records are about.') }}
                        </p>
                    </div>
                    <a href="{{ route('places.index') }}" class="btn btn-sm btn-outline-success flex-shrink-0">
                        {{ __('Browse all places') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($places as $place)
                        @php $records = (int) ($place['record_count'] ?? 0); @endphp
                        @if(Route::has('places.show'))
                            <a href="{{ route('places.show', ['termId' => $place['term_id']]) }}"
                               class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center">
                                <i class="fas fa-location-dot me-1 text-success"></i>{{ $place['label'] }}
                                <span class="badge rounded-pill bg-success ms-2">{{ number_format($records) }}</span>
                            </a>
                        @else
                            <span class="btn btn-sm btn-outline-secondary disabled d-inline-flex align-items-center">
                                <i class="fas fa-location-dot me-1 text-success"></i>{{ $place['label'] }}
                                <span class="badge rounded-pill bg-success ms-2">{{ number_format($records) }}</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        {{-- People and organisations ---------------------------------------- --}}
        @if($peopleEnabled && ! empty($people))
            <section class="mb-5">
                <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
                    <div>
                        <h2 class="h4 mb-1">
                            <i class="fas fa-users me-2 text-info"></i>{{ __('People and organisations') }}
                        </h2>
                        <p class="text-muted small mb-0">
                            {{ __('The people and organisations credited with creating the most published records.') }}
                        </p>
                    </div>
                    <a href="{{ route('people.index') }}" class="btn btn-sm btn-outline-info flex-shrink-0">
                        {{ __('Browse all people') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="row g-3">
                    @foreach($people as $creator)
                        @php $records = (int) ($creator['record_count'] ?? 0); @endphp
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="text-uppercase text-muted small fw-semibold mb-1">
                                        <i class="fas fa-user-pen me-1 text-info"></i>{{ __('Creator') }}
                                    </div>
                                    <h3 class="h6 mb-2">
                                        @if(Route::has('people.show'))
                                            <a href="{{ route('people.show', ['actorId' => $creator['actor_id']]) }}" class="text-decoration-none">{{ $creator['name'] }}</a>
                                        @else
                                            {{ $creator['name'] }}
                                        @endif
                                    </h3>
                                    <p class="small text-muted mb-0">
                                        {{ trans_choice('{1}:count published record.|[2,*]:count published records.', $records, ['count' => number_format($records)]) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Timeline strip --------------------------------------------------- --}}
        @if($timelineEnabled && ! empty($timeline))
            <section class="mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-end mb-3 gap-2">
                    <div>
                        <h2 class="h4 mb-1">
                            <i class="fas fa-timeline me-2 text-warning"></i>{{ __('Browse by period') }}
                        </h2>
                        <p class="text-muted small mb-0">
                            {{ __('How the published records spread across time.') }}
                        </p>
                    </div>
                    <a href="{{ route('timeline.index') }}" class="btn btn-sm btn-outline-warning flex-shrink-0">
                        {{ __('See the full timeline') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                @php $maxCount = max(array_map(static fn ($b) => (int) ($b['count'] ?? 0), $timeline)) ?: 1; @endphp
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-end flex-wrap gap-3">
                            @foreach($timeline as $bucket)
                                @php
                                    $count = (int) ($bucket['count'] ?? 0);
                                    $pct = (int) round(($count / $maxCount) * 100);
                                    $pct = max($pct, 6);
                                    $browse = $bucket['browse_url'] ?? null;
                                @endphp
                                <div class="text-center" style="min-width: 4.5rem; flex: 1 1 4.5rem;">
                                    <div class="d-flex align-items-end justify-content-center" style="height: 6rem;">
                                        @if(! empty($browse))
                                            <a href="{{ $browse }}"
                                               class="d-block bg-warning rounded-top"
                                               style="width: 1.75rem; height: {{ $pct }}%;"
                                               title="{{ $bucket['period_label'] }} - {{ trans_choice('{1}:count record|[2,*]:count records', $count, ['count' => number_format($count)]) }}"></a>
                                        @else
                                            <span class="d-block bg-secondary rounded-top"
                                                  style="width: 1.75rem; height: {{ $pct }}%;"
                                                  title="{{ $bucket['period_label'] }} - {{ trans_choice('{1}:count record|[2,*]:count records', $count, ['count' => number_format($count)]) }}"></span>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-1 text-truncate">{{ $bucket['period_label'] }}</div>
                                    <div class="small fw-semibold">{{ number_format($count) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Machine-readable twin + closing note --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
            <p class="text-muted small mb-0">
                <i class="fas fa-circle-info me-1"></i>
                {{ __('Each panel is a small sample. Follow any "browse all" link to explore everything behind that door.') }}
            </p>
            <a href="{{ url('/explore-collection.json') }}" class="text-decoration-none small" rel="nofollow">
                <i class="fas fa-code me-1"></i>{{ __('Hub data (JSON)') }}
            </a>
        </div>

    @endif

</div>
@endsection
