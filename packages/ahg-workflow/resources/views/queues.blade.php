@extends('theme::layouts.1col')

@section('title', 'Workflow Queues')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-layer-group"></i> Workflow Queues</h1>
    <a href="{{ route('workflow.dashboard') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left"></i> {{ __('Dashboard') }}</a>
  </div>

  @if(count($queues) === 0)
    <div class="alert alert-info">No queues configured yet.</div>
  @else
    <div class="row">
      @foreach($queues as $queue)
        <div class="col-md-4 mb-4">
          <div class="card h-100" style="border-left: 4px solid {{ $queue->color ?? '#6c757d' }}">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="card-title mb-0">
                  <i class="fas {{ $queue->icon ?? 'fa-inbox' }}" style="color: {{ $queue->color ?? '#6c757d' }}"></i>
                  {{ $queue->name }}
                </h5>
                @if(!$queue->is_active)
                  <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                @endif
              </div>

              @if($queue->description)
                <p class="text-muted small mb-3">{{ $queue->description }}</p>
              @endif

              <div class="row text-center">
                <div class="col-4">
                  <h4 class="mb-0 text-primary">{{ $queue->task_count }}</h4>
                  <small class="text-muted">{{ __('Active') }}</small>
                </div>
                <div class="col-4">
                  <h4 class="mb-0 text-danger">{{ $queue->overdue_count }}</h4>
                  <small class="text-muted">{{ __('Overdue') }}</small>
                </div>
                <div class="col-4">
                  <h4 class="mb-0 text-info">{{ $queue->sla_days ?? '-' }}</h4>
                  <small class="text-muted">{{ __('SLA Days') }}</small>
                </div>
              </div>
            </div>
            <div class="card-footer bg-white">
              <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                  @if($queue->warning_days)
                    Warning: {{ $queue->warning_days }}d |
                  @endif
                  @if($queue->due_days)
                    Due: {{ $queue->due_days }}d |
                  @endif
                  @if($queue->sla_escalation_days)
                    Escalate: {{ $queue->sla_escalation_days }}d
                  @endif
                </small>
                @if($queue->overdue_count > 0)
                  <a href="{{ route('workflow.overdue', ['queue_id' => $queue->id]) }}" class="btn btn-sm atom-btn-outline-danger">
                    View Overdue
                  </a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
