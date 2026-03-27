@extends('theme::layouts.1col')

@section('title', 'Embargo - ' . ($resource->title ?? $resource->slug))
@section('body-class', 'rights embargo-edit')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $resource->title ?? $resource->slug }}</h1>
    <span class="small">{{ isset($embargo) ? 'Edit Embargo' : 'Add Embargo' }}</span>
  </div>
@endsection

@section('content')
<form method="post" action="{{ route('ext-rights.store-embargo', $resource->slug) }}">
  @csrf
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">Embargo Details</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Embargo Type <span class="text-danger">*</span></label>
          <select name="embargo_type" class="form-select" required>
            @foreach($formOptions['embargo_type_options'] as $value => $label)
            <option value="{{ $value }}" {{ old('embargo_type', $embargo->embargo_type ?? 'full') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Reason <span class="text-danger">*</span></label>
          <select name="reason" class="form-select" required>
            @foreach($formOptions['embargo_reason_options'] as $value => $label)
            <option value="{{ $value }}" {{ old('reason', $embargo->reason ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Start Date <span class="text-danger">*</span></label>
          <input type="date" name="start_date" class="form-control" required value="{{ old('start_date', $embargo->start_date ?? date('Y-m-d')) }}">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $embargo->end_date ?? '') }}">
          <small class="form-text text-muted">Leave empty for indefinite embargo</small>
        </div>
      </div>
      <div class="mb-3">
        <div class="form-check">
          <input type="checkbox" name="auto_release" class="form-check-input" id="auto_release" value="1"
                 {{ old('auto_release', $embargo->auto_release ?? 1) ? 'checked' : '' }}>
          <label class="form-check-label" for="auto_release">Automatically lift embargo when end date is reached</label>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Reason Note</label>
        <textarea name="reason_note" class="form-control" rows="3">{{ old('reason_note', $embargo->reason_note ?? '') }}</textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Internal Note</label>
        <textarea name="internal_note" class="form-control" rows="2" placeholder="Not visible to users">{{ old('internal_note', $embargo->internal_note ?? '') }}</textarea>
      </div>
    </div>
  </div>

  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ route('ext-rights.index', $resource->slug) }}" class="btn atom-btn-outline-light">Cancel</a>
    <button type="submit" class="btn atom-btn-outline-light"><i class="fas fa-save me-1"></i>Save Embargo</button>
  </section>
</form>

@if(isset($embargo))
<div class="card mt-4 border-danger">
  <div class="card-header bg-danger text-white">
    <h5 class="mb-0">Release Embargo</h5>
  </div>
  <div class="card-body">
    <p>Release this embargo immediately. The item will become accessible according to its other rights settings.</p>
    <form action="{{ route('ext-rights.release-embargo', [$resource->slug, $embargo->id]) }}" method="post"
          onsubmit="return confirm('Are you sure you want to release this embargo?');">
      @csrf
      <button type="submit" class="btn btn-danger"><i class="fas fa-unlock me-1"></i>Release Now</button>
    </form>
  </div>
</div>
@endif
@endsection
