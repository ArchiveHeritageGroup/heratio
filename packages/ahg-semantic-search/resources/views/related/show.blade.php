{{--
  Related records - public discovery surface

  Given ONE published archival record, this page lists the most-similar OTHER
  published records, reusing the EXISTING semantic vector index (no new index,
  no AI call of its own). Each card links straight to a record's show page.
  Read-only; published records only; an empty / unavailable index renders a calm
  "no related records available" empty-state and never 500s. The note below is
  honest about HOW relatedness is computed and about what an empty result means.
  International, jurisdiction-neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Related records').($recordTitle ? ': '.$recordTitle : ''))

@section('content')
@php
    $related = $related ?? [];
    $count = (int) ($count ?? count($related));
    $recordId = (int) ($recordId ?? 0);
    $recordTitle = $recordTitle ?? (__('Record').' #'.$recordId);
    $recordUrl = $recordUrl ?? null;
@endphp
<div class="container-fluid py-4">

    {{-- Breadcrumb back to the source record (when it has a slug) --}}
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb mb-0">
            @if($recordUrl)
                <li class="breadcrumb-item">
                    <a href="{{ $recordUrl }}" class="text-decoration-none">
                        <i class="fas fa-file-lines me-1"></i>{{ \Illuminate\Support\Str::limit($recordTitle, 80) }}
                    </a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ __('Related records') }}</li>
        </ol>
    </nav>

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="text-uppercase small text-white-50 fw-semibold mb-1">
            <i class="fas fa-diagram-project me-1"></i>{{ __('Related records') }}
        </div>
        <h1 class="h2 mb-2">{{ $recordTitle }}</h1>
        <p class="lead mb-0">
            @if($count > 0)
                {{ trans_choice('{1}:count published record found that is most similar to this one.|[2,*]:count published records found that are most similar to this one.', $count, ['count' => number_format($count)]) }}
            @else
                {{ __('No related records available for this record yet.') }}
            @endif
        </p>
        @if($recordUrl)
            <a href="{{ $recordUrl }}" class="btn btn-sm btn-outline-light mt-3">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back to this record') }}
            </a>
        @endif
    </div>

    @if(empty($related))
        {{-- Calm empty-state. Absence of results is shown plainly, with an honest
             explanation of why there may be none. Never a 500. --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-circle-nodes fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No related records available') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 40rem;">
                    {{ __('We could not find published records similar to this one right now. This can happen when the record has not yet been added to the semantic index, when no other published record is close enough in meaning, or when the discovery service is temporarily unavailable. Nothing is hidden - there is simply nothing to show.') }}
                </p>
            </div>
        </div>
    @else
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
            @foreach($related as $rec)
                @php
                    $title = $rec['title'] ?? (__('Record').' #'.($rec['id'] ?? 0));
                    $url = $rec['url'] ?? null;
                    $score = isset($rec['score']) ? (float) $rec['score'] : null;
                @endphp
                <div class="col">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body d-flex flex-column">
                            <h3 class="h6 card-title mb-2">
                                @if($url)
                                    <a href="{{ $url }}" class="text-decoration-none stretched-link">{{ $title }}</a>
                                @else
                                    {{ $title }}
                                @endif
                            </h3>
                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                @if($score !== null)
                                    <span class="badge bg-secondary" title="{{ __('Semantic similarity score (higher is closer)') }}">
                                        {{ number_format($score, 3) }}
                                    </span>
                                @else
                                    <span></span>
                                @endif
                                @if($url)
                                    <span class="small text-primary">
                                        {{ __('Open') }} <i class="fas fa-arrow-right ms-1"></i>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Honest note on how relatedness is computed. --}}
    <div class="alert alert-light border small mb-0">
        <i class="fas fa-circle-info me-1"></i>
        {{ __('How this is computed: relatedness is the semantic similarity of the record descriptions. Each record is represented as a vector in an existing semantic index, and this page lists the published records whose descriptions are closest in meaning to this one. Only published records appear, and the record itself is never listed. If nothing appears, it is shown plainly above - no result is ever hidden.') }}
    </div>

</div>
@endsection
