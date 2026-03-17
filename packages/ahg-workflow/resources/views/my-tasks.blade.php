@extends('theme::layouts.1col')

@section('title', 'My Workflow Tasks')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-user-check"></i> My Tasks</h1>
    <a href="{{ route('workflow.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  {{-- Status Filter --}}
  <div class="mb-3">
    <div class="btn-group" role="group">
      <a href="{{ route('workflow.my-tasks') }}" class="btn btn-outline-primary {{ !$currentStatus ? 'active' : '' }}">All Active</a>
      <a href="{{ route('workflow.my-tasks', ['status' => 'pending']) }}" class="btn btn-outline-warning {{ $currentStatus === 'pending' ? 'active' : '' }}">Pending</a>
      <a href="{{ route('workflow.my-tasks', ['status' => 'claimed']) }}" class="btn btn-outline-info {{ $currentStatus === 'claimed' ? 'active' : '' }}">Claimed</a>
      <a href="{{ route('workflow.my-tasks', ['status' => 'in_progress']) }}" class="btn btn-outline-primary {{ $currentStatus === 'in_progress' ? 'active' : '' }}">In Progress</a>
      <a href="{{ route('workflow.my-tasks', ['status' => 'completed']) }}" class="btn btn-outline-success {{ $currentStatus === 'completed' ? 'active' : '' }}">Completed</a>
    </div>
  </div>

  @if(count($tasks) === 0)
    <div class="alert alert-info">No tasks found{{ $currentStatus ? ' with status "' . $currentStatus . '"' : '' }}.</div>
  @else
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
                <th>Status</th>
                <th>Priority</th>
                <th>Due Date</th>
                <th>Decision</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($tasks as $task)
                <tr>
                  <td>{{ $task->id }}</td>
                  <td><a href="{{ route('workflow.task', $task->id) }}">{{ $task->step_name }}</a></td>
                  <td>{{ $task->workflow_name }}</td>
                  <td>
                    <span class="badge bg-secondary">{{ $task->object_type }}</span>
                    #{{ $task->object_id }}
                  </td>
                  <td>
                    @if($task->status === 'claimed')
                      <span class="badge bg-info">Claimed</span>
                    @elseif($task->status === 'in_progress')
                      <span class="badge bg-primary">In Progress</span>
                    @elseif($task->status === 'completed')
                      <span class="badge bg-success">Completed</span>
                    @else
                      <span class="badge bg-warning text-dark">Pending</span>
                    @endif
                  </td>
                  <td>
                    @if($task->priority === 'high')
                      <span class="badge bg-danger">High</span>
                    @elseif($task->priority === 'low')
                      <span class="badge bg-secondary">Low</span>
                    @else
                      <span class="badge bg-primary">Normal</span>
                    @endif
                  </td>
                  <td>
                    @if($task->due_date)
                      @if($task->due_date < now()->toDateString())
                        <span class="text-danger fw-bold"><i class="fas fa-exclamation-circle"></i> {{ $task->due_date }}</span>
                      @else
                        {{ $task->due_date }}
                      @endif
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    @if($task->decision === 'approved')
                      <span class="badge bg-success">Approved</span>
                    @elseif($task->decision === 'rejected')
                      <span class="badge bg-danger">Rejected</span>
                    @else
                      <span class="badge bg-secondary">Pending</span>
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
