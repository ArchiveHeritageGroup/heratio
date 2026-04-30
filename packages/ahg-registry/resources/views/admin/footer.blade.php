{{--
  Registry Admin — Footer Management
  Cloned from PSIS adminFooterSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Footer') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-footer')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Footer') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-shoe-prints me-2"></i>{{ __('Footer Management') }}</h1>

<form method="post" action="{{ route('registry.admin.footer') }}">
  @csrf

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Copyright') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Copyright notice') }}</label>
        <input type="text" class="form-control" name="footer_copyright" value="{{ $settings['footer_copyright'] ?? '' }}">
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Footer Links') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Links (JSON)') }}</label>
        <textarea class="form-control font-monospace" name="footer_links" rows="6">{{ $settings['footer_links'] ?? '' }}</textarea>
        <div class="form-text">{{ __('Array of {label, url} objects.') }}</div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Social Media') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Social links (JSON)') }}</label>
        <textarea class="form-control font-monospace" name="footer_social" rows="4">{{ $settings['footer_social'] ?? '' }}</textarea>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Address') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Address (plain text or HTML)') }}</label>
        <textarea class="form-control" name="footer_address" rows="3">{{ $settings['footer_address'] ?? '' }}</textarea>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save footer') }}</button>
</form>
@endsection
