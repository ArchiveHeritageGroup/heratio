{{--
  Registry Admin — Settings
  Cloned from PSIS adminSettingsSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Settings') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-settings')

@php
  // Derive group from setting_key prefix (e.g. `smtp_host` → `smtp`).
  $grouped = $settings->groupBy(function ($s) {
      $key = $s->setting_key ?? '';
      return str_contains($key, '_') ? substr($key, 0, strpos($key, '_')) : 'general';
  });
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Settings') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-cog me-2"></i>{{ __('Registry Settings') }}</h1>

<form method="post" action="{{ route('registry.admin.settings') }}">
  @csrf

  @forelse($grouped as $groupName => $groupSettings)
    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ ucfirst($groupName ?: 'general') }}</div>
      <div class="card-body">
        @foreach($groupSettings as $s)
          <div class="mb-3">
            <label class="form-label">{{ $s->setting_key ?? '' }}</label>
            @if(($s->setting_type ?? 'text') === 'boolean')
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="setting_{{ $s->setting_key }}" value="1" {{ $s->setting_value ? 'checked' : '' }}>
              </div>
            @elseif(($s->setting_type ?? 'text') === 'textarea')
              <textarea class="form-control" name="setting_{{ $s->setting_key }}" rows="4">{{ $s->setting_value }}</textarea>
            @else
              <input type="text" class="form-control" name="setting_{{ $s->setting_key }}" value="{{ $s->setting_value }}">
            @endif
            @if(!empty($s->description))
              <div class="form-text">{{ $s->description }}</div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @empty
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="fas fa-cog fa-2x mb-2"></i>
        <p class="mb-0">{{ __('No settings configured yet.') }}</p>
      </div>
    </div>
  @endforelse

  @if($grouped->isNotEmpty())
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save settings') }}</button>
  @endif
</form>
@endsection
