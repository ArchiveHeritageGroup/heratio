@extends('theme::layouts.1col')

@section('title', $mode === 'create' ? __('Add processing activity') : __('Edit processing activity'))

@section('content')
@php
  $isCreate = $mode === 'create';
  $action = $isCreate
    ? route('ahgprivacy.article-30.store')
    : route('ahgprivacy.article-30.update', ['id' => $activity->id]);
@endphp

<div class="d-flex align-items-center mb-3">
  <a href="{{ route('ahgprivacy.article-30.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to register') }}">
    <i class="fas fa-arrow-left"></i>
  </a>
  <h1 class="h2 mb-0">
    <i class="fas fa-clipboard-list me-2"></i>
    {{ $isCreate ? __('Add processing activity') : __('Edit processing activity') }}
  </h1>
</div>

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ $action }}">
  @csrf
  @if (! $isCreate)
    @method('PUT')
  @endif

  <div class="card mb-3">
    <div class="card-header">{{ __('Identification') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required maxlength="255"
          value="{{ old('name', $activity->name) }}">
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Purpose of processing') }} <span class="text-danger">*</span></label>
        <textarea name="purpose" class="form-control" rows="3" required>{{ old('purpose', $activity->purpose) }}</textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Lawful basis (GDPR Art. 6)') }}</label>
        <select name="lawful_basis" class="form-select">
          @foreach (\AhgPrivacy\Models\ProcessingActivity::LAWFUL_BASES as $basis)
            <option value="{{ $basis }}" @selected(old('lawful_basis', $activity->lawful_basis) === $basis)>
              {{ $basis }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">{{ __('Data scope') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">{{ __('Categories of data') }}</label>
          <input type="text" name="categories_of_data" class="form-control"
            value="{{ old('categories_of_data', is_array($activity->categories_of_data) ? implode(', ', $activity->categories_of_data) : '') }}"
            placeholder="{{ __('contact, identifiers, financial') }}">
          <small class="form-text text-muted">{{ __('Comma- or semicolon-separated.') }}</small>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Categories of subjects') }}</label>
          <input type="text" name="categories_of_subjects" class="form-control"
            value="{{ old('categories_of_subjects', is_array($activity->categories_of_subjects) ? implode(', ', $activity->categories_of_subjects) : '') }}"
            placeholder="{{ __('users, researchers, donors') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Recipients') }}</label>
          <input type="text" name="recipients" class="form-control"
            value="{{ old('recipients', is_array($activity->recipients) ? implode(', ', $activity->recipients) : '') }}"
            placeholder="{{ __('internal, processors') }}">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">{{ __('Retention and security') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">{{ __('Retention period') }}</label>
          <input type="text" name="retention_period" class="form-control" maxlength="255"
            value="{{ old('retention_period', $activity->retention_period) }}">
        </div>
        <div class="col-md-8">
          <label class="form-label">{{ __('Security measures') }}</label>
          <textarea name="security_measures" class="form-control" rows="2">{{ old('security_measures', $activity->security_measures) }}</textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">{{ __('Cross-border transfers') }}</div>
    <div class="card-body">
      <div class="form-check mb-2">
        <input type="hidden" name="transfers_outside_eea" value="0">
        <input type="checkbox" name="transfers_outside_eea" id="transfers_outside_eea" value="1" class="form-check-input"
          @checked(old('transfers_outside_eea', $activity->transfers_outside_eea))>
        <label class="form-check-label" for="transfers_outside_eea">{{ __('Transfers personal data outside the EEA?') }}</label>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Safeguards (SCC, BCR, adequacy decision, derogation)') }}</label>
        <textarea name="safeguards" class="form-control" rows="2">{{ old('safeguards', $activity->safeguards) }}</textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('DPO contact') }}</label>
        <input type="text" name="dpo_contact" class="form-control" maxlength="255"
          value="{{ old('dpo_contact', $activity->dpo_contact) }}">
      </div>
      <div class="form-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
          @checked(old('is_active', $isCreate ? true : $activity->is_active))>
        <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <i class="fas fa-shield-alt me-1"></i>{{ __('High-risk screening (DPIA)') }}
    </div>
    <div class="card-body">
      <p class="small text-muted">
        {{ __('Heratio screens this activity automatically against the GDPR Art. 35(3) high-risk triggers (special category data, large-scale profiling, biometric processing, cross-border transfer without safeguards). Leave each override on "Auto" to let the screen decide, or force a determination as the DPO.') }}
      </p>
      @php
        $overrideOptions = ['' => __('Auto (screen decides)'), '1' => __('Yes - high risk'), '0' => __('No')];
        $overrideFields = [
          'special_category_override'      => __('Special category data'),
          'large_scale_profiling_override' => __('Large-scale profiling / monitoring'),
          'biometric_override'             => __('Biometric / genetic processing'),
        ];
      @endphp
      <div class="row g-3">
        @foreach ($overrideFields as $field => $label)
          @php $current = old($field, $activity->{$field}); $current = ($current === null ? '' : (string) $current); @endphp
          <div class="col-md-4">
            <label class="form-label">{{ $label }}</label>
            <select name="{{ $field }}" class="form-select">
              @foreach ($overrideOptions as $val => $text)
                <option value="{{ $val }}" @selected($current === (string) $val)>{{ $text }}</option>
              @endforeach
            </select>
          </div>
        @endforeach
      </div>
      @if (! $isCreate && $activity->dpia_screening_note)
        <div class="alert {{ $activity->dpia_required ? 'alert-warning' : 'alert-light' }} mt-3 mb-0 py-2 small">
          <i class="fas {{ $activity->dpia_required ? 'fa-exclamation-triangle' : 'fa-check' }} me-1"></i>
          {{ $activity->dpia_screening_note }}
          @if ($activity->dpia_required)
            @if ($activity->dpia_completed)
              <span class="badge bg-success ms-1">{{ __('DPIA completed') }}@if ($activity->dpia_date) - {{ $activity->dpia_date->format('Y-m-d') }}@endif</span>
            @else
              <a href="{{ route('ahgprivacy.dpia.create') }}" class="alert-link ms-1">{{ __('Start a DPIA') }}</a>
            @endif
          @endif
        </div>
      @endif
    </div>
  </div>

  <div class="d-flex justify-content-end">
    <a href="{{ route('ahgprivacy.article-30.index') }}" class="btn btn-light me-2">{{ __('Cancel') }}</a>
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i>{{ __('Save') }}
    </button>
  </div>
</form>
@endsection
