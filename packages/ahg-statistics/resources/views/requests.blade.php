{{--
  Requests and releases report (heratio#1772).
  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@extends('theme::layouts.1col')

@section('title', __('Requests and releases'))

@section('content')
@php
    $r = $data['requests'] ?? [];
    $d = $data['decisions'] ?? [];
    $rel = $data['releases'] ?? [];
    $tile = fn ($label, $value) => ['label' => $label, 'value' => $value];
@endphp
<div class="container-fluid px-4 py-3">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">{{ __('Statistics') }}</a></li>
            <li class="breadcrumb-item active">{{ __('Requests and releases') }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-4"><i class="fas fa-key me-2"></i>{{ __('Requests and releases') }}</h1>

    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-3 align-items-center">
                <div class="col-auto"><label class="form-label mb-0" for="rq-start">{{ __('Period:') }}</label></div>
                <div class="col-auto"><input id="rq-start" type="date" name="start" class="form-control form-control-sm" value="{{ $startDate }}"></div>
                <div class="col-auto">{{ __('to') }}</div>
                <div class="col-auto"><input type="date" name="end" class="form-control form-control-sm" value="{{ $endDate }}" aria-label="{{ __('End date') }}"></div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">{{ __('Apply') }}</button></div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            $tile(__('Requests submitted'), $r['submitted'] ?? 0),
            $tile(__('From a basket'), $r['basket'] ?? 0),
            $tile(__('Records requested'), $r['records'] ?? 0),
            $tile(__('Average time to a decision'), $data['turnaround_hours'] !== null ? __(':hours hours', ['hours' => $data['turnaround_hours']]) : '-'),
            $tile(__('Release links issued'), $rel['issued'] ?? 0),
            $tile(__('Release downloads'), $rel['downloads'] ?? 0),
        ] as $t)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-muted">{{ $t['label'] }}</div>
                    <div class="fs-4 fw-bold">{{ is_numeric($t['value']) ? number_format($t['value']) : $t['value'] }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">{{ __('Requests by status') }}</h5></div>
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($r['by_status'] ?? [] as $status => $n)
                            <tr><td>@include('ahg-access-request::partials.status-badge', ['status' => $status])</td><td class="text-end">{{ number_format($n) }}</td></tr>
                        @empty
                            <tr><td class="text-muted">{{ __('No requests in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">{{ __('Decisions and steps') }}</h5></div>
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($d as $action => $n)
                            <tr><td>{{ ucfirst($action) }}</td><td class="text-end">{{ number_format($n) }}</td></tr>
                        @empty
                            <tr><td class="text-muted">{{ __('No decisions in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">{{ __('Releases') }}</h5></div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td>{{ __('Released on approval') }}</td><td class="text-end">{{ number_format($rel['by_request'] ?? 0) }}</td></tr>
                        <tr><td>{{ __('Released on payment of an order') }}</td><td class="text-end">{{ number_format($rel['by_order'] ?? 0) }}</td></tr>
                        <tr><td>{{ __('Links used at least once') }}</td><td class="text-end">{{ number_format($rel['downloaded'] ?? 0) }}</td></tr>
                        <tr><td>{{ __('Links revoked') }}</td><td class="text-end">{{ number_format($rel['revoked'] ?? 0) }}</td></tr>
                        <tr><td>{{ __('Quotations paid') }}</td><td class="text-end">{{ number_format($rel['quotes_paid'] ?? 0) }}</td></tr>
                        <tr><td>{{ __('Paid for quotations') }}</td><td class="text-end">{{ number_format($rel['quotes_paid_total'] ?? 0, 2) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Requests per day') }}</h5></div>
        <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Date') }}</th><th class="text-end">{{ __('Requests') }}</th></tr></thead>
            <tbody>
                @forelse($data['by_day'] ?? [] as $day => $n)
                    <tr><td>{{ $day }}</td><td class="text-end">{{ number_format($n) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-muted">{{ __('No requests in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
