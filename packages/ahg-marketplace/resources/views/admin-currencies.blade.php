{{--
  Marketplace Admin — Currencies

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminCurrenciesSuccess.php.

  International framing: PSIS labelled the rate column "Exchange Rate to ZAR" because
  the DB column is `exchange_rate_to_zar`. The column name is kept for schema compatibility,
  but the UI label is reframed as "Exchange Rate (to base currency)". Operators can configure
  the base currency per install; ZAR is just one possible base.
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Currencies') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace currencies')

@php
  $baseCurrency = config('heratio.base_currency', 'ZAR');
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Currencies') }}</li>
  </ol>
</nav>

@if(session('success') || session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') ?? session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Manage Currencies') }}</h1>

<div class="alert alert-info py-2 small">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('Base currency is') }} <strong>{{ $baseCurrency }}</strong>.
  {{ __('All exchange rates are relative to this base. Set via AHG Settings → Marketplace.') }}
</div>

{{-- Add currency form --}}
<div class="card mb-4">
  <div class="card-header"><h5 class="card-title mb-0">{{ __('Add Currency') }}</h5></div>
  <div class="card-body">
    <form method="POST" action="{{ route('ahgmarketplace.admin-currencies.post') }}" class="row g-2 align-items-end">
      @csrf
      <input type="hidden" name="form_action" value="add">
      <div class="col-md-2">
        <label class="form-label small">{{ __('Code') }}</label>
        <input type="text" name="code" class="form-control form-control-sm" required placeholder="USD" maxlength="3" style="text-transform:uppercase;">
      </div>
      <div class="col-md-3">
        <label class="form-label small">{{ __('Name') }}</label>
        <input type="text" name="name" class="form-control form-control-sm" required placeholder="{{ __('Currency name') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label small">{{ __('Symbol') }}</label>
        <input type="text" name="symbol" class="form-control form-control-sm" maxlength="5">
      </div>
      <div class="col-md-3">
        <label class="form-label small">{{ __('Rate to :base', ['base' => $baseCurrency]) }}</label>
        <input type="number" name="exchange_rate_to_zar" class="form-control form-control-sm" value="1.000000" step="0.000001" min="0.000001" required>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="fas fa-plus me-1"></i> {{ __('Add') }}
        </button>
      </div>
    </form>
  </div>
</div>

@if(empty($currencies) || count($currencies) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-money-bill fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No currencies configured') }}</h5>
      <p class="text-muted">{{ __('Add your first currency above.') }}</p>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Symbol') }}</th>
            <th class="text-end">{{ __('Rate to :base', ['base' => $baseCurrency]) }}</th>
            <th>{{ __('Active') }}</th>
            <th>{{ __('Updated') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($currencies as $currency)
            <tr>
              <td class="fw-semibold">{{ $currency->code ?? '' }}</td>
              <td>{{ $currency->name ?? '' }}</td>
              <td>{{ $currency->symbol ?? '-' }}</td>
              <td class="text-end">
                <form method="POST" action="{{ route('ahgmarketplace.admin-currencies.post') }}" class="d-inline-flex align-items-center">
                  @csrf
                  <input type="hidden" name="form_action" value="update">
                  <input type="hidden" name="code" value="{{ $currency->code ?? '' }}">
                  <input type="number" name="exchange_rate_to_zar" class="form-control form-control-sm me-1" value="{{ number_format((float) ($currency->exchange_rate_to_zar ?? 1), 6, '.', '') }}" step="0.000001" min="0.000001" style="width:130px;">
                  <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('Update Rate') }}">
                    <i class="fas fa-save"></i>
                  </button>
                </form>
              </td>
              <td>
                @if($currency->is_active ?? 1)
                  <span class="badge bg-success">{{ __('Active') }}</span>
                @else
                  <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                @endif
              </td>
              <td class="small text-muted">{{ ($currency->updated_at ?? null) ? date('d M Y', strtotime($currency->updated_at)) : '-' }}</td>
              <td class="text-end">
                <form method="POST" action="{{ route('ahgmarketplace.admin-currencies.post') }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="form_action" value="toggle">
                  <input type="hidden" name="code" value="{{ $currency->code ?? '' }}">
                  @if($currency->is_active ?? 1)
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Deactivate') }}">
                      <i class="fas fa-toggle-on"></i>
                    </button>
                  @else
                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Activate') }}">
                      <i class="fas fa-toggle-off"></i>
                    </button>
                  @endif
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection
