{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'IPSAS Configuration')

@section('content')
@php
    $config = $config ?? [];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item active">{{ __('Configuration') }}</li>
                </ol>
            </nav>
            <h1><i class="fas fa-cog me-2"></i>{{ __('IPSAS Configuration') }}</h1>
            <p class="text-muted">{{ __('Configure heritage asset accounting settings') }}</p>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Organization') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">{{ __('Organization Name') }}</label>
                            <input type="text" name="organization_name" class="form-control" value="{{ old('organization_name', $config['organization_name'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Accounting Standard') }}</label>
                            <select name="accounting_standard" class="form-select">
                                @foreach($opts['accounting_standard'] as $code => $lbl)
                                    <option value="{{ $code }}" {{ strcasecmp($code, (string) old('accounting_standard', $config['accounting_standard'] ?? 'IPSAS')) === 0 ? 'selected' : '' }}>{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('IPSAS is the international baseline; other frameworks are jurisdictional overlays.') }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Default Currency') }}</label>
                            <input type="text" name="default_currency" class="form-control" maxlength="3" required value="{{ old('default_currency', $config['default_currency'] ?? 'USD') }}" placeholder="{{ __('ISO 4217 code (e.g. USD, EUR, GBP)') }}">
                            <small class="text-muted">{{ __('Three-letter ISO 4217 code used for new assets, valuations and reports.') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Financial Year') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Financial Year Start') }}</label>
                            <select name="financial_year_start" class="form-select">
                                @php
                                    $fyStart = substr((string) old('financial_year_start', $config['financial_year_start'] ?? '01-01'), 0, 2);
                                @endphp
                                @for($m = 1; $m <= 12; $m++)
                                    @php $num = sprintf('%02d', $m); @endphp
                                <option value="{{ $num }}-01" {{ $num === $fyStart ? 'selected' : '' }}>{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('F') }}</option>
                                @endfor
                            </select>
                            <small class="text-muted">{{ __('The financial year runs for twelve months from the first day of this month.') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Valuation Settings') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Depreciation Policy') }}</label>
                            <select name="depreciation_policy" class="form-select">
                                @foreach($opts['depreciation_policy'] as $code => $lbl)
                                    <option value="{{ $code }}" {{ $code === old('depreciation_policy', $config['depreciation_policy'] ?? 'none') ? 'selected' : '' }}>{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('Heritage assets typically not depreciated under IPSAS 17') }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Frequency (Years)') }}</label>
                            <input type="number" name="valuation_frequency_years" class="form-control" min="1" max="10" value="{{ $config['valuation_frequency_years'] ?? 5 }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Nominal Value (for unvalued assets)') }}</label>
                            <input type="number" name="nominal_value" class="form-control" step="0.01" min="0" value="{{ $config['nominal_value'] ?? 1 }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Impairment Threshold (%)') }}</label>
                            <input type="number" name="impairment_threshold_percent" class="form-control" min="0" max="100" value="{{ $config['impairment_threshold_percent'] ?? 10 }}">
                            <small class="text-muted">{{ __('Threshold for IPSAS 21 impairment recognition') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Insurance') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Insurance Review Period (Months)') }}</label>
                            <input type="number" name="insurance_review_months" class="form-control" min="1" max="24" value="{{ $config['insurance_review_months'] ?? 12 }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> {{ __('IPSAS Heritage Assets') }}</h6>
                <ul class="small mb-0">
                    <li>{{ __('Heritage assets may be recognized at nominal value (IPSAS 17)') }}</li>
                    <li>{{ __('Depreciation typically not applied to heritage items') }}</li>
                    <li>{{ __('Regular impairment assessment required (IPSAS 21)') }}</li>
                    <li>{{ __('Fair value revaluation every 3-5 years') }}</li>
                </ul>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save Configuration') }}</button>
                    <a href="{{ route('ipsas.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
