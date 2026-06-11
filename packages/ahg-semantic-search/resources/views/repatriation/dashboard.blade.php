{{--
  Repatriation dashboard - public face of the repatriation engine (heratio#1207)

  A read-only aggregate VIEW over the claims register (displaced_heritage_claim):
  big numbers, simple CSS bars (no charting library), the virtual-return vs
  physically-returned split, the top places / communities of origin, and a recent-
  activity tail that links onward to each claim's /virtual-return/{id} page. No new
  table; cheap aggregate COUNTs only.

  Sensitive subject matter. Copy is factual, non-partisan and jurisdiction-neutral:
  a claim status describes WHERE A DIALOGUE STANDS, never a legal determination. The
  standing disclaimer is surfaced prominently. Dignified empty-state when no claims
  have been recorded. International, culturally respectful.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Repatriation dashboard'))

@php
    $available = (bool) ($data['available'] ?? false);
    $total = (int) ($data['total'] ?? 0);
    $byStatus = is_array($data['by_status'] ?? null) ? $data['by_status'] : [];
    $byOrigin = is_array($data['by_origin'] ?? null) ? $data['by_origin'] : [];
    $byCommunity = is_array($data['by_community'] ?? null) ? $data['by_community'] : [];
    $virtualReturn = (int) ($data['virtual_return'] ?? 0);
    $returned = (int) ($data['returned'] ?? 0);
    $inDialogue = (int) ($data['in_dialogue'] ?? 0);
    $recent = is_array($data['recent'] ?? null) ? $data['recent'] : [];

    // Largest status count, used to scale the CSS bars (never divide by zero).
    $statusMax = 0;
    foreach ($byStatus as $s) {
        $statusMax = max($statusMax, (int) ($s['count'] ?? 0));
    }
    $originMax = 0;
    foreach ($byOrigin as $o) {
        $originMax = max($originMax, (int) ($o['count'] ?? 0));
    }
    $communityMax = 0;
    foreach ($byCommunity as $c) {
        $communityMax = max($communityMax, (int) ($c['count'] ?? 0));
    }

    $pct = static function (int $value, int $max): int {
        if ($max <= 0) {
            return 0;
        }
        return (int) max(2, round($value / $max * 100));
    };
@endphp

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-scale-balanced fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Repatriation dashboard') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('An open overview of the repatriation dialogues recorded here.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('This dashboard gathers, in one place, the repatriation claims documented in our register: how many there are, where the objects originate, the communities connected to them, and where each conversation currently stands. It is offered in a spirit of transparency and care - to make these dialogues visible and to honour the places and communities of origin.') }}
        </p>
    </div>

    {{-- Standing disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A status describes where a dialogue stands, not a legal determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    @if(!$available || $total === 0)
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-dove fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No repatriation claims recorded yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('As repatriation claims are documented in the register, this dashboard will summarise them here: the count of claims, the places and communities of origin, the balance between virtual and physical returns, and where each dialogue stands. None have been recorded so far.') }}
                </p>
            </div>
        </div>
    @else

        {{-- Big numbers --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold mb-0">{{ number_format($total) }}</div>
                        <div class="text-uppercase small text-muted fw-semibold">
                            <i class="fas fa-folder-open me-1"></i>{{ __('Documented claims') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold mb-0">{{ number_format($inDialogue) }}</div>
                        <div class="text-uppercase small text-muted fw-semibold">
                            <i class="fas fa-comments me-1"></i>{{ __('Dialogues in progress') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold mb-0 text-dark">{{ number_format($virtualReturn) }}</div>
                        <div class="text-uppercase small text-muted fw-semibold">
                            <i class="fas fa-cube me-1"></i>{{ __('Under virtual return') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold mb-0 text-success">{{ number_format($returned) }}</div>
                        <div class="text-uppercase small text-muted fw-semibold">
                            <i class="fas fa-hands-holding-circle me-1"></i>{{ __('Physically returned') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- By status --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-list-check me-2"></i>{{ __('Where the dialogues stand') }}
                        </h2>
                    </div>
                    <div class="card-body">
                        @foreach($byStatus as $s)
                            @php
                                $count = (int) ($s['count'] ?? 0);
                                $level = (string) ($s['level'] ?? 'secondary');
                                $width = $pct($count, $statusMax);
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-semibold" title="{{ $s['help'] ?? '' }}">
                                        <span class="badge bg-{{ $level }} me-1">&nbsp;</span>{{ $s['label'] ?? '' }}
                                    </span>
                                    <span class="small text-muted">{{ number_format($count) }}</span>
                                </div>
                                <div class="progress" role="progressbar"
                                     aria-label="{{ $s['label'] ?? '' }}"
                                     aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $statusMax }}"
                                     style="height: .6rem;">
                                    <div class="progress-bar bg-{{ $level }}" style="width: {{ $count > 0 ? $width : 0 }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                        <p class="text-muted small mb-0 mt-3">
                            {{ __('Each status is a neutral, factual stage in a documented conversation. None asserts ownership or a finding of wrongful removal.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Top places / communities of origin --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-earth-africa me-2"></i>{{ __('Places and communities of origin') }}
                        </h2>
                    </div>
                    <div class="card-body">

                        @if(!empty($byOrigin))
                            <div class="text-uppercase text-muted small fw-semibold mb-2">
                                <i class="fas fa-seedling me-1 text-success"></i>{{ __('By place of origin') }}
                            </div>
                            @foreach($byOrigin as $o)
                                @php
                                    $place = (string) ($o['place'] ?? '');
                                    $count = (int) ($o['count'] ?? 0);
                                    $width = $pct($count, $originMax);
                                @endphp
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">{{ $place }}</span>
                                        <span class="small text-muted">{{ number_format($count) }}</span>
                                    </div>
                                    <div class="progress" role="progressbar"
                                         aria-label="{{ $place }}"
                                         aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $originMax }}"
                                         style="height: .5rem;">
                                        <div class="progress-bar bg-success" style="width: {{ $width }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        @if(!empty($byCommunity))
                            <div class="text-uppercase text-muted small fw-semibold mb-2 mt-4">
                                <i class="fas fa-people-group me-1"></i>{{ __('By community') }}
                            </div>
                            @foreach($byCommunity as $c)
                                @php
                                    $community = (string) ($c['community'] ?? '');
                                    $count = (int) ($c['count'] ?? 0);
                                    $width = $pct($count, $communityMax);
                                @endphp
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small">{{ $community }}</span>
                                        <span class="small text-muted">{{ number_format($count) }}</span>
                                    </div>
                                    <div class="progress" role="progressbar"
                                         aria-label="{{ $community }}"
                                         aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $communityMax }}"
                                         style="height: .5rem;">
                                        <div class="progress-bar bg-primary" style="width: {{ $width }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        @if(empty($byOrigin) && empty($byCommunity))
                            <p class="text-muted small mb-0">
                                {{ __('No place or community of origin has been recorded against the claims yet.') }}
                            </p>
                        @endif

                    </div>
                </div>
            </div>

        </div>

        {{-- Recent activity --}}
        @if(!empty($recent))
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-clock-rotate-left me-2"></i>{{ __('Recent activity') }}
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Object') }}</th>
                                <th scope="col">{{ __('Place of origin') }}</th>
                                <th scope="col">{{ __('Community') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col" class="text-end">{{ __('Virtual return') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent as $r)
                                @php
                                    $claimId = (int) ($r['id'] ?? 0);
                                    $title = $r['item_title'] ?: (__('Claim').' #'.$claimId);
                                    $meta = $r['status_meta'] ?? ['label' => '', 'level' => 'secondary'];
                                    $vrUrl = $claimId > 0 ? url('/virtual-return/'.$claimId) : null;
                                @endphp
                                <tr>
                                    <td>
                                        @if($vrUrl)
                                            <a href="{{ $vrUrl }}" class="text-decoration-none fw-semibold">{{ $title }}</a>
                                        @else
                                            <span class="fw-semibold">{{ $title }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $r['origin_place'] ?: '-' }}</td>
                                    <td class="small text-muted">{{ $r['claimant_community'] ?: '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $meta['level'] ?? 'secondary' }}">{{ $meta['label'] ?? '' }}</span>
                                    </td>
                                    <td class="text-end">
                                        @if($vrUrl)
                                            <a href="{{ $vrUrl }}" class="btn btn-sm btn-outline-dark">
                                                <i class="fas fa-person-walking-arrow-right me-1"></i>{{ __('Open') }}
                                            </a>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Machine read + closing note --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
            <p class="text-muted small mb-0">
                <i class="fas fa-hand-holding-heart me-1"></i>
                {{ __('These dialogues are maintained with the communities and holding institutions concerned. Origin, ownership and lawful-transfer history are matters for qualified staff and the relevant communities to assess together, case by case, under the applicable law.') }}
            </p>
            <a href="{{ url('/repatriation.json') }}" class="small text-decoration-none" rel="nofollow">
                <i class="fas fa-code me-1"></i>{{ __('Open data (JSON)') }}
            </a>
        </div>

    @endif

</div>
@endsection
