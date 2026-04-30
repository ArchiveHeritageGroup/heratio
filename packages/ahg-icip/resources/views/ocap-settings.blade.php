{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+
  OCAP overlay settings page.
--}}
@extends('theme::layouts.1col')

@section('title', 'OCAP® Settings')

@section('content')
<div class="container my-4" style="max-width:760px;">
  <h1 class="h3 mb-3"><i class="fas fa-shield-alt me-2"></i>{{ __('OCAP® Settings') }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="POST" action="{{ route('ahgicip.ocap-settings') }}">
    @csrf
    <div class="card">
      <div class="card-header bg-light"><strong>{{ __('Per-market overlay') }}</strong></div>
      <div class="card-body">
        <div class="form-check form-switch mb-3">
          <input type="hidden" name="ocap_enabled" value="0">
          <input class="form-check-input" type="checkbox" id="ocap_enabled"
                 name="ocap_enabled" value="1" {{ $enabled ? 'checked' : '' }}>
          <label class="form-check-label" for="ocap_enabled">{{ __('Enable OCAP overlay') }}</label>
        </div>

        <p class="text-muted small mb-2">
          OCAP® (Ownership, Control, Access, Possession) is a First Nations data-sovereignty
          framework. Heratio ships it as an opt-in overlay so that the platform stays
          jurisdiction-neutral by default.
        </p>
        <p class="text-muted small mb-0">
          Typical markets where you would enable this: Canada (BAC-LAC, Indigenous Services
          Canada), Australia (AIATSIS), Aotearoa New Zealand (Te Mana Raraunga), other
          jurisdictions with Indigenous-data governance regimes.
        </p>
      </div>
      <div class="card-footer text-end">
        <a href="{{ route('ahgicip.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
      </div>
    </div>
  </form>
</div>
@endsection
