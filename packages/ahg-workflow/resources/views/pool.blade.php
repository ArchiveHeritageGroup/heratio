@extends('theme::layouts.1col')

@section('title', 'Workflow Task Pool')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-inbox"></i> Task Pool</h1>
    <a href="{{ route('workflow.dashboard') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left"></i> Dashboard</a>
  </div>

  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <p class="text-muted">Tasks available for claiming. Claim a task to assign it to yourself.</p>

  @if(count($tasks) === 0)
    <div class="alert alert-info">No tasks available in the pool.</div>
  @else
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                <th>#</th>
                <th>Step</th>
                <th>Workflow</th>
                <th>Object</th>
                <th>Priority</th>
                <th>Due Date</th>
                <th>Created</th>
                <th>Action</th>
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
                  <td><small>{{ $task->created_at }}</small></td>
                  <td>
                    <form action="{{ route('workflow.task.claim', $task->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-success"><i class="fas fa-hand-paper"></i> Claim</button>
                    </form>
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
