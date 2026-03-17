@extends('theme::layouts.1col')

@section('title', 'Workflow History')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-history"></i> Workflow Activity Log</h1>
    <a href="{{ route('workflow.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>

  @if(count($history) === 0)
    <div class="alert alert-info">No workflow activity recorded yet.</div>
  @else
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Time</th>
                <th>Task</th>
                <th>Action</th>
                <th>Workflow</th>
                <th>Object</th>
                <th>From</th>
                <th>To</th>
                <th>Performed By</th>
                <th>Comment</th>
              </tr>
            </thead>
            <tbody>
              @foreach($history as $entry)
                <tr>
                  <td><small>{{ $entry->performed_at }}</small></td>
                  <td>
                    @if($entry->task_id)
                      <a href="{{ route('workflow.task', $entry->task_id) }}">#{{ $entry->task_id }}</a>
                    @else
                      -
                    @endif
                  </td>
                  <td>
                    @switch($entry->action)
                      @case('approved')
                        <span class="badge bg-success">Approved</span>
                        @break
                      @case('rejected')
                        <span class="badge bg-danger">Rejected</span>
                        @break
                      @case('claimed')
                        <span class="badge bg-info">Claimed</span>
                        @break
                      @case('released')
                        <span class="badge bg-warning text-dark">Released</span>
                        @break
                      @case('started')
                        <span class="badge bg-primary">Started</span>
                        @break
                      @case('created')
                        <span class="badge bg-secondary">Created</span>
                        @break
                      @default
                        <span class="badge bg-secondary">{{ ucfirst($entry->action) }}</span>
                    @endswitch
                  </td>
                  <td>{{ $entry->workflow_name ?? '-' }}</td>
                  <td>
                    <span class="badge bg-secondary">{{ $entry->object_type }}</span>
                    #{{ $entry->object_id }}
                  </td>
                  <td><small>{{ $entry->from_status ?? '-' }}</small></td>
                  <td><small>{{ $entry->to_status ?? '-' }}</small></td>
                  <td>{{ $entry->performer_name ?? $entry->username ?? 'System' }}</td>
                  <td><small class="text-muted">{{ Str::limit($entry->comment, 80) }}</small></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
@endsection
