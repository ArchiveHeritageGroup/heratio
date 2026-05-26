@extends('theme::layouts.1col')

@section('title', $mode === 'create' ? __('Start DPIA') : __('Edit DPIA'))

@section('content')
@php
  $isCreate = $mode === 'create';
  $action = $isCreate
    ? route('ahgprivacy.dpia.store')
    : route('ahgprivacy.dpia.update', ['id' => $dpia->id]);
  $steps = [
    1 => __('1. Necessity'),
    2 => __('2. Risks'),
    3 => __('3. Mitigation'),
    4 => __('4. Sign-off'),
  ];
@endphp

<div class="d-flex align-items-center mb-3">
  <a href="{{ route('ahgprivacy.dpia.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to DPIAs') }}">
    <i class="fas fa-arrow-left"></i>
  </a>
  <h1 class="h2 mb-0">
    <i class="fas fa-shield-alt me-2"></i>
    {{ $isCreate ? __('Start DPIA') : ($dpia->name ?: __('DPIA')) }}
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

@if (session('status'))
  <div class="alert alert-info">{{ session('status') }}</div>
@endif

@if (! $isCreate)
  <ul class="nav nav-pills mb-3">
    @foreach ($steps as $n => $label)
      <li class="nav-item">
        <a class="nav-link @if ($step === $n) active @endif" href="{{ route('ahgprivacy.dpia.edit', ['id' => $dpia->id, 'step' => $n]) }}">{{ $label }}</a>
      </li>
    @endforeach
  </ul>
@endif

<form method="POST" action="{{ $action }}">
  @csrf
  @if (! $isCreate)
    @method('PUT')
  @endif
  <input type="hidden" name="step" value="{{ $step }}">

  <div class="card mb-3">
    <div class="card-header">{{ __('Identification') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required maxlength="255"
            value="{{ old('name', $dpia->name) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Linked processing activity') }}</label>
          <select name="processing_activity_id" class="form-select">
            <option value="">{{ __('-- not linked --') }}</option>
            @foreach ($activities as $a)
              <option value="{{ $a->id }}" @selected(old('processing_activity_id', $dpia->processing_activity_id) == $a->id)>
                {{ $a->name }}
              </option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mt-3">
        <label class="form-label">{{ __('Description') }}</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $dpia->description) }}</textarea>
      </div>
    </div>
  </div>

  @if ($step === 1)
    <div class="card mb-3">
      <div class="card-header">{{ __('Step 1 - Necessity and proportionality') }}</div>
      <div class="card-body">
        <textarea name="necessity_proportionality" class="form-control" rows="8" placeholder="{{ __('How is the processing necessary for the purpose? Is it proportionate to the rights involved?') }}">{{ old('necessity_proportionality', $dpia->necessity_proportionality) }}</textarea>
      </div>
    </div>
  @endif

  @if ($step === 2)
    <div class="card mb-3">
      <div class="card-header">{{ __('Step 2 - Risks to data subjects') }}</div>
      <div class="card-body">
        <textarea name="risks_to_subjects" class="form-control" rows="8" placeholder="{{ __('Identify risks to data subjects (confidentiality, integrity, availability, discrimination, profiling, secondary use, etc.)') }}">{{ old('risks_to_subjects', $dpia->risks_to_subjects) }}</textarea>
      </div>
    </div>
  @endif

  @if ($step === 3)
    <div class="card mb-3">
      <div class="card-header">{{ __('Step 3 - Mitigation and residual risk') }}</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Measures to mitigate the risks') }}</label>
          <textarea name="measures_to_mitigate" class="form-control" rows="5">{{ old('measures_to_mitigate', $dpia->measures_to_mitigate) }}</textarea>
        </div>
        <div>
          <label class="form-label">{{ __('Residual risks after mitigation') }}</label>
          <textarea name="residual_risks" class="form-control" rows="5">{{ old('residual_risks', $dpia->residual_risks) }}</textarea>
        </div>
      </div>
    </div>
  @endif

  @if ($step === 4)
    <div class="card mb-3">
      <div class="card-header">{{ __('Step 4 - DPO opinion and sign-off') }}</div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="form-label">{{ __('DPO opinion') }}</label>
            <textarea name="dpo_opinion" class="form-control" rows="4">{{ old('dpo_opinion', $dpia->dpo_opinion) }}</textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('DPO consulted on') }}</label>
            <input type="date" name="dpo_consulted_at" class="form-control"
              value="{{ old('dpo_consulted_at', optional($dpia->dpo_consulted_at)->format('Y-m-d')) }}">
            <label class="form-label mt-3">{{ __('Status') }}</label>
            <select name="status" class="form-select">
              @foreach (\AhgPrivacy\Models\Dpia::statuses() as $s)
                <option value="{{ $s }}" @selected(old('status', $dpia->status) === $s)>{{ $s }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="d-flex justify-content-between">
    <a href="{{ route('ahgprivacy.dpia.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i>
      @if ($step < 4) {{ __('Save and continue') }} @else {{ __('Save') }} @endif
    </button>
  </div>
</form>

@if (! $isCreate && $step === 4 && $dpia->status !== 'completed')
  <hr class="my-4">
  <div class="card border-success">
    <div class="card-header bg-success text-white">
      <i class="fas fa-signature me-2"></i>{{ __('Sign off') }}
    </div>
    <div class="card-body">
      <p class="small text-muted">
        {{ __('Signing off marks the DPIA as completed and writes a tamper-evident chain row in the audit trail. This action cannot be undone except by archiving the assessment.') }}
      </p>
      <form method="POST" action="{{ route('ahgprivacy.dpia.signoff', ['id' => $dpia->id]) }}">
        @csrf
        <div class="mb-3">
          <label class="form-label">{{ __('Sign-off note (optional)') }}</label>
          <textarea name="signoff_note" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-success">
          <i class="fas fa-check me-1"></i>{{ __('Sign off') }}
        </button>
      </form>
    </div>
  </div>
@endif

@if (! $isCreate && $dpia->status === 'completed')
  <div class="alert alert-success mt-4">
    <i class="fas fa-check-circle me-2"></i>
    {{ __('Signed off') }}
    @if ($dpia->signed_off_at)
      {{ __('at') }} {{ $dpia->signed_off_at->format('Y-m-d H:i') }} UTC.
    @endif
  </div>
@endif
@endsection
