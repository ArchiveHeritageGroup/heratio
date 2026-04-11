{{--
  Marketplace Settings — dynamic DB-driven settings grouped by setting_group
  Cloned from AtoM ahgMarketplacePlugin adminSettingsSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Marketplace Settings')
@section('body-class', 'admin settings')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">Marketplace Admin</a></li>
    <li class="breadcrumb-item active">Settings</li>
  </ol>
</nav>

@if(session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">Marketplace Settings</h1>

@if(empty($settings) || (is_countable($settings) && count($settings) === 0))
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-cog fa-3x text-muted mb-3 d-block"></i>
      <h5>No settings configured</h5>
      <p class="text-muted">Run the marketplace install to populate default settings.</p>
    </div>
  </div>
@else
  <form method="post" action="{{ route('ahgmarketplace.admin-settings.post') }}">
    @csrf
    <input type="hidden" name="form_action" value="save">

    @php
      $grouped = [];
      foreach ($settings as $setting) {
          $group = $setting->setting_group ?? 'general';
          $grouped[$group][] = $setting;
      }
      ksort($grouped);
    @endphp

    @foreach($grouped as $groupName => $groupSettings)
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0">{{ ucfirst(str_replace('_', ' ', $groupName)) }}</h5>
        </div>
        <div class="card-body">
          @foreach($groupSettings as $setting)
            @php
              $key = $setting->setting_key;
              $type = $setting->setting_type ?? 'string';
              $value = $setting->setting_value ?? '';
              $desc = $setting->description ?? '';
              $inputName = 'setting_' . $key;
            @endphp
            <div class="mb-3 row">
              <label class="col-sm-4 col-form-label" for="{{ e($inputName) }}">
                {{ e($key) }}
              </label>
              <div class="col-sm-8">
                @if($type === 'boolean')
                  <div class="form-check mt-2">
                    <input type="hidden" name="{{ e($inputName) }}" value="0">
                    <input type="checkbox" class="form-check-input" name="{{ e($inputName) }}"
                           id="{{ e($inputName) }}" value="1"
                           {{ ($value && $value !== '0' && $value !== 'false') ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ e($inputName) }}">Enabled</label>
                  </div>
                @elseif($type === 'number' || $type === 'integer' || $type === 'float')
                  <input type="number" class="form-control" name="{{ e($inputName) }}"
                         id="{{ e($inputName) }}" value="{{ e($value) }}"
                         {{ $type === 'float' ? 'step="0.01"' : '' }}>
                @elseif($type === 'json')
                  <textarea class="form-control font-monospace" name="{{ e($inputName) }}"
                            id="{{ e($inputName) }}" rows="3">{{ e($value) }}</textarea>
                @else
                  <input type="text" class="form-control" name="{{ e($inputName) }}"
                         id="{{ e($inputName) }}" value="{{ e($value) }}">
                @endif
                @if($desc)
                  <div class="form-text">{{ e($desc) }}</div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach

    <div class="text-end mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save All Settings
      </button>
    </div>
  </form>
@endif

<div class="mt-4">
  <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back to Admin
  </a>
  @if(Route::has('ahgmarketplace.admin-dashboard'))
  <a href="{{ route('ahgmarketplace.admin-dashboard') }}" class="btn btn-outline-primary ms-2">
    <i class="fas fa-tachometer-alt me-2"></i>Marketplace Dashboard
  </a>
  @endif
</div>
@endsection
