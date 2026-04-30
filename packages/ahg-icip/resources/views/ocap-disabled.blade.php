{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+
  OCAP overlay — disabled state.
--}}
@extends('theme::layouts.1col')

@section('title', 'OCAP® Overlay')

@section('content')
<div class="container my-4">
  <h1 class="h3 mb-3"><i class="fas fa-shield-alt me-2"></i>OCAP® Overlay</h1>

  <div class="alert alert-warning">
    <strong>{{ __('OCAP overlay is disabled.') }}</strong>
    OCAP® (Ownership, Control, Access, Possession) is a First Nations data-sovereignty framework
    used in Canada, Australia, Aotearoa New Zealand, and other Indigenous-data jurisdictions.
    Heratio ships it as a pluggable per-market overlay, off by default.
  </div>

  <p>Enable the overlay to surface a per-record traffic-light assessment of the four OCAP principles
     against existing ICIP data (consents, restrictions, communities, custody).</p>

  <a href="{{ $enableUrl }}" class="btn btn-primary">
    <i class="fas fa-cog me-1"></i>{{ __('Configure OCAP') }}
  </a>
</div>
@endsection
