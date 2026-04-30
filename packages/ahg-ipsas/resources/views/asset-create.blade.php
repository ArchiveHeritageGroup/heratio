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

@section('title', 'Register Heritage Asset')

@section('content')
@php
    $categories = $categories ?? collect();
    $defaultCurrency = config('heratio.default_currency', '');
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.assets') }}">Assets</a></li>
                    <li class="breadcrumb-item active">New Asset</li>
                </ol>
            </nav>
            <h1><i class="fas fa-plus-circle me-2"></i>{{ __('Register Heritage Asset') }}</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Asset Details') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Category') }}</label>
                            <select name="category_id" class="form-select">
                                <option value="">{{ __('Select...') }}</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Location') }}</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Acquisition') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Acquisition Date') }}</label>
                            <input type="date" name="acquisition_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Acquisition Method') }}</label>
                            <select name="acquisition_method" class="form-select">
                                <option value="purchase">{{ __('Purchase') }}</option>
                                <option value="donation">{{ __('Donation') }}</option>
                                <option value="bequest">{{ __('Bequest') }}</option>
                                <option value="transfer">{{ __('Transfer') }}</option>
                                <option value="exchange">{{ __('Exchange') }}</option>
                                <option value="found">{{ __('Found/Discovered') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Acquisition Source') }}</label>
                            <input type="text" name="acquisition_source" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Acquisition Cost') }}</label>
                            <input type="number" name="acquisition_cost" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Currency') }}</label>
                            <input type="text" name="acquisition_currency" class="form-control" maxlength="3" value="{{ $defaultCurrency }}" placeholder="{{ __('ISO 4217 code (e.g. USD, EUR, GBP)') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Valuation') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Basis') }}</label>
                            <select name="valuation_basis" class="form-select">
                                <option value="historical_cost">{{ __('Historical Cost') }}</option>
                                <option value="fair_value">{{ __('Fair Value') }}</option>
                                <option value="nominal">{{ __('Nominal Value') }}</option>
                                <option value="not_recognized">{{ __('Not Recognized') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Current Value') }}</label>
                            <input type="number" name="current_value" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Condition Rating') }}</label>
                            <select name="condition_rating" class="form-select">
                                <option value="excellent">{{ __('Excellent') }}</option>
                                <option value="good">{{ __('Good') }}</option>
                                <option value="fair">{{ __('Fair') }}</option>
                                <option value="poor">{{ __('Poor') }}</option>
                                <option value="critical">{{ __('Critical') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> {{ __('IPSAS Compliance') }}</h6>
                <p class="small mb-0">Assets are recognized under IPSAS 17 (Property, Plant and Equipment) and IPSAS 31 (Intangible Assets). Choose the appropriate valuation basis based on asset type and available information.</p>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>{{ __('Register Asset') }}</button>
                    <a href="{{ route('ipsas.assets') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
