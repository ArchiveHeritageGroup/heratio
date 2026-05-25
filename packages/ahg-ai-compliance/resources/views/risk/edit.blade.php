@extends('theme::layouts.2col')
@section('title', $risk ? 'Edit risk' : 'Add risk')
@section('body-class', 'admin ai-compliance')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => []])
@endsection

@section('title-block')
  <h1>{{ $risk ? __('Edit risk') : __('Add risk') }}</h1>
  <p class="text-muted small mb-0">{{ __('EU AI Act Article 9') }}</p>
@endsection

@section('content')

<form method="post" action="{{ $risk ? route('ai-compliance.risk.update', $risk->id) : route('ai-compliance.risk.store') }}" autocomplete="off">
  @csrf
  @if ($risk)
    @method('PUT')
  @endif

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <label class="form-label">{{ __('Service') }} <span class="text-danger">*</span></label>
      <select name="service" class="form-select" required>
        @foreach ($services as $svc)
          <option value="{{ $svc }}" @selected(($risk->service ?? old('service')) === $svc)>{{ strtoupper($svc) }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ __('Severity') }} <span class="text-danger">*</span></label>
      <select name="severity" class="form-select" required>
        @foreach ($severities as $s)
          <option value="{{ $s }}" @selected(($risk->severity ?? old('severity', 'medium')) === $s)>{{ __(ucfirst($s)) }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ __('Likelihood') }} <span class="text-danger">*</span></label>
      <select name="likelihood" class="form-select" required>
        @foreach ($likelihoods as $l)
          <option value="{{ $l }}" @selected(($risk->likelihood ?? old('likelihood', 'medium')) === $l)>{{ __(ucfirst($l)) }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">{{ __('Risk description') }} <span class="text-danger">*</span></label>
    <textarea name="risk_description" class="form-control" rows="2" required>{{ $risk->risk_description ?? old('risk_description') }}</textarea>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <label class="form-label">{{ __('Source of risk') }} <span class="text-danger">*</span></label>
      <select name="intended_or_misuse" class="form-select" required>
        @foreach ($usage as $u)
          <option value="{{ $u }}" @selected(($risk->intended_or_misuse ?? old('intended_or_misuse', 'intended')) === $u)>{{ $u === 'intended' ? __('Intended use') : __('Reasonably foreseeable misuse') }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ __('Affected group') }}</label>
      <input type="text" name="affected_group" class="form-control" value="{{ $risk->affected_group ?? old('affected_group') }}" placeholder="{{ __('e.g. researchers, indigenous_language_collections, data_subjects') }}">
      <small class="form-text text-muted">{{ __('Article 9(9): vulnerable groups trigger elevated review severity') }}</small>
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">{{ __('Mitigation') }}</label>
    <textarea name="mitigation" class="form-control" rows="3">{{ $risk->mitigation ?? old('mitigation') }}</textarea>
    <small class="form-text text-muted">{{ __('Concrete controls in place: human review, confidence gating, audit, etc.') }}</small>
  </div>

  <div class="mb-4">
    <label class="form-label">{{ __('Residual risk after mitigation') }} <span class="text-danger">*</span></label>
    <select name="residual_risk" class="form-select" required>
      @foreach ($severities as $s)
        <option value="{{ $s }}" @selected(($risk->residual_risk ?? old('residual_risk', 'low')) === $s)>{{ __(ucfirst($s)) }}</option>
      @endforeach
    </select>
  </div>

  <div class="d-flex justify-content-between">
    <a href="{{ route('ai-compliance.risk.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('Cancel') }}</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> {{ $risk ? __('Save changes') : __('Add risk') }}</button>
  </div>
</form>

@endsection
