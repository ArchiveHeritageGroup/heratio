@extends('theme::layouts.1col')

@section('title', 'Workflow Dashboard')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-project-diagram"></i> Workflow Dashboard</h1>
    <div>
      <a href="{{ route('workflow.admin') }}" class="btn atom-btn-white"><i class="fas fa-cogs"></i> Manage Workflows</a>
    </div>
  </div>

  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  {{-- Stat Cards --}}
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card border-primary h-100">
        <div class="card-body text-center">
          <div class="mb-2"><i class="fas fa-tasks fa-2x text-primary"></i></div>
          <h3 class="mb-1">{{ $stats['my_tasks'] }}</h3>
          <p class="text-muted mb-0">My Tasks</p>
        </div>
        <div class="card-footer bg-white text-center">
          <a href="{{ route('workflow.my-tasks') }}" class="text-decoration-none">View tasks <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-info h-100">
        <div class="card-body text-center">
          <div class="mb-2"><i class="fas fa-inbox fa-2x text-info"></i></div>
          <h3 class="mb-1">{{ $stats['pool_tasks'] }}</h3>
          <p class="text-muted mb-0">Pool Tasks</p>
        </div>
        <div class="card-footer bg-white text-center">
          <a href="{{ route('workflow.pool') }}" class="text-decoration-none">View pool <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-success h-100">
        <div class="card-body text-center">
          <div class="mb-2"><i class="fas fa-check-circle fa-2x text-success"></i></div>
          <h3 class="mb-1">{{ $stats['completed_today'] }}</h3>
          <p class="text-muted mb-0">Completed Today</p>
        </div>
        <div class="card-footer bg-white text-center">
          <a href="{{ route('workflow.history') }}" class="text-decoration-none">View history <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-danger h-100">
        <div class="card-body text-center">
          <div class="mb-2"><i class="fas fa-exclamation-triangle fa-2x text-danger"></i></div>
          <h3 class="mb-1">{{ $stats['overdue_tasks'] }}</h3>
          <p class="text-muted mb-0">Overdue Tasks</p>
        </div>
        <div class="card-footer bg-white text-center">
          <a href="{{ route('workflow.overdue') }}" class="text-decoration-none">View overdue <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- My Tasks --}}
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-user-check"></i> My Tasks</h5>
          <a href="{{ route('workflow.my-tasks') }}" class="btn btn-sm atom-btn-white">View All</a>
        </div>
        <div class="card-body p-0">
          @if(count($myTasks) === 0)
            <p class="text-muted text-center py-4 mb-0">No tasks assigned to you.</p>
          @else
            <div class="table-responsive">
              <table class="table table-bordered table-hover mb-0">
                <thead>
                  <tr>
                    <th>Task</th>
                    <th>Workflow</th>
                    <th>Status</th>
                    <th>Due</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach(array_slice($myTasks, 0, 10) as $task)
                    <tr>
                      <td><a href="{{ route('workflow.task', $task->id) }}">{{ $task->step_name }}</a></td>
                      <td><span class="text-muted">{{ $task->workflow_name }}</span></td>
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
                        @if($task->due_date)
                          @if($task->due_date < now()->toDateString())
                            <span class="text-danger fw-bold">{{ $task->due_date }}</span>
                          @else
                            {{ $task->due_date }}
                          @endif
                        @else
                          <span class="text-muted">-</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Pool Tasks --}}
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-inbox"></i> Pool Tasks</h5>
          <a href="{{ route('workflow.pool') }}" class="btn btn-sm atom-btn-white">View All</a>
        </div>
        <div class="card-body p-0">
          @if(count($poolTasks) === 0)
            <p class="text-muted text-center py-4 mb-0">No tasks available in pool.</p>
          @else
            <div class="table-responsive">
              <table class="table table-bordered table-hover mb-0">
                <thead>
                  <tr>
                    <th>Task</th>
                    <th>Workflow</th>
                    <th>Priority</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach(array_slice($poolTasks, 0, 10) as $task)
                    <tr>
                      <td><a href="{{ route('workflow.task', $task->id) }}">{{ $task->step_name }}</a></td>
                      <td><span class="text-muted">{{ $task->workflow_name }}</span></td>
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
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Recent Activity --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h5>
      <a href="{{ route('workflow.history') }}" class="btn btn-sm atom-btn-white">Full History</a>
    </div>
    <div class="card-body p-0">
      @if(count($recentHistory) === 0)
        <p class="text-muted text-center py-4 mb-0">No recent activity.</p>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr>
                <th>Time</th>
                <th>Action</th>
                <th>Workflow</th>
                <th>Performed By</th>
                <th>Comment</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentHistory as $entry)
                <tr>
                  <td><small>{{ $entry->performed_at }}</small></td>
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
                  <td>{{ $entry->performer_name ?? $entry->username ?? 'System' }}</td>
                  <td><small class="text-muted">{{ Str::limit($entry->comment, 60) }}</small></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
