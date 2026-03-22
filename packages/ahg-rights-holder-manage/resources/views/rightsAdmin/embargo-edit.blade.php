@extends('theme::layouts.2col')

@section('title', 'Edit Embargo')
@section('body-class', 'admin rights-admin embargo-edit')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">Edit Embargo</h1>
@endsection

@section('content')
<form method="post" action="{{ route('rights-admin.embargo-update', $embargo->id ?? 0) }}">
  @csrf
  @method('PUT')
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">Embargo Details</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="embargo_type" class="form-label">Embargo Type <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="embargo_type" id="embargo_type" class="form-select">
            @foreach(['full' => 'Full', 'metadata_only' => 'Metadata Only', 'digital_object' => 'Digital Object', 'custom' => 'Custom'] as $val => $label)
              <option value="{{ $val }}" {{ ($embargo->embargo_type ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label for="start_date" class="form-label">Start Date <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $embargo->start_date ?? '' }}">
        </div>
        <div class="col-md-3 mb-3">
          <label for="end_date" class="form-label">End Date <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $embargo->end_date ?? '' }}">
        </div>
      </div>
      <div class="mb-3">
        <label for="reason" class="form-label">Reason <span class="badge bg-secondary ms-1">Optional</span></label>
        <input type="text" name="reason" id="reason" class="form-control" value="{{ $embargo->reason ?? '' }}">
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_perpetual" value="1" id="is_perpetual" {{ ($embargo->is_perpetual ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_perpetual">Perpetual</label>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ ($embargo->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">Active</label>
      </div>
    </div>
  </div>
  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ route('rights-admin.embargoes') }}" class="btn atom-btn-outline-light">Cancel</a>
    <button type="submit" class="btn atom-btn-outline-light">Save</button>
  </section>
</form>
@endsection
