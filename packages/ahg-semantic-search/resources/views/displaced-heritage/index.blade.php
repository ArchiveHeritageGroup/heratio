{{--
  Displaced heritage register - public face of the repatriation engine (heratio#1207)

  A dignified, factual register of catalogue items whose recorded place / community
  of origin appears to differ from where they are now held, as traced by
  DisplacedHeritageService::scan(). Each entry shows the object (linked to its
  record), its origin, its current holding location, the displacement context, and
  a confidence indicator. Where the object has a reconstruction twin or a digital
  surrogate, a gated "virtual return" link is offered; otherwise provenance context
  only. Sensitive subject matter: copy is factual and respectful, with no
  triumphalism and no claim of wrongful removal. Empty-state when nothing is traced.
  International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Displaced heritage register'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-route fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Displaced heritage register') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('Tracing objects whose recorded origin lies far from where they are held today.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('This register draws together catalogue records whose documented place or community of origin differs from their current holding location. It is offered in a spirit of transparency and care: to make displacement visible, to honour the communities and places of origin, and - where a digital reconstruction or surrogate exists - to allow each object to be encountered again in its own context.') }}
        </p>
    </div>

    {{-- Standing disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A documentation aid, not a claim or a determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    @if(empty($entries))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-dove fa-3x text-muted mb-3"></i>
                <h2 class="h4">
                    @if(!empty($originFilter))
                        {{ __('No traced items for this place of origin') }}
                    @else
                        {{ __('No displaced items have been traced yet') }}
                    @endif
                </h2>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    @if(!empty($originFilter))
                        {{ __('No catalogue records currently match this place or community of origin. Other origins may still appear in the register.') }}
                    @else
                        {{ __('As catalogue records record both a place or community of origin and a current holding location, any that appear to differ will be gathered here for careful review. None have been traced from the catalogue so far.') }}
                    @endif
                </p>
                @if(!empty($originFilter))
                    <a href="{{ route('displaced-heritage.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to the full register') }}
                    </a>
                @endif
            </div>
        </div>
    @else

        {{-- Summary + origin filter --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <p class="text-muted small mb-0">
                <i class="fas fa-layer-group me-1"></i>
                @if(!empty($originFilter))
                    {{ trans_choice('{1}:count item traced from :origin.|[2,*]:count items traced from :origin.', $shownCount, ['count' => $shownCount, 'origin' => $originFilter]) }}
                    <a href="{{ route('displaced-heritage.index') }}" class="ms-2">{{ __('Show all origins') }}</a>
                @else
                    {{ trans_choice('{1}:count item traced from the catalogue.|[2,*]:count items traced from the catalogue.', $totalTraced, ['count' => $totalTraced]) }}
                @endif
            </p>

            @if(!empty($byOrigin))
                <div class="d-flex flex-wrap gap-1 align-items-center">
                    <span class="text-muted small me-1">{{ __('Browse by place of origin:') }}</span>
                    @foreach($byOrigin as $bo)
                        @php
                            $region = (string) ($bo['region'] ?? '');
                            $count = (int) ($bo['count'] ?? 0);
                            $active = strcasecmp($region, (string) $originFilter) === 0;
                        @endphp
                        @if($region !== '')
                            <a href="{{ route('displaced-heritage.index', ['origin' => $region]) }}"
                               class="badge rounded-pill text-decoration-none {{ $active ? 'text-bg-dark' : 'text-bg-light border' }}">
                                {{ $region }} <span class="opacity-75">{{ $count }}</span>
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
                    $title = $e['title'] ?: (__('Record').' #'.$e['id']);
                    $conf = $e['confidence'] ?? ['label' => __('Documented origin'), 'level' => 'info'];
                    $vr = $e['virtual_return'] ?? null;
                    // Per-item detail link, gated on the route existing and a real id.
                    $detailUrl = (!empty($e['id']) && \Illuminate\Support\Facades\Route::has('displaced-heritage.show'))
                        ? route('displaced-heritage.show', ['id' => (int) $e['id']])
                        : null;
                @endphp

                <div class="col-12 col-lg-6">
                    <div class="card h-100 shadow-sm">

                        {{-- Header: the object + a confidence indicator --}}
                        <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                            <div class="me-2">
                                <div class="text-uppercase text-muted small fw-semibold">
                                    <i class="fas fa-landmark me-1"></i>{{ __('Object') }}
                                </div>
                                <h2 class="h5 mb-0">
                                    @if($detailUrl)
                                        <a href="{{ $detailUrl }}" class="text-decoration-none">{{ $title }}</a>
                                    @elseif(!empty($e['slug']))
                                        <a href="{{ url('/'.$e['slug']) }}" class="text-decoration-none">{{ $title }}</a>
                                    @else
                                        {{ $title }}
                                    @endif
                                </h2>
                            </div>
                            <span class="badge rounded-pill bg-{{ $conf['level'] }} flex-shrink-0">
                                {{ $conf['label'] }}
                            </span>
                        </div>

                        <div class="card-body">

                            {{-- Origin -> holding journey --}}
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="text-uppercase text-muted small fw-semibold mb-1">
                                        <i class="fas fa-seedling me-1 text-success"></i>{{ __('Place / community of origin') }}
                                    </div>
                                    <div class="fw-semibold">{{ $e['origin_region'] }}</div>
                                    @if(!empty($e['origin']['value']))
                                        <div class="small text-muted">
                                            {{ $e['origin']['label'] }}: {{ $e['origin']['value'] }}
                                        </div>
                                    @endif
                                </div>
                                <div class="col-6">
                                    <div class="text-uppercase text-muted small fw-semibold mb-1">
                                        <i class="fas fa-building-columns me-1"></i>{{ __('Current holding location') }}
                                    </div>
                                    <div class="fw-semibold">{{ $e['holding_region'] }}</div>
                                    @if(!empty($e['holding']['value']))
                                        <div class="small text-muted">
                                            {{ $e['holding']['label'] }}: {{ $e['holding']['value'] }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Displacement context --}}
                            @if(!empty($e['reason']))
                                <div class="border-start border-3 ps-3 mb-3">
                                    <div class="text-uppercase text-muted small fw-semibold mb-1">
                                        <i class="fas fa-file-lines me-1"></i>{{ __('Displacement context') }}
                                    </div>
                                    <p class="small mb-0">{{ $e['reason'] }}</p>
                                </div>
                            @endif

                        </div>

                        {{-- Per-item detail link --}}
                        @if($detailUrl)
                            <div class="px-3 pb-2">
                                <a href="{{ $detailUrl }}" class="small text-decoration-none">
                                    <i class="fas fa-circle-info me-1"></i>{{ __('View the full entry') }}
                                </a>
                            </div>
                        @endif

                        {{-- Virtual return / provenance affordance --}}
                        <div class="card-footer bg-white d-flex justify-content-between flex-wrap gap-2 align-items-center">
                            @if($vr && $vr['type'] === 'reconstruction')
                                <span class="small text-muted">
                                    <i class="fas fa-cube me-1"></i>{{ __('A digital reconstruction exists') }}
                                </span>
                                <a href="{{ $vr['url'] }}" class="btn btn-sm btn-dark">
                                    <i class="fas fa-person-walking-arrow-right me-1"></i>{{ $vr['label'] }}
                                </a>
                            @elseif($vr && $vr['type'] === 'surrogate')
                                <span class="small text-muted">
                                    <i class="fas fa-image me-1"></i>{{ __('A digital surrogate exists') }}
                                </span>
                                <a href="{{ $vr['url'] }}" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-up-right-from-square me-1"></i>{{ $vr['label'] }}
                                </a>
                            @else
                                <span class="small text-muted">
                                    <i class="fas fa-scroll me-1"></i>{{ __('Provenance and return context on the record') }}
                                </span>
                                @if(!empty($e['slug']))
                                    <a href="{{ url('/'.$e['slug']) }}" class="btn btn-sm btn-outline-secondary">
                                        {{ __('Open record') }} <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                @endif
                            @endif
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-4 mb-0">
            <i class="fas fa-hand-holding-heart me-1"></i>
            {{ __('This register is maintained in dialogue with communities and holding institutions. Origin, ownership and lawful-transfer history are matters for qualified staff and the relevant communities to assess together, case by case.') }}
        </p>

    @endif

</div>
@endsection
