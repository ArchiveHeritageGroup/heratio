{{--
  Displaced heritage - per-item detail (public face of the repatriation engine, heratio#1207)

  One traced object's full picture, derived from DisplacedHeritageService::scan():
  its record (linked), the place / community of ORIGIN, the current HOLDER /
  location, the displacement (origin-vs-holding) context, a confidence indicator,
  and the gated "virtual return" affordance (reconstruction twin walkthrough if
  linked, else digital surrogate on the record, else provenance context only).

  Sensitive subject matter: copy is factual and respectful, with no triumphalism
  and no claim of wrongful removal. The service's standing disclaimer is surfaced.
  International, jurisdiction-neutral. Read-only; every link existence-checked
  upstream so this view never emits a dead link.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $title = !empty($entry['title']) ? $entry['title'] : (__('Record').' #'.$entry['id']);
    $conf = $entry['confidence'] ?? ['label' => __('Documented origin'), 'level' => 'info'];
    $vr = $entry['virtual_return'] ?? null;
@endphp

@section('title', $title.' - '.__('Displaced heritage'))

@section('content')
<div class="container-fluid py-4">

    {{-- Breadcrumb back to the register --}}
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('displaced-heritage.index') }}" class="text-decoration-none">
                    <i class="fas fa-route me-1"></i>{{ __('Displaced heritage register') }}
                </a>
            </li>
            @if(!empty($entry['origin_region']))
                <li class="breadcrumb-item">
                    <a href="{{ route('displaced-heritage.index', ['origin' => $entry['origin_region']]) }}"
                       class="text-decoration-none">{{ $entry['origin_region'] }}</a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
        </ol>
    </nav>

    {{-- Header: the object + a confidence indicator --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-uppercase text-white-50 small fw-semibold mb-1">
                    <i class="fas fa-landmark me-1"></i>{{ __('Traced object') }}
                </div>
                <h1 class="h2 mb-2">{{ $title }}</h1>
                <p class="mb-0 text-white-50">
                    {{ __('Recorded as originating in :origin, now held in :holding.', [
                        'origin' => $entry['origin_region'] ?: __('an unrecorded place'),
                        'holding' => $entry['holding_region'] ?: __('an unrecorded location'),
                    ]) }}
                </p>
            </div>
            <span class="badge rounded-pill bg-{{ $conf['level'] }} fs-6 flex-shrink-0">
                {{ $conf['label'] }}
            </span>
        </div>
    </div>

    {{-- Standing disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A documentation aid, not a claim or a determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    <div class="row g-4">

        {{-- Origin -> holding journey --}}
        <div class="col-12 col-lg-8">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-arrow-right-arrow-left me-2 text-muted"></i>{{ __('Origin and current holding') }}
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">
                                <i class="fas fa-seedling me-1 text-success"></i>{{ __('Place / community of origin') }}
                            </div>
                            <div class="h5 mb-1">{{ $entry['origin_region'] ?: __('Not recorded') }}</div>
                            @if(!empty($entry['origin']['value']))
                                <div class="small text-muted">
                                    {{ $entry['origin']['label'] }}: {{ $entry['origin']['value'] }}
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">
                                <i class="fas fa-building-columns me-1"></i>{{ __('Current holder / location') }}
                            </div>
                            <div class="h5 mb-1">{{ $entry['holding_region'] ?: __('Not recorded') }}</div>
                            @if(!empty($entry['holding']['value']))
                                <div class="small text-muted">
                                    {{ $entry['holding']['label'] }}: {{ $entry['holding']['value'] }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Displacement context --}}
                    @if(!empty($entry['reason']))
                        <hr class="my-4">
                        <div class="border-start border-3 ps-3">
                            <div class="text-uppercase text-muted small fw-semibold mb-1">
                                <i class="fas fa-file-lines me-1"></i>{{ __('Displacement context') }}
                            </div>
                            <p class="mb-0">{{ $entry['reason'] }}</p>
                        </div>
                    @endif
                </div>

                {{-- Link to the underlying record --}}
                @if(!empty($entry['slug']))
                    <div class="card-footer bg-white">
                        <a href="{{ url('/'.$entry['slug']) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-lines me-1"></i>{{ __('Open the full catalogue record') }}
                            <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Virtual return / provenance affordance --}}
        <div class="col-12 col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-hand-holding-heart me-2 text-muted"></i>{{ __('Virtual return') }}
                    </h2>
                </div>
                <div class="card-body d-flex flex-column">
                    @if($vr && $vr['type'] === 'reconstruction')
                        <p class="text-muted">
                            <i class="fas fa-cube me-1"></i>{{ __('A digital reconstruction of this object in its own context exists. You can walk through it and encounter the object again where it belongs.') }}
                        </p>
                        <a href="{{ $vr['url'] }}" class="btn btn-dark mt-auto">
                            <i class="fas fa-person-walking-arrow-right me-1"></i>{{ $vr['label'] }}
                        </a>
                    @elseif($vr && $vr['type'] === 'surrogate')
                        <p class="text-muted">
                            <i class="fas fa-image me-1"></i>{{ __('A digital surrogate of this object is available on its catalogue record, so it can be viewed at a distance from where it is held.') }}
                        </p>
                        <a href="{{ $vr['url'] }}" class="btn btn-outline-dark mt-auto">
                            <i class="fas fa-up-right-from-square me-1"></i>{{ $vr['label'] }}
                        </a>
                    @else
                        <p class="text-muted">
                            <i class="fas fa-scroll me-1"></i>{{ __('No digital reconstruction or surrogate is linked to this object yet. Its provenance and return context are documented on the catalogue record.') }}
                        </p>
                        @if(!empty($entry['slug']))
                            <a href="{{ url('/'.$entry['slug']) }}" class="btn btn-outline-secondary mt-auto">
                                {{ __('Open record') }} <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

    </div>

    <p class="text-muted small mt-4 mb-0">
        <i class="fas fa-hand-holding-heart me-1"></i>
        {{ __('This entry is maintained in dialogue with communities and holding institutions. Origin, ownership and lawful-transfer history are matters for qualified staff and the relevant communities to assess together, case by case.') }}
    </p>

    <div class="mt-3 d-flex gap-2">
        <a href="{{ route('displaced-heritage.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to the register') }}
        </a>
        <a href="{{ route('repatriation.lodge.form', ['item' => $entry['id']]) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-scale-balanced me-1"></i>{{ __('Lodge a repatriation claim about this object') }}
        </a>
    </div>

</div>
@endsection
