@extends('theme::layouts.1col')
@section('title', 'Records Management - Create Retention Schedule')
@section('body-class', 'admin records schedule-create')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-calendar-plus me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Create Retention Schedule') }}</h1><span class="small text-muted">Records management — new retention schedule</span></div>
  </div>
@endsection
@section('content')
<form method="post" action="{{ route('records.schedules.store') }}">@csrf
<div class="card mb-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Schedule Details') }}</h5></div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-4 mb-3">
        <label for="schedule_ref" class="form-label">Schedule Reference <span class="badge bg-secondary ms-1">Required</span></label>
        <input type="text" name="schedule_ref" id="schedule_ref" class="form-control" value="{{ old('schedule_ref') }}" required>
        @error('schedule_ref')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-8 mb-3">
        <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Required</span></label>
        <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required>
        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
      </div>
      <div class="col-12 mb-3">
        <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
      </div>
      <div class="col-md-6 mb-3">
        <label for="authority" class="form-label">Authority <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="authority" id="authority" class="form-control" value="{{ old('authority') }}">
      </div>
      <div class="col-md-6 mb-3">
        <label for="jurisdiction" class="form-label">Jurisdiction <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="jurisdiction" id="jurisdiction" class="form-control" value="{{ old('jurisdiction') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label for="effective_date" class="form-label">Effective Date <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="date" name="effective_date" id="effective_date" class="form-control" value="{{ old('effective_date') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label for="review_date" class="form-label">Review Date <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="date" name="review_date" id="review_date" class="form-control" value="{{ old('review_date') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label for="expiry_date" class="form-label">Expiry Date <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="date" name="expiry_date" id="expiry_date" class="form-control" value="{{ old('expiry_date') }}">
      </div>
    </div>
  </div>
</div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
  <a href="{{ route('records.schedules.index') }}" class="btn atom-btn-outline-light">Cancel</a>
  <button type="submit" class="btn atom-btn-outline-light">{{ __('Create Schedule') }}</button>
</section>
</form>
@endsection
