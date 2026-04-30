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

@section('title', 'NAZ Configuration')

@section('content')
@php
    // Controller passes $settings; PSIS template used $config — alias for parity.
    $config = $settings ?? [];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item active">Configuration</li>
                </ol>
            </nav>
            <h1><i class="fas fa-cog me-2"></i>{{ __('NAZ Configuration') }}</h1>
        </div>
    </div>

    @if(session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Closure Period Settings') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Default Closure Period (years)') }}</label>
                            <input type="number" name="closure_period_years" class="form-control" value="{{ $config['closure_period_years'] ?? '25' }}">
                            <small class="text-muted">{{ __('Per Section 10 of the NAZ Act') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Research Permit Fees') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Foreign Researcher Fee (USD)') }}</label>
                            <input type="number" name="foreign_permit_fee_usd" class="form-control" value="{{ $config['foreign_permit_fee_usd'] ?? '200' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Local Researcher Fee (USD)') }}</label>
                            <input type="number" name="local_permit_fee_usd" class="form-control" value="{{ $config['local_permit_fee_usd'] ?? '0' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Permit Validity (months)') }}</label>
                            <input type="number" name="permit_validity_months" class="form-control" value="{{ $config['permit_validity_months'] ?? '12' }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Contact Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Director Name') }}</label>
                            <input type="text" name="director_name" class="form-control" value="{{ $config['director_name'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('NAZ Email') }}</label>
                            <input type="email" name="naz_email" class="form-control" value="{{ $config['naz_email'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('NAZ Phone') }}</label>
                            <input type="tel" name="naz_phone" class="form-control" value="{{ $config['naz_phone'] ?? '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
