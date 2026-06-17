{{--
  Cross-institution at-risk board - federated face of the "race against loss"
  (heratio#1205, federation slice).

  This instance's published at-risk register MERGED with a LIVE fetch of every
  active federation peer's /api/v1/endangered, ranked into one leaderboard,
  most-urgent first, with a source-institution badge on every entry. Additive:
  the single-instance /at-risk register is unchanged. Fail-soft: federation
  absent / a peer down shows local-only items plus a plain warning, never an
  error. International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Heritage at risk across institutions'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-globe fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Heritage at risk across institutions') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('A shared race against loss: the most vulnerable heritage from this institution and its federation partners, gathered into one priority view.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Each partner publishes its own at-risk register; this board queries them live and ranks every item by urgency, so the most vulnerable heritage across the network can be safeguarded first. Each entry shows which institution holds it.') }}
        </p>
        <div class="mt-3">
            <a href="{{ route('endangered.register') }}" class="btn btn-sm btn-outline-light">
                <i class="fas fa-arrow-left me-1"></i>{{ __('This institution only') }}
            </a>
        </div>
    </div>

    {{-- Standing disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A prioritisation aid, not a prediction of loss.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    {{-- Federation status: how many partners answered, plus any soft warnings --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 text-muted small">
        <span>
            <i class="fas fa-network-wired me-1"></i>
            @if($peersQueried > 0)
                {{ trans_choice('{1}:count partner institution queried.|[2,*]:count partner institutions queried.', $peersQueried, ['count' => $peersQueried]) }}
            @else
                {{ __('No federation partners are configured; showing this institution only.') }}
            @endif
        </span>
        <span class="text-muted">&middot;</span>
        <span>
            <i class="fas fa-layer-group me-1"></i>
            {{ trans_choice('{1}:count item awaiting capture.|[2,*]:count items awaiting capture.', $totalCount, ['count' => $totalCount]) }}
        </span>
    </div>

    @if(!empty($warnings))
        <div class="alert alert-light border d-flex align-items-start mb-4" role="status">
            <i class="fas fa-triangle-exclamation text-warning me-3 mt-1"></i>
            <div class="small">
                <div class="fw-semibold mb-1">{{ __('Some partners could not be reached just now') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach($warnings as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Risk-category filter over the merged board --}}
    @if(!empty($riskCounts))
        <div class="d-flex flex-wrap gap-1 align-items-center mb-3">
            <span class="text-muted small me-1">{{ __('Browse by risk:') }}</span>
            <a href="{{ route('endangered.register.global') }}"
               class="badge rounded-pill text-decoration-none {{ empty($riskFilter) ? 'text-bg-dark' : 'text-bg-light border' }}">
                {{ __('All') }}
            </a>
            @foreach($risks as $key => $meta)
                @php $c = (int) ($riskCounts[$key] ?? 0); @endphp
                @if($c > 0)
                    <a href="{{ route('endangered.register.global', ['risk' => $key]) }}"
                       class="badge rounded-pill text-decoration-none {{ strcasecmp($riskFilter, $key) === 0 ? 'text-bg-dark' : 'text-bg-light border' }}">
                        <i class="fas {{ $meta['icon'] }} me-1"></i>{{ __($meta['label']) }} <span class="opacity-75">{{ $c }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    @endif

    @if(empty($items))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
                <h2 class="h4">
                    @if(!empty($riskFilter))
                        {{ __('No items at risk in this category across the network') }}
                    @else
                        {{ __('No items are currently flagged at risk across the network') }}
                    @endif
                </h2>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    {{ __('When a published record is judged to be at risk and still awaiting capture - here or at a partner institution - it will appear in this shared board, ordered by urgency.') }}
                </p>
                @if(!empty($riskFilter))
                    <a href="{{ route('endangered.register.global') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to the full board') }}
                    </a>
                @endif
            </div>
        </div>
    @else

        {{-- The merged, ranked board --}}
        <div class="row g-4">
            @foreach($items as $e)
                @php
                    $title = ($e['title'] ?? null) ?: (__('Record').' #'.($e['item_ref'] ?? ''));
                    $riskIcon = $risks[$e['risk_category']]['icon'] ?? 'fa-triangle-exclamation';
                    $riskLabel = $e['risk_label'] ?: ($e['risk_category'] ?? '');
                    $urgencyLevel = $urgencies[$e['urgency']]['level'] ?? 'info';
                    $urgencyLabel = $e['urgency_label'] ?: ($e['urgency'] ?? '');
                    $peer = $e['source_peer'] ?? null;
                    $institution = $e['institution'] ?? null;
                    $url = $e['catalogue_url'] ?? null;
                @endphp

                <div class="col-12 col-lg-6">
                    <div class="card h-100 shadow-sm">

                        <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                            <div class="me-2">
                                <div class="text-uppercase text-muted small fw-semibold">
                                    <i class="fas {{ $riskIcon }} me-1"></i>{{ __($riskLabel) }}
                                </div>
                                <h2 class="h5 mb-0">
                                    @if(!empty($url))
                                        <a href="{{ $url }}" class="text-decoration-none" @if($peer) target="_blank" rel="noopener" @endif>{{ $title }}</a>
                                    @else
                                        {{ $title }}
                                    @endif
                                </h2>
                            </div>
                            <span class="badge rounded-pill text-bg-{{ $urgencyLevel }} flex-shrink-0">
                                {{ __($urgencyLabel) }}
                            </span>
                        </div>

                        <div class="card-body">
                            {{-- Source-institution badge: local vs a federation partner --}}
                            <div class="mb-2">
                                @if($peer)
                                    <span class="badge rounded-pill text-bg-info">
                                        <i class="fas fa-share-nodes me-1"></i>{{ $institution ?: $peer['name'] }}
                                    </span>
                                    <span class="text-muted small ms-1">{{ __('Partner institution') }}</span>
                                @else
                                    <span class="badge rounded-pill text-bg-secondary">
                                        <i class="fas fa-house me-1"></i>{{ $institution ?: __('This institution') }}
                                    </span>
                                @endif
                            </div>

                            @if(!empty($e['reason']))
                                <div class="border-start border-3 ps-3">
                                    <div class="text-uppercase text-muted small fw-semibold mb-1">
                                        <i class="fas fa-file-lines me-1"></i>{{ __('Why it is at risk') }}
                                    </div>
                                    <p class="small mb-0">{{ $e['reason'] }}</p>
                                </div>
                            @else
                                <p class="small text-muted mb-0">
                                    {{ __('This item has been prioritised for capture. See its record for full context.') }}
                                </p>
                            @endif
                        </div>

                        @if(!empty($url))
                            <div class="card-footer bg-white d-flex justify-content-end">
                                <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary" @if($peer) target="_blank" rel="noopener" @endif>
                                    {{ __('Open record') }} <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        @endif

                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-4 mb-0">
            <i class="fas fa-hand-holding-heart me-1"></i>
            {{ __('This board draws on registers maintained by curatorial staff at each institution. The order in which to act, and the assessment of risk, are matters for qualified staff to weigh against the evidence in every case.') }}
        </p>

    @endif

</div>
@endsection
