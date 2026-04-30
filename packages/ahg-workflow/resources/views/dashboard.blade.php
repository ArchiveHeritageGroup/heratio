@extends('theme::layouts.1col')

@section('title', 'Workflow Dashboard')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="fas fa-tasks me-2" aria-hidden="true"></i>{{ __('Workflow Dashboard') }}</h1>
    <a href="{{ route('workflow.admin') }}" class="btn btn-outline-primary">
      <i class="fas fa-cog me-1" aria-hidden="true"></i>{{ __('Manage Workflows') }}
    </a>
  </div>

{{-- Stats Cards --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle mb-1 text-white-50">{{ __('My Tasks') }}</h6>
              <h2 class="card-title mb-0">{{ $stats['my_tasks'] ?? 0 }}</h2>
            </div>
            <i class="fas fa-clipboard-list fa-2x opacity-50" aria-hidden="true"></i>
          </div>
        </div>
        <div class="card-footer bg-transparent border-0">
          <a href="{{ route('workflow.my-tasks') }}" class="text-white text-decoration-none small">
            View all <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-dark h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle mb-1 text-dark-50">{{ __('Pool Tasks') }}</h6>
              <h2 class="card-title mb-0">{{ $stats['pool_tasks'] ?? 0 }}</h2>
            </div>
            <i class="fas fa-inbox fa-2x opacity-50" aria-hidden="true"></i>
          </div>
        </div>
        <div class="card-footer bg-transparent border-0">
          <a href="{{ route('workflow.pool') }}" class="text-dark text-decoration-none small">
            Browse pool <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle mb-1 text-white-50">{{ __('Completed Today') }}</h6>
              <h2 class="card-title mb-0">{{ $stats['completed_today'] ?? 0 }}</h2>
            </div>
            <i class="fas fa-check-circle fa-2x opacity-50" aria-hidden="true"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-danger text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle mb-1 text-white-50">{{ __('Overdue') }}</h6>
              <h2 class="card-title mb-0">{{ $stats['overdue_tasks'] ?? 0 }}</h2>
            </div>
            <i class="fas fa-exclamation-triangle fa-2x opacity-50" aria-hidden="true"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- My Tasks --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>{{ __('My Tasks') }}</h5>
          <a href="{{ route('workflow.my-tasks') }}" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <div class="card-body p-0">
          @if(count($myTasks) === 0)
            <div class="text-center text-muted py-4">
              <i class="fas fa-inbox fa-3x mb-2 opacity-50" aria-hidden="true"></i>
              <p class="mb-0">No tasks assigned to you</p>
            </div>
          @else
            <div class="list-group list-group-flush">
              @foreach(array_slice($myTasks, 0, 5) as $task)
                <a href="{{ route('workflow.task', $task->id) }}" class="list-group-item list-group-item-action">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="mb-1">{{ $task->object_title ?? "Object #{$task->object_id}" }}</h6>
                      <small class="text-muted">
                        {{ $task->workflow_name }} &rarr; {{ $task->step_name }}
                      </small>
                    </div>
                    <span class="badge bg-{{ $task->priority === 'urgent' ? 'danger' : ($task->priority === 'high' ? 'warning' : 'secondary') }}">
                      {{ ucfirst($task->priority ?? 'normal') }}
                    </span>
                  </div>
                </a>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Task Pool --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-layer-group me-2" aria-hidden="true"></i>{{ __('Available Tasks') }}</h5>
          <a href="{{ route('workflow.pool') }}" class="btn btn-sm btn-outline-secondary">Browse Pool</a>
        </div>
        <div class="card-body p-0">
          @if(count($poolTasks) === 0)
            <div class="text-center text-muted py-4">
              <i class="fas fa-check-double fa-3x mb-2 opacity-50" aria-hidden="true"></i>
              <p class="mb-0">No tasks available to claim</p>
            </div>
          @else
            <div class="list-group list-group-flush">
              @foreach(array_slice($poolTasks, 0, 5) as $task)
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h6 class="mb-1">{{ $task->object_title ?? "Object #{$task->object_id}" }}</h6>
                      <small class="text-muted">
                        {{ $task->step_name }}
                      </small>
                    </div>
                    <form action="{{ route('workflow.task.claim', $task->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-success">{{ __('Claim') }}</button>
                    </form>
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Recent Activity --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fas fa-history me-2" aria-hidden="true"></i>{{ __('Recent Activity') }}</h5>
      <a href="{{ route('workflow.history') }}" class="btn btn-sm btn-outline-secondary">View All</a>
    </div>
    <div class="card-body p-0">
      @if(count($recentHistory) === 0)
        <div class="text-center text-muted py-4">
          <p class="mb-0">No recent activity</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col">{{ __('Time') }}</th>
                <th scope="col">{{ __('Action') }}</th>
                <th scope="col">{{ __('Object') }}</th>
                <th scope="col">{{ __('User') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentHistory as $entry)
                <tr>
                  <td class="text-nowrap">
                    <small>{{ \Carbon\Carbon::parse($entry->performed_at)->format('M j, H:i') }}</small>
                  </td>
                  <td>
                    @php
                      $actionColor = match($entry->action) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'claimed' => 'primary',
                        'started' => 'info',
                        'returned' => 'warning',
                        default => 'secondary',
                      };
                    @endphp
                    <span class="badge bg-{{ $actionColor }}">{{ ucfirst($entry->action) }}</span>
                  </td>
                  <td>{{ $entry->object_title ?? "#{$entry->object_id}" }}</td>
                  <td><small>{{ $entry->username ?? 'Unknown' }}</small></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
