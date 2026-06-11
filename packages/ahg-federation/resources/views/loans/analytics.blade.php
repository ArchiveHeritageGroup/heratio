{{--
  Federation loan analytics - admin-gated, read-only aggregate dashboard over
  the inter-institution loan workflow (#1203 loan-analytics slice).

  Big numbers + simple CSS bars (no charting library). Reads the $report array
  built by LoanAnalyticsService::report(). Carries an empty-state when there
  are no loan requests yet, and a hint when no self-member is registered (so
  incoming/outgoing cannot be split).

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', __('Loan analytics'))

@php
    $statusBadge = function ($s) {
        return [
            'requested'  => 'bg-info',
            'approved'   => 'bg-primary',
            'declined'   => 'bg-danger',
            'in_transit' => 'bg-warning text-dark',
            'returned'   => 'bg-success',
            'cancelled'  => 'bg-secondary',
        ][$s] ?? 'bg-secondary';
    };
    $barColour = function ($s) {
        return [
            'requested'  => '#0dcaf0',
            'approved'   => '#0d6efd',
            'declined'   => '#dc3545',
            'in_transit' => '#ffc107',
            'returned'   => '#198754',
            'cancelled'  => '#6c757d',
        ][$s] ?? '#6c757d';
    };

    $total = (int) ($report['total'] ?? 0);
    $statusCounts = $report['status_counts'] ?? [];
    $direction = $report['direction'] ?? ['incoming' => 0, 'outgoing' => 0, 'external' => 0];
    $approval = $report['approval'] ?? ['approved' => 0, 'declined' => 0, 'decided' => 0, 'rate_pct' => null];
    $turnaround = $report['turnaround'] ?? ['avg_days' => null, 'decided_count' => 0];
    $topBorrowers = $report['top_borrowers'] ?? [];
    $topLenders = $report['top_lenders'] ?? [];

    $statusMax = !empty($statusCounts) ? max(1, max($statusCounts)) : 1;
    $borrowerMax = !empty($topBorrowers) ? max(1, max(array_column($topBorrowers, 'count'))) : 1;
    $lenderMax = !empty($topLenders) ? max(1, max(array_column($topLenders, 'count'))) : 1;
    $dirMax = max(1, $direction['incoming'] ?? 0, $direction['outgoing'] ?? 0, $direction['external'] ?? 0);
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-bar-chart-line me-2"></i>{{ __('Loan analytics') }}
            </h4>
            <p class="text-muted mb-0">
                {{ __('Read-only aggregates over inter-institution loan requests across the federation.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('federation.loans.index') }}" class="atom-btn-white">
                <i class="bi bi-arrow-left-right me-1"></i>{{ __('Loan worklist') }}
            </a>
            <a href="{{ route('federation.loans.analytics.json') }}" class="atom-btn-white" target="_blank" rel="noopener">
                <i class="bi bi-filetype-json me-1"></i>{{ __('JSON') }}
            </a>
        </div>
    </div>

    @if ($total === 0)
        {{-- Empty-state: never a 500, just a dignified hint. --}}
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-inbox display-5 d-block mb-3"></i>
                <h5>{{ __('No loan requests yet') }}</h5>
                <p class="mb-3">
                    {{ __('Once federation members start requesting loans, this dashboard fills in with counts by status, direction, partner, approval rate and turnaround.') }}
                </p>
                <a href="{{ route('federation.loans.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('New loan request') }}
                </a>
            </div>
        </div>
    @else
        @if (($report['self_member_id'] ?? 0) < 1)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ __('No self-member is registered, so requests cannot be split into incoming and outgoing. Mark a member as "This institution" on the') }}
                <a href="{{ route('union.members.index') }}">{{ __('members page') }}</a>.
            </div>
        @endif

        {{-- Big numbers --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold">{{ number_format($total) }}</div>
                        <div class="text-muted small text-uppercase">{{ __('Total requests') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold">
                            {{ $approval['rate_pct'] !== null ? $approval['rate_pct'] . '%' : '-' }}
                        </div>
                        <div class="text-muted small text-uppercase">{{ __('Approval rate') }}</div>
                        <div class="text-muted small">
                            {{ __(':a approved / :d decided', ['a' => $approval['approved'], 'd' => $approval['decided']]) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="display-5 fw-bold">
                            {{ $turnaround['avg_days'] !== null ? $turnaround['avg_days'] : '-' }}
                        </div>
                        <div class="text-muted small text-uppercase">{{ __('Avg turnaround (days)') }}</div>
                        <div class="text-muted small">
                            {{ __(':n decided', ['n' => $turnaround['decided_count']]) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="display-6 fw-bold">
                            <span class="text-info">{{ $direction['incoming'] }}</span>
                            <span class="text-muted">/</span>
                            <span class="text-primary">{{ $direction['outgoing'] }}</span>
                        </div>
                        <div class="text-muted small text-uppercase">{{ __('Incoming / outgoing') }}</div>
                        @if (($direction['external'] ?? 0) > 0)
                            <div class="text-muted small">{{ __(':n external', ['n' => $direction['external']]) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            {{-- Counts by status (CSS bars) --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-list-check me-1"></i>{{ __('Requests by status') }}
                    </div>
                    <div class="card-body">
                        @foreach ($statuses as $s)
                            @php $c = (int) ($statusCounts[$s] ?? 0); @endphp
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>
                                        <span class="badge {{ $statusBadge($s) }} me-1">&nbsp;</span>
                                        {{ __(ucwords(str_replace('_', ' ', $s))) }}
                                    </span>
                                    <span class="fw-bold">{{ number_format($c) }}</span>
                                </div>
                                <div style="background:#e9ecef;border-radius:.25rem;height:.6rem;overflow:hidden;">
                                    <div style="height:100%;width:{{ $c > 0 ? max(2, round($c / $statusMax * 100)) : 0 }}%;background:{{ $barColour($s) }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Direction (CSS bars) --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-arrow-left-right me-1"></i>{{ __('Direction') }}
                        <span class="text-muted small">{{ __('(relative to this institution)') }}</span>
                    </div>
                    <div class="card-body">
                        @php
                            $dirRows = [
                                ['key' => 'incoming', 'label' => __('Incoming (we hold)'), 'colour' => '#0dcaf0'],
                                ['key' => 'outgoing', 'label' => __('Outgoing (we borrow)'), 'colour' => '#0d6efd'],
                                ['key' => 'external', 'label' => __('External (neither party is us)'), 'colour' => '#6c757d'],
                            ];
                        @endphp
                        @foreach ($dirRows as $d)
                            @php $c = (int) ($direction[$d['key']] ?? 0); @endphp
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>{{ $d['label'] }}</span>
                                    <span class="fw-bold">{{ number_format($c) }}</span>
                                </div>
                                <div style="background:#e9ecef;border-radius:.25rem;height:.6rem;overflow:hidden;">
                                    <div style="height:100%;width:{{ $c > 0 ? max(2, round($c / $dirMax * 100)) : 0 }}%;background:{{ $d['colour'] }};"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Top borrowers --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Top borrowers') }}
                        <span class="text-muted small">{{ __('(members making the most requests)') }}</span>
                    </div>
                    <div class="card-body">
                        @if (empty($topBorrowers))
                            <p class="text-muted mb-0">{{ __('No requests recorded.') }}</p>
                        @else
                            @foreach ($topBorrowers as $b)
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>{{ $b['name'] }}</span>
                                        <span class="fw-bold">{{ number_format($b['count']) }}</span>
                                    </div>
                                    <div style="background:#e9ecef;border-radius:.25rem;height:.6rem;overflow:hidden;">
                                        <div style="height:100%;width:{{ max(2, round($b['count'] / $borrowerMax * 100)) }}%;background:#0d6efd;"></div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            {{-- Top lenders --}}
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-box-arrow-in-down-left me-1"></i>{{ __('Top lenders') }}
                        <span class="text-muted small">{{ __('(members holding the most requested items)') }}</span>
                    </div>
                    <div class="card-body">
                        @if (empty($topLenders))
                            <p class="text-muted mb-0">{{ __('No requests recorded.') }}</p>
                        @else
                            @foreach ($topLenders as $l)
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>{{ $l['name'] }}</span>
                                        <span class="fw-bold">{{ number_format($l['count']) }}</span>
                                    </div>
                                    <div style="background:#e9ecef;border-radius:.25rem;height:.6rem;overflow:hidden;">
                                        <div style="height:100%;width:{{ max(2, round($l['count'] / $lenderMax * 100)) }}%;background:#198754;"></div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
