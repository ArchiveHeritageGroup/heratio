{{--
  At-risk register - PUBLIC face of the "race against loss" (heratio#1205)

  A dignified, factual public register of PUBLISHED heritage items judged to be at
  risk and still awaiting capture, ordered most-urgent first. It frames why
  heritage is endangered - conflict, climate, decay, lost funding, displacement,
  or a digitisation gap - and the race to capture it before it is lost. Each entry
  shows the object (linked to its record), the risk and how urgent it is, and the
  documented reason. Captured and unpublished items are not shown. Copy is factual
  and non-alarmist: the register surfaces priorities, not predictions. Empty-state
  when nothing is at risk. International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Heritage at risk'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-shield-heart fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Heritage at risk') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('A race against loss: capturing the most vulnerable heritage before it is gone.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Heritage can be lost before it is ever recorded - to conflict, to a changing climate, to the slow decay of fragile materials, to lost funding, to displacement, or simply because no durable digital copy was ever made. This register draws together items that have been judged to need capture sooner rather than later, ordered by urgency, so the most vulnerable can be safeguarded first.') }}
        </p>
    </div>

    {{-- Standing disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A prioritisation aid, not a prediction of loss.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    @if(empty($entries))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
                <h2 class="h4">
                    @if(!empty($riskFilter))
                        {{ __('No published items at risk in this category') }}
                    @else
                        {{ __('No published items are currently flagged at risk') }}
                    @endif
                </h2>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    @if(!empty($riskFilter))
                        {{ __('No published records currently carry this risk. Other categories may still appear in the register.') }}
                    @else
                        {{ __('When a published record is judged to be at risk and still awaiting capture, it will appear here, ordered by urgency, so the most vulnerable heritage can be safeguarded first.') }}
                    @endif
                </p>
                @if(!empty($riskFilter))
                    <a href="{{ route('endangered.register') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to the full register') }}
                    </a>
                @endif
            </div>
        </div>
    @else

        {{-- Summary + risk-category filter --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <p class="text-muted small mb-0">
                <i class="fas fa-layer-group me-1"></i>
                @if(!empty($riskFilter))
                    {{ trans_choice('{1}:count published item awaiting capture in this category.|[2,*]:count published items awaiting capture in this category.', $shownCount, ['count' => $shownCount]) }}
                    <a href="{{ route('endangered.register') }}" class="ms-2">{{ __('Show all categories') }}</a>
                @else
                    {{ trans_choice('{1}:count published item awaiting capture.|[2,*]:count published items awaiting capture.', $total, ['count' => $total]) }}
                @endif
            </p>

            @if(!empty($riskCounts))
                <div class="d-flex flex-wrap gap-1 align-items-center">
                    <span class="text-muted small me-1">{{ __('Browse by risk:') }}</span>
                    @foreach($risks as $key => $meta)
                        @php $c = (int) ($riskCounts[$key] ?? 0); @endphp
                        @if($c > 0)
                            <a href="{{ route('endangered.register', ['risk' => $key]) }}"
                               class="badge rounded-pill text-decoration-none {{ strcasecmp($riskFilter, $key) === 0 ? 'text-bg-dark' : 'text-bg-light border' }}">
                                <i class="fas {{ $meta['icon'] }} me-1"></i>{{ __($meta['label']) }} <span class="opacity-75">{{ $c }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Register --}}
        <div class="row g-4">
            @foreach($entries as $e)
                @php
                    $title = $e['item_title'] ?: (__('Record').' #'.$e['item_ref']);
                    $rm = $e['risk_meta'] ?? ['label' => $e['risk_category'], 'icon' => 'fa-triangle-exclamation'];
                    $um = $e['urgency_meta'] ?? ['label' => $e['urgency'], 'level' => 'info'];
                @endphp

                <div class="col-12 col-lg-6">
                    <div class="card h-100 shadow-sm">

                        {{-- Header: the object + an urgency indicator --}}
                        <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                            <div class="me-2">
                                <div class="text-uppercase text-muted small fw-semibold">
                                    <i class="fas {{ $rm['icon'] }} me-1"></i>{{ __($rm['label']) }}
                                </div>
                                <h2 class="h5 mb-0">
                                    @if(!empty($e['item_slug']))
                                        <a href="{{ url('/'.$e['item_slug']) }}" class="text-decoration-none">{{ $title }}</a>
                                    @else
                                        {{ $title }}
                                    @endif
                                </h2>
                            </div>
                            <span class="badge rounded-pill text-bg-{{ $um['level'] }} flex-shrink-0">
                                {{ __($um['label']) }}
                            </span>
                        </div>

                        <div class="card-body">
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

                        @if(!empty($e['item_slug']))
                            <div class="card-footer bg-white d-flex justify-content-end">
                                <a href="{{ url('/'.$e['item_slug']) }}" class="btn btn-sm btn-outline-secondary">
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
            {{ __('This register is maintained by curatorial staff. The order in which to act, and the assessment of risk, are matters for qualified staff to weigh against the evidence in every case.') }}
        </p>

    @endif

</div>
@endsection
