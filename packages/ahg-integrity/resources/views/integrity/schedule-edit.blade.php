@extends('theme::layouts.1col')
@section('title', 'Integrity - Schedule Edit')
@section('body-class', 'admin integrity schedule-edit')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Schedule Edit</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')
<form method="post" action="{{ route('integrity.schedules.update', $schedule->id ?? 0) }}">@csrf @method('PUT')
<div class="card mb-4"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Edit Schedule</h5></div>
<div class="card-body"><div class="row">
  <div class="col-md-6 mb-3"><label for="name" class="form-label">Name <span class="badge bg-secondary ms-1">Required</span></label><input type="text" name="name" id="name" class="form-control" value="{{ $schedule->name ?? '' }}"></div>
  <div class="col-md-6 mb-3"><label for="cron_expression" class="form-label">Cron Expression <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="cron_expression" id="cron_expression" class="form-control" value="{{ $schedule->cron_expression ?? '' }}" placeholder="0 2 * * *"></div>
  <div class="col-md-6 mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ ($schedule->is_active ?? true) ? 'checked' : '' }}><label class="form-check-label" for="is_active">Active</label></div></div>
</div></div></div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;"><a href="{{ route('integrity.schedules') }}" class="btn atom-btn-outline-light">Cancel</a><button type="submit" class="btn atom-btn-outline-light">Save</button></section>
</form>
@endsection
