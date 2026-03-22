@extends('theme::layouts.1col')
@section('title', 'Edit Preservation Schedule')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-edit me-2"></i>Edit Schedule</h1>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $formAction ?? '#' }}">
      @csrf
      @if(isset($schedule)) @method('PUT') @endif

      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Schedule Details</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Task Type <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="task_type" class="form-select">
                <option value="fixity" {{ old('task_type', $schedule->task_type ?? '') == 'fixity' ? 'selected' : '' }}>Fixity Check</option>
                <option value="virus_scan" {{ old('task_type', $schedule->task_type ?? '') == 'virus_scan' ? 'selected' : '' }}>Virus Scan</option>
                <option value="backup" {{ old('task_type', $schedule->task_type ?? '') == 'backup' ? 'selected' : '' }}>Backup</option>
                <option value="identification" {{ old('task_type', $schedule->task_type ?? '') == 'identification' ? 'selected' : '' }}>Format Identification</option>
              </select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Frequency <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="frequency" class="form-select">
                <option value="daily" {{ old('frequency', $schedule->frequency ?? '') == 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ old('frequency', $schedule->frequency ?? '') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                <option value="monthly" {{ old('frequency', $schedule->frequency ?? '') == 'monthly' ? 'selected' : '' }}>Monthly</option>
              </select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Time <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="time" name="run_time" class="form-control" value="{{ old('run_time', $schedule->run_time ?? '02:00') }}">
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="is_enabled" class="form-select">
                <option value="1" {{ old('is_enabled', $schedule->is_enabled ?? 1) == 1 ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ old('is_enabled', $schedule->is_enabled ?? 1) == 0 ? 'selected' : '' }}>No</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>Save</button>
        <a href="{{ route('preservation.scheduler') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection