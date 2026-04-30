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
                    <li class="breadcrumb-item active">Configuration</li>
                </ol>
            </nav>
            <h1><i class="fas fa-cog me-2"></i>{{ __('IPSAS Configuration') }}</h1>
            <p class="text-muted">Configure heritage asset accounting settings</p>
        </div>
    </div>

    @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('notice') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Organization') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">{{ __('Organization Name') }}</label>
                            <input type="text" name="organization_name" class="form-control" value="{{ $config['organization_name'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Accounting Standard') }}</label>
                            <select name="accounting_standard" class="form-select">
                                <option value="ipsas" {{ 'ipsas' === ($config['accounting_standard'] ?? '') ? 'selected' : '' }}>{{ __('IPSAS (International)') }}</option>
                                <option value="ifrs" {{ 'ifrs' === ($config['accounting_standard'] ?? '') ? 'selected' : '' }}>{{ __('IFRS') }}</option>
                                <option value="grap" {{ 'grap' === ($config['accounting_standard'] ?? '') ? 'selected' : '' }}>{{ __('GRAP (South Africa)') }}</option>
                                <option value="gaap" {{ 'gaap' === ($config['accounting_standard'] ?? '') ? 'selected' : '' }}>{{ __('GAAP') }}</option>
                            </select>
                            <small class="text-muted">{{ __('IPSAS is the international baseline; other frameworks are jurisdictional overlays.') }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Default Currency') }}</label>
                            <input type="text" name="default_currency" class="form-control" maxlength="3" value="{{ $config['default_currency'] ?? '' }}" placeholder="{{ __('ISO 4217 code (e.g. USD, EUR, GBP, ZAR)') }}">
                            <small class="text-muted">{{ __('Three-letter ISO 4217 code. Leave blank to show amounts without a currency prefix.') }}</small>
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
                                    $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
                                @endphp
                                @foreach($months as $num => $name)
                                <option value="{{ $num }}" {{ $num === ($config['financial_year_start'] ?? '01') ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
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
                                <option value="none" {{ 'none' === ($config['depreciation_policy'] ?? 'none') ? 'selected' : '' }}>{{ __('No Depreciation (Heritage)') }}</option>
                                <option value="straight_line" {{ 'straight_line' === ($config['depreciation_policy'] ?? '') ? 'selected' : '' }}>{{ __('Straight Line') }}</option>
                                <option value="reducing_balance" {{ 'reducing_balance' === ($config['depreciation_policy'] ?? '') ? 'selected' : '' }}>{{ __('Reducing Balance') }}</option>
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
                    <li>Heritage assets may be recognized at nominal value (IPSAS 17)</li>
                    <li>Depreciation typically not applied to heritage items</li>
                    <li>Regular impairment assessment required (IPSAS 21)</li>
                    <li>Fair value revaluation every 3-5 years</li>
                </ul>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save Configuration') }}</button>
                    <a href="{{ route('ipsas.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
