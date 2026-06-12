{{--
  Endangered-heritage dashboard - public face of the "race against loss" (heratio#1205)

  A read-only aggregate VIEW over the at-risk register (endangered_heritage_item):
  big numbers, simple CSS bars (no charting library), the capture-progress split
  (captured / in progress / still flagged), the breakdown by risk category and by
  urgency, and a short tail of the highest-priority outstanding PUBLISHED items that
  links onward to the /at-risk register. No new table; cheap aggregate COUNTs only.

  Copy is factual and non-alarmist: a flag records a curatorial judgement that an
  item should be captured sooner rather than later, and the documented reason why -
  never a prediction of certain loss or a claim about any institution's stewardship.
  The standing disclaimer is surfaced prominently. Dignified empty-state when nothing
  has been flagged. International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Endangered heritage dashboard'))

@php
    $available = (bool) ($data['available'] ?? false);
    $total = (int) ($data['total'] ?? 0);
    $byRisk = is_array($data['by_risk'] ?? null) ? $data['by_risk'] : [];
    $byUrgency = is_array($data['by_urgency'] ?? null) ? $data['by_urgency'] : [];
    $captured = (int) ($data['captured'] ?? 0);
    $inProgress = (int) ($data['in_progress'] ?? 0);
    $flagged = (int) ($data['flagged'] ?? 0);
    $outstanding = (int) ($data['outstanding'] ?? 0);
    $capturePct = (int) ($data['capture_progress_pct'] ?? 0);
    $publicTotal = (int) ($data['public_total'] ?? 0);
    $priority = is_array($data['priority'] ?? null) ? $data['priority'] : [];

    // Largest count in each breakdown, used to scale the CSS bars (never /0).
    $riskMax = 0;
    foreach ($byRisk as $r) {
        $riskMax = max($riskMax, (int) ($r['count'] ?? 0));
    }
    $urgencyMax = 0;
    foreach ($byUrgency as $u) {
        $urgencyMax = max($urgencyMax, (int) ($u['count'] ?? 0));
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
            <i class="fas fa-shield-heart fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Endangered heritage dashboard') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('A race against loss: an open overview of the heritage flagged for priority capture.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Heritage can be lost before it is ever recorded - to conflict, to a changing climate, to the slow decay of fragile materials, to lost funding, to displacement, or simply because no durable digital copy was ever made. This dashboard gathers, in one place, the items judged to need capture sooner rather than later: how many there are, why they are at risk, how urgent they are, and how far the work of safeguarding them has progressed.') }}
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

    @if(!$available || $total === 0)
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No items flagged as at risk yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('As items are flagged for priority capture, this dashboard will summarise them here: the count of flags, the reasons they are at risk, how urgent each is, and how far the work of capturing them has progressed. None have been flagged so far.') }}
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
                            <i class="fas fa-flag me-1"></i>{{ __('Items flagged at risk') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold mb-0 text-danger">{{ number_format($outstanding) }}</div>
                        <div class="text-uppercase small text-muted fw-semibold">
                            <i class="fas fa-hourglass-half me-1"></i>{{ __('Still awaiting capture') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold mb-0 text-primary">{{ number_format($inProgress) }}</div>
                        <div class="text-uppercase small text-muted fw-semibold">
                            <i class="fas fa-camera-retro me-1"></i>{{ __('Capture in progress') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold mb-0 text-success">{{ number_format($captured) }}</div>
                        <div class="text-uppercase small text-muted fw-semibold">
                            <i class="fas fa-circle-check me-1"></i>{{ __('Captured / safeguarded') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Capture progress --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">
                    <i class="fas fa-gauge-high me-2"></i>{{ __('Capture progress') }}
                </h2>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold">
                        {{ __('Share of at-risk items already captured') }}
                    </span>
                    <span class="small text-muted">{{ $capturePct }}%</span>
                </div>
                <div class="progress" role="progressbar"
                     aria-label="{{ __('Capture progress') }}"
                     aria-valuenow="{{ $capturePct }}" aria-valuemin="0" aria-valuemax="100"
                     style="height: 1rem;">
                    <div class="progress-bar bg-success" style="width: {{ $capturePct }}%;">
                        @if($capturePct >= 10){{ $capturePct }}%@endif
                    </div>
                </div>
                <p class="text-muted small mb-0 mt-3">
                    {{ __('Progress is the share of items captured out of those captured plus those still awaiting capture. Items no longer treated as at risk are not counted.') }}
                </p>
            </div>
        </div>

        <div class="row g-4">

            {{-- By risk category --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-triangle-exclamation me-2"></i>{{ __('Why heritage is at risk') }}
                        </h2>
                    </div>
                    <div class="card-body">
                        @php $shownRisk = 0; @endphp
                        @foreach($byRisk as $r)
                            @php
                                $count = (int) ($r['count'] ?? 0);
                                if ($count <= 0) { continue; }
                                $shownRisk++;
                                $icon = (string) ($r['icon'] ?? 'fa-triangle-exclamation');
                                $width = $pct($count, $riskMax);
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-semibold">
                                        <i class="fas {{ $icon }} me-1 text-muted"></i>{{ __($r['label'] ?? '') }}
                                    </span>
                                    <span class="small text-muted">{{ number_format($count) }}</span>
                                </div>
                                <div class="progress" role="progressbar"
                                     aria-label="{{ $r['label'] ?? '' }}"
                                     aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $riskMax }}"
                                     style="height: .6rem;">
                                    <div class="progress-bar bg-secondary" style="width: {{ $width }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                        @if($shownRisk === 0)
                            <p class="text-muted small mb-0">{{ __('No risk categories have been recorded yet.') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- By urgency --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-bolt me-2"></i>{{ __('How urgent the work is') }}
                        </h2>
                    </div>
                    <div class="card-body">
                        @php $shownUrgency = 0; @endphp
                        @foreach($byUrgency as $u)
                            @php
                                $count = (int) ($u['count'] ?? 0);
                                if ($count <= 0) { continue; }
                                $shownUrgency++;
                                $level = (string) ($u['level'] ?? 'info');
                                $width = $pct($count, $urgencyMax);
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-semibold">
                                        <span class="badge bg-{{ $level }} me-1">&nbsp;</span>{{ __($u['label'] ?? '') }}
                                    </span>
                                    <span class="small text-muted">{{ number_format($count) }}</span>
                                </div>
                                <div class="progress" role="progressbar"
                                     aria-label="{{ $u['label'] ?? '' }}"
                                     aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $urgencyMax }}"
                                     style="height: .6rem;">
                                    <div class="progress-bar bg-{{ $level }}" style="width: {{ $width }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                        @if($shownUrgency === 0)
                            <p class="text-muted small mb-0">{{ __('No urgency bands have been recorded yet.') }}</p>
                        @endif
                        <p class="text-muted small mb-0 mt-3">
                            {{ __('Urgency is a curatorial judgement of how soon an item should be captured, not a forecast of how soon it might be lost.') }}
                        </p>
                    </div>
                </div>
            </div>

        </div>

        {{-- Highest-priority outstanding items --}}
        @if(!empty($priority))
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-list-ol me-2"></i>{{ __('Highest-priority items awaiting capture') }}
                    </h2>
                    <a href="{{ url('/at-risk') }}" class="btn btn-sm btn-outline-dark">
                        <i class="fas fa-arrow-right me-1"></i>{{ __('Open the full register') }}
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Item') }}</th>
                                <th scope="col">{{ __('Risk') }}</th>
                                <th scope="col">{{ __('Urgency') }}</th>
                                <th scope="col" class="text-end">{{ __('Record') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($priority as $p)
                                @php
                                    $itemRef = (int) ($p['item_ref'] ?? 0);
                                    $title = $p['item_title'] ?: (__('Record').' #'.$itemRef);
                                    $rm = $p['risk_meta'] ?? ['label' => $p['risk_category'] ?? '', 'icon' => 'fa-triangle-exclamation'];
                                    $um = $p['urgency_meta'] ?? ['label' => $p['urgency'] ?? '', 'level' => 'info'];
                                    $slug = $p['item_slug'] ?? null;
                                    $recordUrl = ($slug !== null && $slug !== '') ? url('/'.$slug) : null;
                                @endphp
                                <tr>
                                    <td>
                                        @if($recordUrl)
                                            <a href="{{ $recordUrl }}" class="text-decoration-none fw-semibold">{{ $title }}</a>
                                        @else
                                            <span class="fw-semibold">{{ $title }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        <i class="fas {{ $rm['icon'] ?? 'fa-triangle-exclamation' }} me-1"></i>{{ __($rm['label'] ?? '') }}
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-{{ $um['level'] ?? 'info' }}">{{ __($um['label'] ?? '') }}</span>
                                    </td>
                                    <td class="text-end">
                                        @if($recordUrl)
                                            <a href="{{ $recordUrl }}" class="btn btn-sm btn-outline-secondary">
                                                {{ __('Open') }} <i class="fas fa-arrow-right ms-1"></i>
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
                @if($publicTotal > count($priority))
                    <div class="card-footer bg-white text-center">
                        <a href="{{ url('/at-risk') }}" class="small text-decoration-none">
                            @php $more = $publicTotal - count($priority); @endphp
                            {{ trans_choice('{1}:1 more published item awaits capture in the register.|[2,*]::count more published items await capture in the register.', $more, ['count' => $more]) }}
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Machine read + closing note --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
            <p class="text-muted small mb-0">
                <i class="fas fa-hand-holding-heart me-1"></i>
                {{ __('This register is maintained by curatorial staff. The order in which to act, and the assessment of risk, are matters for qualified staff to weigh against the evidence in every case.') }}
            </p>
            <a href="{{ url('/endangered-heritage.json') }}" class="small text-decoration-none" rel="nofollow">
                <i class="fas fa-code me-1"></i>{{ __('Open data (JSON)') }}
            </a>
        </div>

    @endif

</div>
@endsection
