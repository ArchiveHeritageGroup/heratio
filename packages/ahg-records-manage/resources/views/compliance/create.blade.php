{{--
  Records Management — New compliance assessment (P2.8)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'New Compliance Assessment')
@section('body-class', 'admin records compliance create')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-plus me-2"></i> New Compliance Assessment</h1>
  <a href="{{ route('records.compliance.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('records.compliance.store') }}" class="row g-3">
  @csrf
  <div class="col-md-6">
    <label class="form-label">{{ __('Framework') }}</label>
    <select name="framework" class="form-select" required>
      <option value="">— pick a framework —</option>
      @foreach($frameworks as $f)<option value="{{ $f->code }}" @selected(old('framework')===$f->code)>{{ $f->label }}</option>@endforeach
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label">{{ __('Assessment ref') }}</label>
    <input type="text" name="assessment_ref" class="form-control" value="{{ old('assessment_ref', 'CA-' . date('Y') . '-' . sprintf('%03d', random_int(1, 999))) }}" required>
    <div class="form-text small">Unique identifier — e.g. CA-2026-001.</div>
  </div>
  <div class="col-12">
    <label class="form-label">{{ __('Title') }}</label>
    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
  </div>
  <div class="col-12">
    <label class="form-label">{{ __('Scope (optional)') }}</label>
    <textarea name="scope" rows="3" class="form-control" placeholder="{{ __('What is being assessed? Departments, record series, time period.') }}">{{ old('scope') }}</textarea>
  </div>
  <div class="col-md-6">
    <label class="form-label">{{ __('Period start') }}</label>
    <input type="date" name="period_start" class="form-control" value="{{ old('period_start') }}">
  </div>
  <div class="col-md-6">
    <label class="form-label">{{ __('Period end') }}</label>
    <input type="date" name="period_end" class="form-control" value="{{ old('period_end') }}">
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-primary"><i class="fas fa-play me-1"></i>Create + run checks</button>
    <a href="{{ route('records.compliance.index') }}" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>

<div class="alert alert-info mt-4 small">
  <i class="fas fa-info-circle me-1"></i>
  On submit, the chosen framework's automated checks run against the live RM data plane. You can re-run them any time afterwards from the assessment detail page. Results are stored as JSON findings + recommendations and a scored percentage. Final sign-off (witness name + timestamp) locks the assessment.
</div>
@endsection
