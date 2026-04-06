@extends('theme::layouts.1col')
@section('title', 'Records Management - ' . ($schedule->title ?? 'Schedule'))
@section('body-class', 'admin records schedule-show')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-calendar-check me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ $schedule->title }}</h1><span class="small text-muted">{{ $schedule->schedule_ref }}</span></div>
  </div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Schedule Details</h5>
    <div class="d-flex gap-2">
      @if($schedule->status === 'draft')
        <form method="post" action="{{ route('records.schedules.approve', $schedule->id) }}" class="d-inline">@csrf
          <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve and activate this schedule?')"><i class="fas fa-check me-1"></i>Approve</button>
        </form>
      @endif
      <a href="{{ route('records.schedules.edit', $schedule->id) }}" class="btn btn-sm btn-light"><i class="fas fa-edit me-1"></i>Edit</a>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <table class="table table-sm">
          <tr><th style="width:160px">Reference</th><td>{{ $schedule->schedule_ref }}</td></tr>
          <tr><th>Title</th><td>{{ $schedule->title }}</td></tr>
          <tr><th>Authority</th><td>{{ $schedule->authority ?? '-' }}</td></tr>
          <tr><th>Jurisdiction</th><td>{{ $schedule->jurisdiction ?? '-' }}</td></tr>
          <tr><th>Version</th><td>{{ $schedule->version }}</td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <table class="table table-sm">
          <tr><th style="width:160px">Status</th><td>
            @if($schedule->status === 'draft')<span class="badge bg-secondary">Draft</span>
            @elseif($schedule->status === 'active')<span class="badge bg-success">Active</span>
            @elseif($schedule->status === 'superseded')<span class="badge bg-warning text-dark">Superseded</span>
            @elseif($schedule->status === 'expired')<span class="badge bg-danger">Expired</span>
            @else<span class="badge bg-secondary">{{ ucfirst($schedule->status) }}</span>@endif
          </td></tr>
          <tr><th>Effective Date</th><td>{{ $schedule->effective_date ?? '-' }}</td></tr>
          <tr><th>Review Date</th><td>{{ $schedule->review_date ?? '-' }}</td></tr>
          <tr><th>Expiry Date</th><td>{{ $schedule->expiry_date ?? '-' }}</td></tr>
          <tr><th>Approved By</th><td>{{ $schedule->approved_by ?? '-' }}</td></tr>
          <tr><th>Approved At</th><td>{{ $schedule->approved_at ?? '-' }}</td></tr>
        </table>
      </div>
    </div>
    @if($schedule->description)
      <div class="mt-2"><strong>Description:</strong><p class="mt-1">{{ $schedule->description }}</p></div>
    @endif
  </div>
</div>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Disposal Classes ({{ count($classes) }})</h5>
    <a href="{{ route('records.classes.create', $schedule->id) }}" class="btn btn-sm btn-light"><i class="fas fa-plus me-1"></i>Add Class</a>
  </div>
  <div class="card-body p-0">
    @if(count($classes) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <th>Ref</th><th>Title</th><th>Retention</th><th>Trigger</th><th>Disposal Action</th><th>Records</th><th>Active</th><th>Actions</th>
      </tr></thead>
      <tbody>
        @foreach($classes as $class)
        <tr>
          <td>{{ $class->class_ref }}</td>
          <td>{{ $class->title }}</td>
          <td>
            @if($class->retention_period_years || $class->retention_period_months)
              {{ $class->retention_period_years ? $class->retention_period_years . 'y' : '' }}{{ $class->retention_period_months ? ' ' . $class->retention_period_months . 'm' : '' }}
            @else
              -
            @endif
          </td>
          <td>{{ ucfirst(str_replace('_', ' ', $class->retention_trigger)) }}</td>
          <td>{{ ucfirst(str_replace('_', ' ', $class->disposal_action)) }}</td>
          <td>{{ $class->record_count ?? 0 }}</td>
          <td>{!! $class->is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
          <td>
            <a href="{{ route('records.classes.edit', [$schedule->id, $class->id]) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
            @if(($class->record_count ?? 0) === 0)
              <form method="post" action="{{ route('records.classes.delete', [$schedule->id, $class->id]) }}" class="d-inline">@csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this disposal class?')"><i class="fas fa-trash"></i></button>
              </form>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
      <div class="text-center py-4 text-muted">No disposal classes defined for this schedule.</div>
    @endif
  </div>
</div>

<div class="mt-3"><a href="{{ route('records.schedules.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Schedules</a></div>
@endsection
