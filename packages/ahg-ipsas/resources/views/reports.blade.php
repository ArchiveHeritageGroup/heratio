{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Heratio is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.
--}}
@extends('theme::layouts.1col')

@section('title', __('Financial Reports'))

@section('content')
@php
    // [report code or null for a page link, icon, colour, description]
    $cards = [
        ['asset_register', 'fa-file-invoice-dollar', 'primary', __('Complete listing of heritage assets with valuations')],
        ['valuation_summary', 'fa-balance-scale', 'success', __('Summary of asset valuations for :y', ['y' => $year])],
        ['impairments', 'fa-exclamation-circle', 'warning', __('Summary of recognised and pending impairments')],
        ['insurance', 'fa-shield-alt', 'info', __('Insurance policies and coverage gaps')],
        [null, 'fa-calendar-alt', 'secondary', __('Year-end financial summary for heritage assets')],
        ['compliance', 'fa-check-circle', 'dark', __('IPSAS compliance status and requirements')],
    ];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item active">{{ __('Reports') }}</li>
                </ol>
            </nav>
            <h1><i class="fas fa-chart-bar me-2"></i>{{ __('Financial Reports') }}</h1>
            <p class="text-muted">{{ __('IPSAS-compliant financial reporting. Amounts are in :c unless a record says otherwise.', ['c' => $currency]) }}</p>
        </div>
        <div class="col-auto">
            <form method="get" class="d-flex gap-2 align-items-center">
                <label for="reportYear" class="form-label mb-0">{{ __('Year') }}</label>
                <select name="year" id="reportYear" class="form-select" data-csp-auto-submit>
                    @for($y = (int) date('Y'); $y >= (int) date('Y') - 10; $y--)
                        <option value="{{ $y }}" {{ (int) $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
        </div>
    </div>

    <div class="row">
        @foreach($cards as [$code, $icon, $colour, $desc])
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas {{ $icon }} fa-3x text-{{ $colour }} mb-3"></i>
                    <h5>{{ $code ? __($reports[$code]) : __('Financial Year Summary') }}</h5>
                    <p class="text-muted small">{{ $desc }}</p>
                    @if($code)
                        <a href="{{ route('ipsas.reports', ['report' => $code, 'year' => $year]) }}" class="btn btn-outline-{{ $colour }} btn-sm">
                            <i class="fas fa-download me-1"></i> {{ __('Download CSV') }}
                        </a>
                    @else
                        <a href="{{ route('ipsas.financialYear', ['year' => $year]) }}" class="btn btn-outline-{{ $colour }} btn-sm">
                            <i class="fas fa-eye me-1"></i> {{ __('View Summary') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
