{{--
  Heratio - record a digitisation-provenance entry (issue #1201).

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Record digitisation provenance'))
@section('body-class', 'admin c2pa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-fingerprint me-2"></i>{{ __('Record digitisation provenance') }}</h1>
  <a href="{{ route('c2pa.provenance.index', ['informationObjectId' => $informationObjectId]) }}" class="btn btn-sm atom-btn-white">
    <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
  </a>
</div>

@if($object)
  <p class="text-muted">{{ __('Information object') }}:
    <strong>{{ $object->title ?? $object->identifier ?? ('#' . $object->id) }}</strong>
    <span class="badge bg-secondary ms-1">IO #{{ $object->id }}</span>
  </p>
@endif

<div class="alert {{ $capability['can_sign_manifest'] ? 'alert-info' : 'alert-warning' }}">
  <i class="fas fa-info-circle me-1"></i> {{ $capability['summary'] }}
</div>

@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card">
  <div class="card-body">
    <form method="post" action="{{ route('c2pa.provenance.store', ['informationObjectId' => $informationObjectId]) }}">
      @csrf
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">{{ __('Master digital object') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <select name="digital_object_id" class="form-select form-select-sm">
            <option value="">{{ __('- none / applies to the record as a whole -') }}</option>
            @foreach($digitalObjects as $do)
              <option value="{{ $do->id }}">#{{ $do->id }} - {{ $do->name ?? $do->path }} ({{ $do->mime_type ?? '?' }})</option>
            @endforeach
          </select>
          <div class="form-text">{{ __('When chosen, the manifest hashes the real file and writes a .c2pa.json sidecar next to it.') }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Captured by') }}</label>
          <input type="text" name="captured_by" class="form-control form-control-sm" value="{{ old('captured_by') }}" maxlength="255">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Captured at') }}</label>
          <input type="datetime-local" name="captured_at" class="form-control form-control-sm" value="{{ old('captured_at') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Capture device') }}</label>
          <input type="text" name="capture_device" class="form-control form-control-sm" value="{{ old('capture_device') }}" maxlength="255" placeholder="e.g. Phase One iXG / Artec Leo">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Capture software') }}</label>
          <input type="text" name="capture_software" class="form-control form-control-sm" value="{{ old('capture_software') }}" maxlength="255" placeholder="e.g. Capture One 23">
        </div>
        <div class="col-12">
          <label class="form-label">{{ __('Notes') }}</label>
          <textarea name="notes" class="form-control form-control-sm" rows="3" maxlength="65535">{{ old('notes') }}</textarea>
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-certificate me-1"></i>{{ __('Create & sign provenance record') }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
