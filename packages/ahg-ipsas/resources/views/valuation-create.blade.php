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

@section('title', 'Record Valuation')

@section('content')
@php
    $asset = $asset ?? null;
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.valuations') }}">Valuations</a></li>
                    <li class="breadcrumb-item active">New Valuation</li>
                </ol>
            </nav>
            <h1><i class="fas fa-calculator me-2"></i>{{ __('Record Valuation') }}</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            @if($asset)
            <input type="hidden" name="asset_id" value="{{ $asset->id }}">
            <div class="alert alert-info">
                <strong>{{ __('Asset:') }}</strong> {{ $asset->title ?? '' }}
                ({{ $asset->asset_number ?? '' }})
            </div>
            @else
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Select Asset') }}</h5></div>
                <div class="card-body">
                    <label class="form-label">Asset <span class="text-danger">*</span></label>
                    <input type="number" name="asset_id" class="form-control" required placeholder="{{ __('Enter Asset ID') }}">
                </div>
            </div>
            @endif

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Valuation Details') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Valuation Date <span class="text-danger">*</span></label>
                            <input type="date" name="valuation_date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Type') }}</label>
                            <select name="valuation_type" class="form-select">
                                <option value="initial">{{ __('Initial') }}</option>
                                <option value="revaluation">{{ __('Revaluation') }}</option>
                                <option value="impairment">{{ __('Impairment') }}</option>
                                <option value="reversal">{{ __('Reversal') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Basis') }}</label>
                            <select name="valuation_basis" class="form-select">
                                <option value="historical_cost">{{ __('Historical Cost') }}</option>
                                <option value="fair_value">{{ __('Fair Value') }}</option>
                                <option value="nominal">{{ __('Nominal Value') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Method') }}</label>
                            <select name="valuation_method" class="form-select">
                                <option value="market_comparison">{{ __('Market Comparison') }}</option>
                                <option value="income_approach">{{ __('Income Approach') }}</option>
                                <option value="cost_approach">{{ __('Cost Approach') }}</option>
                                <option value="expert_opinion">{{ __('Expert Opinion') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Previous Value') }}</label>
                            <input type="number" name="previous_value" class="form-control" step="0.01" min="0" value="{{ $asset->current_value ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Value <span class="text-danger">*</span></label>
                            <input type="number" name="new_value" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Valuer Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuer Name') }}</label>
                            <input type="text" name="valuer_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuer Type') }}</label>
                            <select name="valuer_type" class="form-select">
                                <option value="internal">{{ __('Internal') }}</option>
                                <option value="external">{{ __('External') }}</option>
                                <option value="government">{{ __('Government') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Qualifications') }}</label>
                            <input type="text" name="valuer_qualification" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Market Evidence') }}</label>
                            <textarea name="market_evidence" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Documentation Reference') }}</label>
                            <input type="text" name="documentation_ref" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> {{ __('IPSAS Valuation') }}</h6>
                <p class="small mb-0">Record valuation changes as required by IPSAS 17 and IPSAS 21 (Impairment of Non-Cash-Generating Assets). Ensure proper documentation and valuer credentials for audit compliance.</p>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>{{ __('Record Valuation') }}</button>
                    <a href="{{ route('ipsas.valuations') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
