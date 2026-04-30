@extends('theme::layouts.2col')
@section('title', 'Sector Numbering Schemes')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-hashtag me-2"></i>Sector Numbering Schemes</h1>
@endsection

@section('content')
    <div class="alert alert-info" role="alert">
      <i class="fas fa-info-circle me-2"></i>
      Configure unique identifier numbering schemes per GLAM/DAM sector. Leave fields blank to inherit the global settings.
      <br><small class="text-muted">{{ __('Note: Accession numbering uses a single global counter across all sectors.') }}</small>
    </div>

    <div class="card mb-4">
      <div class="card-header bg-secondary text-white"><i class="fas fa-globe me-2"></i>Current Global Identifier Settings (Reference)</div>
      <div class="card-body">
        <dl class="row small mb-0">
          <dt class="col-sm-3">Mask Enabled</dt>
          <dd class="col-sm-3"><code>{{ ($globalValues['identifier_mask_enabled'] ?? '0') ? 'Yes' : 'No' }}</code></dd>
          <dt class="col-sm-3">Mask</dt>
          <dd class="col-sm-3"><code>{{ $globalValues['identifier_mask'] ?? '-' }}</code></dd>
          <dt class="col-sm-3">Counter</dt>
          <dd class="col-sm-3"><code>{{ $globalValues['identifier_counter'] ?? '-' }}</code></dd>
        </dl>
        <div class="text-end mt-2">
          <a href="{{ route('settings.identifier') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-cog me-1"></i>{{ __('Edit Global Settings') }}</a>
        </div>
      </div>
    </div>

    <form method="post" action="{{ route('settings.sector-numbering') }}">
      @csrf
      @php
        $sectorDefaults = [
          'archive' => 'ARCH/%Y%/%04i%',
          'museum'  => 'MUS.%Y%.%04i%',
          'library' => 'LIB/%Y%/%04i%',
          'gallery' => 'GAL.%Y%.%04i%',
          'dam'     => 'DAM-%Y%-%06i%',
        ];
      @endphp

      @forelse($sectors ?? [] as $code => $label)
        <div class="card mb-3">
          <div class="card-header"><i class="fas fa-fingerprint me-2"></i>{{ ucfirst($label) }} — Identifier Numbering</div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="form-check form-switch mb-3">
                  <input type="hidden" name="sector_{{ $code }}__identifier_mask_enabled" value="0">
                  <input class="form-check-input" type="checkbox" name="sector_{{ $code }}__identifier_mask_enabled" value="1" id="mask_{{ $code }}" {{ ($sectorSettings[$code]['identifier_mask_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="mask_{{ $code }}">Mask enabled <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Identifier mask <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <input type="text" name="sector_{{ $code }}__identifier_mask" class="form-control" value="{{ $sectorSettings[$code]['identifier_mask'] ?? '' }}" placeholder="{{ $sectorDefaults[$code] ?? '' }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Counter <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <input type="number" name="sector_{{ $code }}__identifier_counter" class="form-control" value="{{ $sectorSettings[$code]['identifier_counter'] ?? '0' }}" min="0">
                </div>
              </div>
            </div>
          </div>
        </div>
      @empty
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No GLAM/DAM sectors detected. Enable sector plugins to configure numbering.</div>
      @endforelse

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
@endsection
