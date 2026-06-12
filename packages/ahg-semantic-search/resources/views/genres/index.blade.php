{{--
  Browse by genre / form - public discovery landing (genre/form slice)

  The published holdings organised by the genres and forms they carry: the genre
  terms under which the most PUBLISHED records sit. Framed as "ways into the
  collection by genre/form", each genre is a chip in a frequency-sized cloud
  carrying its published-record count and linking to a per-genre detail.
  Read-only; published records only; empty-state when nothing is described by
  genre yet. International, jurisdiction-neutral copy - the genre names come
  entirely from the data.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Browse by genre'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-shapes fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Browse by genre') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('Ways into the collection, grouped by the genres and forms of the records.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Each genre or form is an access point used to describe the published holdings. Start from a genre to find related material across the catalogue, rather than from a single search box. The larger the genre, the more published records carry it; each links straight to those records.') }}
        </p>
    </div>

    @if(empty($genres))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-shapes fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No genres to show yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('Genres and forms appear here once published records are described with genre access points. As records are catalogued and made public, the genres they carry will surface automatically as ways into the collection.') }}
                </p>
            </div>
        </div>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <p class="text-muted small mb-0">
                <i class="fas fa-circle-info me-1"></i>
                {{ trans_choice('{1}:count genre drawn from the published collection.|[2,*]:count genres drawn from the published collection.', $count, ['count' => $count]) }}
            </p>
            <a href="{{ url('/genres.json') }}" class="text-decoration-none small" rel="nofollow">
                <i class="fas fa-code me-1"></i>{{ __('Genre data (JSON)') }}
            </a>
        </div>

        {{-- Frequency-sized cloud: each chip scales between ~0.9rem and ~1.8rem
             by its share of the busiest genre. Ordered by frequency already. --}}
        @php $maxCount = max(1, (int) ($maxCount ?? 1)); @endphp
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center" style="gap: .5rem .75rem; line-height: 2.2;">
                    @foreach($genres as $genre)
                        @php
                            $records = (int) ($genre['record_count'] ?? 0);
                            $share = $records / $maxCount;            // 0..1
                            $size = 0.9 + ($share * 0.9);             // rem
                            $weight = $share >= 0.66 ? 700 : ($share >= 0.33 ? 600 : 500);
                            $detailUrl = $genre['url'] ?? route('genres.show', ['termId' => $genre['term_id']]);
                        @endphp
                        <a href="{{ $detailUrl }}"
                           class="text-decoration-none d-inline-flex align-items-center"
                           style="font-size: {{ number_format($size, 2) }}rem; font-weight: {{ $weight }};"
                           title="{{ trans_choice('{1}:count published record of :genre.|[2,*]:count published records of :genre.', $records, ['count' => number_format($records), 'genre' => $genre['label']]) }}">
                            <i class="fas fa-tag text-primary me-1" style="font-size: .7em;"></i>
                            <span>{{ $genre['label'] }}</span>
                            <span class="badge rounded-pill bg-light text-muted border ms-1" style="font-size: .6em;">{{ number_format($records) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- The same genres as a plain ranked list, for accessibility and for
             readers who prefer a table-of-contents over a cloud. --}}
        <h2 class="h5 mt-4 mb-3">
            <i class="fas fa-list-ol me-1 text-muted"></i>{{ __('All genres by frequency') }}
        </h2>
        <div class="list-group shadow-sm mb-4">
            @foreach($genres as $genre)
                @php
                    $records = (int) ($genre['record_count'] ?? 0);
                    $detailUrl = $genre['url'] ?? route('genres.show', ['termId' => $genre['term_id']]);
                    $browseUrl = $genre['browse_url'] ?? url('/glam/browse?genre='.$genre['term_id']);
                @endphp
                <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                    <div class="text-truncate me-2">
                        <i class="fas fa-tag text-primary me-1"></i>
                        <a href="{{ $detailUrl }}" class="text-decoration-none">{{ $genre['label'] }}</a>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="badge rounded-pill bg-primary" title="{{ __('Published records of this genre') }}">{{ number_format($records) }}</span>
                        <a href="{{ $browseUrl }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Browse all of this genre') }}">
                            <i class="fas fa-up-right-from-square"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-4 mb-0">
            <i class="fas fa-circle-info me-1"></i>
            {{ __('Genres are the collection own genre and form access points ranked by how many published records carry them. They update automatically as records are described and published.') }}
        </p>
    @endif

</div>
@endsection
