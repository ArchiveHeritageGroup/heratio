@extends('theme::layouts.1col')
@section('title', 'Records Management - Record Disposal Class')
@section('body-class', 'admin records assign-class')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-link me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Record Disposal Class</h1><span class="small text-muted">{{ $ioTitle }} (ID: {{ $ioId }})</span></div>
  </div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if($assignment)
<div class="card mb-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Current Assignment</h5></div>
  <div class="card-body">
    <table class="table table-sm">
      <tr><th style="width:200px">Schedule</th><td>{{ $assignment->schedule_ref ?? '' }} — {{ $assignment->schedule_title ?? '' }}</td></tr>
      <tr><th>Disposal Class</th><td>{{ $assignment->class_ref ?? '' }} — {{ $assignment->class_title ?? '' }}</td></tr>
      <tr><th>Disposal Action</th><td>{{ ucfirst(str_replace('_', ' ', $assignment->disposal_action ?? '')) }}</td></tr>
      <tr><th>Retention Period</th><td>
        @if($assignment->retention_period_years || $assignment->retention_period_months)
          {{ $assignment->retention_period_years ? $assignment->retention_period_years . ' year(s)' : '' }}
          {{ $assignment->retention_period_months ? $assignment->retention_period_months . ' month(s)' : '' }}
        @else
          -
        @endif
      </td></tr>
      <tr><th>Retention Start Date</th><td>{{ $assignment->retention_start_date ?? '-' }}</td></tr>
      <tr><th>Calculated Disposal Date</th><td>{{ $assignment->calculated_disposal_date ?? '-' }}</td></tr>
      @if($assignment->override_disposal_date)
        <tr><th>Override Disposal Date</th><td>{{ $assignment->override_disposal_date }}</td></tr>
        <tr><th>Override Reason</th><td>{{ $assignment->override_reason ?? '-' }}</td></tr>
      @endif
      <tr><th>Assigned At</th><td>{{ $assignment->assigned_at ?? '-' }}</td></tr>
    </table>
  </div>
</div>
@endif

<div class="card mb-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ $assignment ? 'Reassign' : 'Assign' }} Disposal Class</h5></div>
  <div class="card-body">
    <form method="post" action="{{ route('records.assign-class') }}">@csrf
      <input type="hidden" name="information_object_id" value="{{ $ioId }}">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="disposal_class_id" class="form-label">Disposal Class <span class="badge bg-secondary ms-1">Required</span></label>
          <select name="disposal_class_id" id="disposal_class_id" class="form-select" required>
            <option value="">-- Select a disposal class --</option>
            @foreach($activeClasses as $cls)
              <option value="{{ $cls->id }}" {{ ($assignment && $assignment->disposal_class_id == $cls->id) ? 'selected' : '' }}>
                {{ $cls->schedule_title }} / {{ $cls->class_ref }} — {{ $cls->title }} ({{ ucfirst($cls->disposal_action) }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label for="retention_start_date" class="form-label">Retention Start Date <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="date" name="retention_start_date" id="retention_start_date" class="form-control" value="{{ $assignment->retention_start_date ?? '' }}">
        </div>
        <div class="col-md-3 mb-3 d-flex align-items-end">
          <button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-save me-1"></i>{{ $assignment ? 'Reassign' : 'Assign' }}</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="mt-3"><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back</a></div>
@endsection
