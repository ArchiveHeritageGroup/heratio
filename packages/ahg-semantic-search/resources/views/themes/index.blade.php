{{--
  Explore by theme - public discovery landing (heratio#1210)

  Themes are the collection's strongest subjects: the subject terms under which
  the most PUBLISHED records sit. Framed as "ways into the collection", each
  theme is a card with its published-record count and a few example records.
  Read-only; published records only; empty-state when nothing is themed yet.
  International, jurisdiction-neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Explore by theme'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-shapes fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Explore by theme') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('Ways into the collection, grouped by what the records are about.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('A theme is one of the collection strongest subjects - the topics under which the most published records sit. Start from a theme to find related material across the catalogue, rather than from a single search box. Each theme links straight to the records that sit under it.') }}
        </p>
    </div>

    @if(empty($themes))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No themes to show yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('Themes appear here once published records are described with subjects. As records are catalogued and made public, the strongest subjects will surface automatically as ways into the collection.') }}
                </p>
            </div>
        </div>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <p class="text-muted small mb-0">
                <i class="fas fa-circle-info me-1"></i>
                {{ trans_choice('{1}:count theme drawn from the published collection.|[2,*]:count themes drawn from the published collection.', $count, ['count' => $count]) }}
            </p>
            <a href="{{ url('/themes.json') }}" class="text-decoration-none small" rel="nofollow">
                <i class="fas fa-code me-1"></i>{{ __('Theme data (JSON)') }}
            </a>
        </div>

        <div class="row g-4">
            @foreach($themes as $theme)
                @php
                    $examples = $theme['examples'] ?? [];
                    $records = (int) ($theme['record_count'] ?? 0);
                @endphp
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                            <div class="me-2">
                                <div class="text-uppercase text-muted small fw-semibold">
                                    <i class="fas fa-tag me-1 text-primary"></i>{{ __('Theme') }}
                                </div>
                                <h2 class="h5 mb-0">
                                    <a href="{{ route('themes.show', ['termId' => $theme['term_id']]) }}" class="text-decoration-none">
                                        {{ $theme['label'] }}
                                    </a>
                                </h2>
                            </div>
                            <span class="badge rounded-pill bg-primary flex-shrink-0" title="{{ __('Published records under this theme') }}">
                                {{ number_format($records) }}
                            </span>
                        </div>

                        <div class="card-body">
                            <p class="small text-muted mb-2">
                                {{ trans_choice('{1}:count published record sits under this theme.|[2,*]:count published records sit under this theme.', $records, ['count' => number_format($records)]) }}
                            </p>

                            @if(count($examples) > 0)
                                <div class="text-uppercase text-muted small fw-semibold mb-1">
                                    <i class="fas fa-list me-1"></i>{{ __('For example') }}
                                </div>
                                <ul class="list-unstyled mb-0">
                                    @foreach($examples as $ex)
                                        <li class="text-truncate">
                                            @if(!empty($ex['slug']))
                                                <a href="{{ url('/'.$ex['slug']) }}" class="text-decoration-none">{{ $ex['title'] }}</a>
                                            @else
                                                <span>{{ $ex['title'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="card-footer bg-white d-flex justify-content-between flex-wrap gap-2">
                            <a href="{{ route('themes.show', ['termId' => $theme['term_id']]) }}" class="text-decoration-none small">
                                {{ __('Explore theme') }} <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                            <a href="{{ $theme['browse_url'] ?? url('/glam/browse?subject='.$theme['term_id']) }}" class="text-decoration-none small text-muted">
                                {{ __('Browse all') }} <i class="fas fa-up-right-from-square ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-4 mb-0">
            <i class="fas fa-circle-info me-1"></i>
            {{ __('Themes are the collection own subjects ranked by how many published records carry them. They update automatically as records are described and published.') }}
        </p>
    @endif

</div>
@endsection
