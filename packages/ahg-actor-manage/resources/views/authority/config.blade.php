@extends('theme::layouts.1col')

@section('title', 'Authority Plugin Configuration')
@section('body-class', 'authority config')

@section('content')

@php
  function cfgVal($cfg, $key, $default = '') {
    return isset($cfg[$key]) ? ($cfg[$key]->config_value ?? $default) : $default;
  }
  function cfgChecked($cfg, $key) {
    return cfgVal($cfg, $key, '0') === '1' ? 'checked' : '';
  }
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Configuration</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-cog me-2"></i>Authority Plugin Configuration</h1>

@if (session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="post" action="{{ route('actor.config') }}">
  @csrf

  {{-- External Authority Sources --}}
  <div class="card mb-3">
    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
      <i class="fas fa-globe me-1"></i>External Authority Sources
    </div>
    <div class="card-body">
      <div class="row g-3">
        @php
          $sources = [
            'wikidata' => 'Wikidata',
            'viaf' => 'VIAF',
            'ulan' => 'ULAN (Getty)',
            'lcnaf' => 'LCNAF (Library of Congress)',
            'isni' => 'ISNI',
          ];
        @endphp
        @foreach ($sources as $key => $label)
          <div class="col-md-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="{{ $key }}_enabled"
                     name="config[{{ $key }}_enabled]" value="1"
                     {{ cfgChecked($config, $key . '_enabled') }}>
              <label class="form-check-label" for="{{ $key }}_enabled">{{ $label }}</label>
            </div>
          </div>
        @endforeach
        <div class="col-md-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="auto_verify_wikidata"
                   name="config[auto_verify_wikidata]" value="1"
                   {{ cfgChecked($config, 'auto_verify_wikidata') }}>
            <label class="form-check-label" for="auto_verify_wikidata">
              Auto-verify Wikidata matches
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Completeness & Quality --}}
  <div class="card mb-3">
    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
      <i class="fas fa-chart-bar me-1"></i>Completeness & Quality
    </div>
    <div class="card-body">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="completeness_auto_recalc"
               name="config[completeness_auto_recalc]" value="1"
               {{ cfgChecked($config, 'completeness_auto_recalc') }}>
        <label class="form-check-label" for="completeness_auto_recalc">
          Auto-recalculate completeness scores
        </label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="hide_stubs_from_public"
               name="config[hide_stubs_from_public]" value="1"
               {{ cfgChecked($config, 'hide_stubs_from_public') }}>
        <label class="form-check-label" for="hide_stubs_from_public">
          Hide stub records from public view
        </label>
      </div>
    </div>
  </div>

  {{-- NER Pipeline --}}
  <div class="card mb-3">
    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
      <i class="fas fa-robot me-1"></i>NER Pipeline
    </div>
    <div class="card-body">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="ner_auto_stub_enabled"
               name="config[ner_auto_stub_enabled]" value="1"
               {{ cfgChecked($config, 'ner_auto_stub_enabled') }}>
        <label class="form-check-label" for="ner_auto_stub_enabled">
          Auto-create stubs from NER entities
        </label>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Minimum confidence threshold') }}</label>
        <input type="number" name="config[ner_auto_stub_threshold]" class="form-control" style="max-width:200px"
               value="{{ e(cfgVal($config, 'ner_auto_stub_threshold', '0.85')) }}"
               min="0" max="1" step="0.05">
      </div>
    </div>
  </div>

  {{-- Merge / Dedup --}}
  <div class="card mb-3">
    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
      <i class="fas fa-clone me-1"></i>Merge & Deduplication
    </div>
    <div class="card-body">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="merge_require_approval"
               name="config[merge_require_approval]" value="1"
               {{ cfgChecked($config, 'merge_require_approval') }}>
        <label class="form-check-label" for="merge_require_approval">
          Require approval for merge operations
        </label>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Deduplication threshold (0-1)') }}</label>
        <input type="number" name="config[dedup_threshold]" class="form-control" style="max-width:200px"
               value="{{ e(cfgVal($config, 'dedup_threshold', '0.80')) }}"
               min="0" max="1" step="0.05">
      </div>
    </div>
  </div>

  {{-- ISDF Functions --}}
  <div class="card mb-3">
    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
      <i class="fas fa-sitemap me-1"></i>ISDF Functions
    </div>
    <div class="card-body">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="function_linking_enabled"
               name="config[function_linking_enabled]" value="1"
               {{ cfgChecked($config, 'function_linking_enabled') }}>
        <label class="form-check-label" for="function_linking_enabled">
          Enable structured function linking
        </label>
      </div>
    </div>
  </div>

  <div class="mb-3">
    <button type="submit" class="btn atom-btn-white">
      <i class="fas fa-save me-1"></i>Save configuration
    </button>
    <a href="{{ route('actor.dashboard') }}" class="btn btn-outline-secondary ms-2">
      Cancel
    </a>
  </div>
</form>

@endsection
