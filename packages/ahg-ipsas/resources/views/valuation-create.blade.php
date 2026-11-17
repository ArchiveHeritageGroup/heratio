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
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.valuations') }}">{{ __('Valuations') }}</a></li>
                    <li class="breadcrumb-item active">{{ __('New Valuation') }}</li>
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
                    <label class="form-label">{{ __('Asset') }} <span class="text-danger">*</span></label>
                    @if($assetChoices->isEmpty())
                        <div class="alert alert-warning mb-0">{{ __('No active assets are registered yet.') }} <a href="{{ route('ipsas.asset.create') }}">{{ __('Register an asset') }}</a></div>
                    @else
                    <select name="asset_id" class="form-select" required>
                        <option value="">{{ __('Select...') }}</option>
                        @foreach($assetChoices as $choice)
                            <option value="{{ $choice->id }}" {{ (string) old('asset_id') === (string) $choice->id ? 'selected' : '' }}>{{ $choice->asset_number }} - {{ \Illuminate\Support\Str::limit($choice->title, 60) }} ({{ $currency }} {{ number_format((float) $choice->current_value, 2) }})</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Enter the current value of the asset as the previous value below.') }}</div>
                    @endif
                </div>
            </div>
            @endif

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Valuation Details') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="valuation_date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Type') }}</label>
                            <select name="valuation_type" class="form-select">
                                @foreach($opts['valuation_type'] as $code => $lbl)
                                    <option value="{{ $code }}" {{ old('valuation_type') === $code ? 'selected' : '' }}>{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Basis') }}</label>
                            <select name="valuation_basis" class="form-select">
                                @foreach($opts['valuation_basis'] as $code => $lbl)
                                    <option value="{{ $code }}" {{ old('valuation_basis') === $code ? 'selected' : '' }}>{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Valuation Method') }}</label>
                            <select name="valuation_method" class="form-select">
                                @foreach($opts['valuation_method'] as $code => $lbl)
                                    <option value="{{ $code }}" {{ old('valuation_method') === $code ? 'selected' : '' }}>{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Previous Value') }}</label>
                            <input type="number" name="previous_value" class="form-control" step="0.01" min="0" value="{{ $asset->current_value ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('New Value') }} <span class="text-danger">*</span></label>
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
                                @foreach($opts['valuer_type'] as $code => $lbl)
                                    <option value="{{ $code }}" {{ old('valuer_type') === $code ? 'selected' : '' }}>{{ __($lbl) }}</option>
                                @endforeach
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
                <p class="small mb-0">{{ __('Record valuation changes as required by IPSAS 17 and IPSAS 21 (Impairment of Non-Cash-Generating Assets). Ensure proper documentation and valuer credentials for audit compliance.') }}</p>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>{{ __('Record Valuation') }}</button>
                    <a href="{{ route('ipsas.valuations') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
