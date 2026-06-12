{{--
  Browse by place - public discovery landing (geography slice)

  The published holdings organised by the places they are about: the place terms
  under which the most PUBLISHED records sit. Framed as "ways into the collection
  by geography", each place is a chip in a frequency-sized cloud carrying its
  published-record count and linking to a per-place detail. Read-only; published
  records only; empty-state when nothing is placed yet. International,
  jurisdiction-neutral copy - the place names come entirely from the data.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Browse by place'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-earth-americas fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Browse by place') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('Ways into the collection, grouped by the places the records are about.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Each place is a geographic access point used to describe the published holdings. Start from a place to find related material across the catalogue, rather than from a single search box. The larger the place, the more published records sit under it; each links straight to those records.') }}
        </p>
    </div>

    @if(empty($places))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-map-location-dot fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No places to show yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('Places appear here once published records are described with geographic access points. As records are catalogued and made public, the places they are about will surface automatically as ways into the collection.') }}
                </p>
            </div>
        </div>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <p class="text-muted small mb-0">
                <i class="fas fa-circle-info me-1"></i>
                {{ trans_choice('{1}:count place drawn from the published collection.|[2,*]:count places drawn from the published collection.', $count, ['count' => $count]) }}
            </p>
            <a href="{{ url('/places.json') }}" class="text-decoration-none small" rel="nofollow">
                <i class="fas fa-code me-1"></i>{{ __('Place data (JSON)') }}
            </a>
        </div>

        {{-- Frequency-sized cloud: each chip scales between ~0.9rem and ~1.8rem
             by its share of the busiest place. Ordered by frequency already. --}}
        @php $maxCount = max(1, (int) ($maxCount ?? 1)); @endphp
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center" style="gap: .5rem .75rem; line-height: 2.2;">
                    @foreach($places as $place)
                        @php
                            $records = (int) ($place['record_count'] ?? 0);
                            $share = $records / $maxCount;            // 0..1
                            $size = 0.9 + ($share * 0.9);             // rem
                            $weight = $share >= 0.66 ? 700 : ($share >= 0.33 ? 600 : 500);
                            $detailUrl = $place['url'] ?? route('places.show', ['termId' => $place['term_id']]);
                        @endphp
                        <a href="{{ $detailUrl }}"
                           class="text-decoration-none d-inline-flex align-items-center"
                           style="font-size: {{ number_format($size, 2) }}rem; font-weight: {{ $weight }};"
                           title="{{ trans_choice('{1}:count published record about :place.|[2,*]:count published records about :place.', $records, ['count' => number_format($records), 'place' => $place['label']]) }}">
                            <i class="fas fa-location-dot text-primary me-1" style="font-size: .7em;"></i>
                            <span>{{ $place['label'] }}</span>
                            <span class="badge rounded-pill bg-light text-muted border ms-1" style="font-size: .6em;">{{ number_format($records) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- The same places as a plain ranked list, for accessibility and for
             readers who prefer a table-of-contents over a cloud. --}}
        <h2 class="h5 mt-4 mb-3">
            <i class="fas fa-list-ol me-1 text-muted"></i>{{ __('All places by frequency') }}
        </h2>
        <div class="list-group shadow-sm mb-4">
            @foreach($places as $place)
                @php
                    $records = (int) ($place['record_count'] ?? 0);
                    $detailUrl = $place['url'] ?? route('places.show', ['termId' => $place['term_id']]);
                    $browseUrl = $place['browse_url'] ?? url('/glam/browse?place='.$place['term_id']);
                @endphp
                <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                    <div class="text-truncate me-2">
                        <i class="fas fa-location-dot text-primary me-1"></i>
                        <a href="{{ $detailUrl }}" class="text-decoration-none">{{ $place['label'] }}</a>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="badge rounded-pill bg-primary" title="{{ __('Published records about this place') }}">{{ number_format($records) }}</span>
                        <a href="{{ $browseUrl }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Browse all about this place') }}">
                            <i class="fas fa-up-right-from-square"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-4 mb-0">
            <i class="fas fa-circle-info me-1"></i>
            {{ __('Places are the collection own geographic access points ranked by how many published records are about them. They update automatically as records are described and published.') }}
        </p>
    @endif

</div>
@endsection
