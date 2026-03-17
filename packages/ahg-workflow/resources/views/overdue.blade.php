@extends('theme::layouts.1col')

@section('title', 'Overdue Tasks')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-exclamation-triangle text-danger"></i> Overdue Tasks</h1>
    <a href="{{ route('workflow.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>

  @if(count($tasks) === 0)
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> No overdue tasks found.</div>
  @else
    <div class="alert alert-warning">
      <strong>{{ count($tasks) }}</strong> task(s) are past their due date.
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Step</th>
                <th>Workflow</th>
                <th>Object</th>
                <th>Assigned To</th>
                <th>Due Date</th>
                <th>Days Overdue</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($tasks as $task)
                @php
                  $daysOverdue = (int) now()->diffInDays(\Carbon\Carbon::parse($task->due_date));
                @endphp
                <tr>
                  <td>{{ $task->id }}</td>
                  <td><a href="{{ route('workflow.task', $task->id) }}">{{ $task->step_name }}</a></td>
                  <td>{{ $task->workflow_name }}</td>
                  <td>
                    <span class="badge bg-secondary">{{ $task->object_type }}</span>
                    #{{ $task->object_id }}
                  </td>
                  <td>{{ $task->assigned_name ?? $task->assigned_username ?? 'Unassigned' }}</td>
                  <td class="text-danger fw-bold">{{ $task->due_date }}</td>
                  <td>
                    @if($daysOverdue > 7)
                      <span class="badge bg-danger">{{ $daysOverdue }} days</span>
                    @elseif($daysOverdue > 3)
                      <span class="badge bg-warning text-dark">{{ $daysOverdue }} days</span>
                    @else
                      <span class="badge bg-info">{{ $daysOverdue }} days</span>
                    @endif
                  </td>
                  <td>
                    @if($task->status === 'claimed')
                      <span class="badge bg-info">Claimed</span>
                    @elseif($task->status === 'in_progress')
                      <span class="badge bg-primary">In Progress</span>
                    @else
                      <span class="badge bg-warning text-dark">Pending</span>
                    @endif
                  </td>
                  <td>
                    <a href="{{ route('workflow.task', $task->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
@endsection
