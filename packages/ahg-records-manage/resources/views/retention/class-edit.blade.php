@extends('theme::layouts.1col')
@section('title', 'Records Management - Edit Disposal Class')
@section('body-class', 'admin records class-edit')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-layer-group me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Edit Disposal Class') }}</h1><span class="small text-muted">Schedule: {{ $schedule->schedule_ref }} — {{ $schedule->title }}</span></div>
  </div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form method="post" action="{{ route('records.classes.update', [$schedule->id, $class->id]) }}">@csrf @method('PUT')
<div class="card mb-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Class Details') }}</h5></div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-4 mb-3">
        <label for="class_ref" class="form-label">Class Reference <span class="badge bg-secondary ms-1">Required</span></label>
        <input type="text" name="class_ref" id="class_ref" class="form-control" value="{{ old('class_ref', $class->class_ref) }}" required>
        @error('class_ref')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-8 mb-3">
        <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Required</span></label>
        <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $class->title) }}" required>
        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
      </div>
      <div class="col-12 mb-3">
        <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $class->description) }}</textarea>
      </div>
      <div class="col-md-3 mb-3">
        <label for="retention_period_years" class="form-label">Retention Years <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="number" name="retention_period_years" id="retention_period_years" class="form-control" min="0" value="{{ old('retention_period_years', $class->retention_period_years) }}">
      </div>
      <div class="col-md-3 mb-3">
        <label for="retention_period_months" class="form-label">Retention Months <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="number" name="retention_period_months" id="retention_period_months" class="form-control" min="0" max="11" value="{{ old('retention_period_months', $class->retention_period_months) }}">
      </div>
      <div class="col-md-3 mb-3">
        <label for="retention_trigger" class="form-label">Trigger <span class="badge bg-secondary ms-1">Required</span></label>
        <select name="retention_trigger" id="retention_trigger" class="form-select">
          <option value="creation_date" {{ old('retention_trigger', $class->retention_trigger) === 'creation_date' ? 'selected' : '' }}>Creation Date</option>
          <option value="last_modified" {{ old('retention_trigger', $class->retention_trigger) === 'last_modified' ? 'selected' : '' }}>Last Modified</option>
          <option value="closure_date" {{ old('retention_trigger', $class->retention_trigger) === 'closure_date' ? 'selected' : '' }}>Closure Date</option>
          <option value="event_date" {{ old('retention_trigger', $class->retention_trigger) === 'event_date' ? 'selected' : '' }}>Event Date</option>
          <option value="cutoff_date" {{ old('retention_trigger', $class->retention_trigger) === 'cutoff_date' ? 'selected' : '' }}>Cutoff Date</option>
        </select>
      </div>
      <div class="col-md-3 mb-3">
        <label for="disposal_action" class="form-label">Disposal Action <span class="badge bg-secondary ms-1">Required</span></label>
        <select name="disposal_action" id="disposal_action" class="form-select" required>
          <option value="">-- Select --</option>
          <option value="destroy" {{ old('disposal_action', $class->disposal_action) === 'destroy' ? 'selected' : '' }}>Destroy</option>
          <option value="transfer" {{ old('disposal_action', $class->disposal_action) === 'transfer' ? 'selected' : '' }}>Transfer</option>
          <option value="review" {{ old('disposal_action', $class->disposal_action) === 'review' ? 'selected' : '' }}>Review</option>
          <option value="retain" {{ old('disposal_action', $class->disposal_action) === 'retain' ? 'selected' : '' }}>Retain Permanently</option>
          <option value="archive" {{ old('disposal_action', $class->disposal_action) === 'archive' ? 'selected' : '' }}>Archive</option>
        </select>
        @error('disposal_action')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
      </div>
      <div class="col-12 mb-3">
        <label for="citation" class="form-label">Legal Citation <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="citation" id="citation" class="form-control" rows="2">{{ old('citation', $class->citation) }}</textarea>
      </div>
      <div class="col-md-3 mb-3">
        <label for="sort_order" class="form-label">Sort Order <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" value="{{ old('sort_order', $class->sort_order) }}">
      </div>
      <div class="col-md-3 mb-3 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="disposal_confirmation_required" value="1" id="disposal_confirmation_required" {{ old('disposal_confirmation_required', $class->disposal_confirmation_required) ? 'checked' : '' }}>
          <label class="form-check-label" for="disposal_confirmation_required">{{ __('Disposal confirmation required') }}</label>
        </div>
      </div>
      <div class="col-md-3 mb-3 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="review_required" value="1" id="review_required" {{ old('review_required', $class->review_required) ? 'checked' : '' }}>
          <label class="form-check-label" for="review_required">{{ __('Review required before disposal') }}</label>
        </div>
      </div>
      <div class="col-md-3 mb-3 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $class->is_active) ? 'checked' : '' }}>
          <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
        </div>
      </div>
    </div>
  </div>
</div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
  <a href="{{ route('records.schedules.show', $schedule->id) }}" class="btn atom-btn-outline-light">Cancel</a>
  <button type="submit" class="btn atom-btn-outline-light">{{ __('Save Changes') }}</button>
</section>
</form>
@endsection
