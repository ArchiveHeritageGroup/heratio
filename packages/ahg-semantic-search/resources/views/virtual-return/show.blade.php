{{--
  Virtual return - public surface for the repatriation engine (heratio#1207)

  Renders one repatriation claim as a respectful "virtual return": the object
  shown in its ORIGIN context (place of origin, claimant community, documented
  evidence) so it can be re-encountered in its own context even when no physical
  return has happened. A link to the object's own record (where any digital
  surrogate / 3D viewer lives) is offered ONLY when the underlying record is
  published. Sensitive subject matter: factual, non-partisan copy; the status is
  presented as where a dialogue stands, never a legal outcome. The standing
  disclaimer is always visible. International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $sm = $claim['status_meta'] ?? ['label' => $claim['claim_status'] ?? 'registered', 'level' => 'secondary', 'help' => ''];
    $itemTitle = ($item['title'] ?? null) ?: ($claim['item_title'] ?? null) ?: (__('Record').' #'.($claim['item_ref'] ?? ''));
    $recordUrl = $item['url'] ?? null; // present only for published items
@endphp

@section('title', __('Virtual return').': '.$itemTitle)

@section('content')
<div class="container-fluid py-4">

    {{-- Hero: the object placed back in its origin context --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-person-walking-arrow-right fa-lg me-3"></i>
            <span class="text-uppercase small fw-semibold text-white-50">{{ __('Virtual return') }}</span>
        </div>
        <h1 class="h2 mb-2">{{ $itemTitle }}</h1>
        @if(!empty($claim['origin_place']) || !empty($claim['claimant_community']))
            <p class="lead mb-1">
                {{ __('Returned, virtually, to') }}
                <strong>{{ $claim['origin_place'] ?: ($claim['claimant_community'] ?? '') }}</strong>@if(!empty($claim['origin_place']) && !empty($claim['claimant_community'])), {{ $claim['claimant_community'] }}@endif.
            </p>
        @endif
        <p class="mb-0 text-white-50">
            {{ __('This page brings the object back into its place and community of origin in digital form. It honours that context regardless of where the object is physically held today, and independently of any decision about physical return.') }}
        </p>
    </div>

    {{-- Standing disclaimer --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A documented request and its status, not a determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    <div class="row g-4">

        {{-- Origin context --}}
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="fas fa-seedling text-success me-2"></i>{{ __('In its origin context') }}</span>
                    <span class="badge text-bg-{{ $sm['level'] }}">{{ __($sm['label']) }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Place / region of origin') }}</div>
                            <div class="fw-semibold">{{ $claim['origin_place'] ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Claimant community') }}</div>
                            <div class="fw-semibold">{{ $claim['claimant_community'] ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Current holder') }}</div>
                            <div>{{ $claim['current_holder'] ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">{{ __('Stage of dialogue') }}</div>
                            <div>{{ __($sm['label']) }}</div>
                            @if(!empty($sm['help']))
                                <div class="small text-muted">{{ __($sm['help']) }}</div>
                            @endif
                        </div>
                    </div>

                    @if(!empty($claim['evidence_summary']))
                        <div class="border-start border-3 ps-3 mb-3">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">
                                <i class="fas fa-file-lines me-1"></i>{{ __('Documented evidence') }}
                            </div>
                            <p class="mb-0">{{ $claim['evidence_summary'] }}</p>
                        </div>
                    @endif

                    {{-- Register trace (origin-vs-holding), shown when the detection register still traces the item --}}
                    @if(!empty($register) && !empty($register['reason']))
                        <div class="border-start border-3 border-secondary ps-3">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">
                                <i class="fas fa-route me-1"></i>{{ __('Displacement context (from the register)') }}
                            </div>
                            <p class="small mb-0">{{ $register['reason'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Re-encounter the object --}}
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-image me-2"></i>{{ __('Re-encounter the object') }}
                </div>
                <div class="card-body d-flex flex-column">
                    @if($recordUrl)
                        <p class="text-muted small">
                            {{ __('The published record hosts the object\'s description and any digital surrogate or 3D view.') }}
                        </p>
                        <a href="{{ $recordUrl }}" class="btn btn-dark mt-auto">
                            <i class="fas fa-up-right-from-square me-1"></i>{{ __('Open the object record') }}
                        </a>
                    @else
                        <p class="text-muted small mb-0">
                            {{ __('A public record is not available for this object yet. Its origin context is recorded above; a digital surrogate may be added in future.') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <p class="text-muted small mt-4 mb-0">
        <i class="fas fa-hand-holding-heart me-1"></i>
        {{ __('This virtual return is maintained in dialogue with communities and holding institutions. Origin, ownership and lawful-transfer history are matters for the relevant communities and qualified staff to assess together, case by case.') }}
    </p>

</div>
@endsection
