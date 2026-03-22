@extends('theme::layouts.1col')

@section('title', 'Task: ' . $task->step_name)
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-clipboard-check"></i> Task #{{ $task->id }}: {{ $task->step_name }}</h1>
    <div>
      <a href="{{ route('workflow.my-tasks') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left"></i> My Tasks</a>
      <a href="{{ route('workflow.dashboard') }}" class="btn atom-btn-white"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    </div>
  </div>

  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="row">
    {{-- Task Details --}}
    <div class="col-lg-8 mb-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Task Details</h5></div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <table class="table table-bordered table-sm table-borderless">
                <tr>
                  <th class="text-muted" style="width:40%">Workflow</th>
                  <td>{{ $task->workflow_name }}</td>
                </tr>
                <tr>
                  <th class="text-muted">Step</th>
                  <td>{{ $task->step_name }}</td>
                </tr>
                <tr>
                  <th class="text-muted">Step Type</th>
                  <td><span class="badge bg-secondary">{{ ucfirst($task->step_type) }}</span></td>
                </tr>
                <tr>
                  <th class="text-muted">Action Required</th>
                  <td>{{ str_replace('_', ' ', ucfirst($task->action_required)) }}</td>
                </tr>
                <tr>
                  <th class="text-muted">Object</th>
                  <td>
                    <span class="badge bg-secondary">{{ $task->object_type }}</span>
                    @if($task->object_type === 'information_object')
                      <a href="{{ url('/' . $task->object_id) }}">#{{ $task->object_id }}</a>
                    @else
                      #{{ $task->object_id }}
                    @endif
                  </td>
                </tr>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-bordered table-sm table-borderless">
                <tr>
                  <th class="text-muted" style="width:40%">Status</th>
                  <td>
                    @if($task->status === 'completed')
                      <span class="badge bg-success">Completed</span>
                    @elseif($task->status === 'claimed')
                      <span class="badge bg-info">Claimed</span>
                    @elseif($task->status === 'in_progress')
                      <span class="badge bg-primary">In Progress</span>
                    @else
                      <span class="badge bg-warning text-dark">Pending</span>
                    @endif
                  </td>
                </tr>
                <tr>
                  <th class="text-muted">Priority</th>
                  <td>
                    @if($task->priority === 'high')
                      <span class="badge bg-danger">High</span>
                    @elseif($task->priority === 'low')
                      <span class="badge bg-secondary">Low</span>
                    @else
                      <span class="badge bg-primary">Normal</span>
                    @endif
                  </td>
                </tr>
                <tr>
                  <th class="text-muted">Assigned To</th>
                  <td>{{ $task->assigned_name ?? $task->assigned_username ?? 'Unassigned' }}</td>
                </tr>
                <tr>
                  <th class="text-muted">Submitted By</th>
                  <td>{{ $task->submitted_name ?? $task->submitted_username ?? '-' }}</td>
                </tr>
                <tr>
                  <th class="text-muted">Due Date</th>
                  <td>
                    @if($task->due_date)
                      @if($task->due_date < now()->toDateString() && $task->status !== 'completed')
                        <span class="text-danger fw-bold"><i class="fas fa-exclamation-circle"></i> {{ $task->due_date }} (Overdue)</span>
                      @else
                        {{ $task->due_date }}
                      @endif
                    @else
                      <span class="text-muted">No due date</span>
                    @endif
                  </td>
                </tr>
                <tr>
                  <th class="text-muted">Decision</th>
                  <td>
                    @if($task->decision === 'approved')
                      <span class="badge bg-success">Approved</span>
                    @elseif($task->decision === 'rejected')
                      <span class="badge bg-danger">Rejected</span>
                    @else
                      <span class="badge bg-secondary">Pending</span>
                    @endif
                  </td>
                </tr>
              </table>
            </div>
          </div>

          @if($task->instructions)
            <div class="mb-3">
              <h6>Instructions</h6>
              <div class="bg-light p-3 rounded">{!! nl2br(e($task->instructions)) !!}</div>
            </div>
          @endif

          @if($task->decision_comment)
            <div class="mb-3">
              <h6>Decision Comment</h6>
              <div class="bg-light p-3 rounded">{!! nl2br(e($task->decision_comment)) !!}</div>
            </div>
          @endif

          @if($task->step_checklist)
            <div class="mb-3">
              <h6>Checklist</h6>
              <div class="bg-light p-3 rounded">{!! nl2br(e($task->step_checklist)) !!}</div>
            </div>
          @endif
        </div>
      </div>

      {{-- Action Buttons --}}
      @if($task->status !== 'completed')
        <div class="card mt-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Actions</h5></div>
          <div class="card-body">
            @if($task->assigned_to === null && $task->pool_enabled)
              {{-- Claim --}}
              <form action="{{ route('workflow.task.claim', $task->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-hand-paper"></i> Claim This Task</button>
              </form>
            @elseif((int) $task->assigned_to === (int) auth()->id())
              {{-- Approve / Reject / Release --}}
              <div class="mb-3">
                <label for="comment" class="form-label">Comment</label>
                <textarea id="comment" class="form-control" rows="3" form="approve-form" name="comment" placeholder="Optional comment for approval, required for rejection..."></textarea>
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <form id="approve-form" action="{{ route('workflow.task.approve', $task->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-check"></i> Approve</button>
                </form>

                <form action="{{ route('workflow.task.reject', $task->id) }}" method="POST" class="d-inline" onsubmit="this.querySelector('[name=comment]').value = document.getElementById('comment').value; if(!this.querySelector('[name=comment]').value) { alert('Comment is required for rejection.'); return false; }">
                  @csrf
                  <input type="hidden" name="comment" value="">
                  <button type="submit" class="btn atom-btn-outline-danger"><i class="fas fa-times"></i> Reject</button>
                </form>

                <form action="{{ route('workflow.task.release', $task->id) }}" method="POST" class="d-inline">
                  @csrf
                  <input type="hidden" name="comment" value="">
                  <button type="submit" class="btn atom-btn-white" onclick="this.form.querySelector('[name=comment]').value = document.getElementById('comment').value;"><i class="fas fa-undo"></i> Release to Pool</button>
                </form>
              </div>

              @if($errors->any())
                <div class="alert alert-danger mt-3">
                  @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                  @endforeach
                </div>
              @endif
            @endif
          </div>
        </div>
      @endif

      {{-- History Timeline --}}
      <div class="card mt-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-history"></i> Task History</h5></div>
        <div class="card-body">
          @if(empty($task->history))
            <p class="text-muted mb-0">No history recorded.</p>
          @else
            <div class="timeline">
              @foreach($task->history as $entry)
                <div class="d-flex mb-3 pb-3 border-bottom">
                  <div class="me-3">
                    @switch($entry->action)
                      @case('approved')
                        <span class="badge bg-success rounded-circle p-2"><i class="fas fa-check"></i></span>
                        @break
                      @case('rejected')
                        <span class="badge bg-danger rounded-circle p-2"><i class="fas fa-times"></i></span>
                        @break
                      @case('claimed')
                        <span class="badge bg-info rounded-circle p-2"><i class="fas fa-hand-paper"></i></span>
                        @break
                      @case('released')
                        <span class="badge bg-warning rounded-circle p-2"><i class="fas fa-undo"></i></span>
                        @break
                      @default
                        <span class="badge bg-secondary rounded-circle p-2"><i class="fas fa-circle"></i></span>
                    @endswitch
                  </div>
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                      <strong>{{ ucfirst($entry->action) }}</strong>
                      <small class="text-muted">{{ $entry->performed_at }}</small>
                    </div>
                    <div class="text-muted">
                      By {{ $entry->performer_name ?? $entry->username ?? 'System' }}
                      @if($entry->from_status || $entry->to_status)
                        &mdash; {{ $entry->from_status ?? '?' }} &rarr; {{ $entry->to_status ?? '?' }}
                      @endif
                    </div>
                    @if($entry->comment)
                      <div class="mt-1 bg-light p-2 rounded small">{!! nl2br(e($entry->comment)) !!}</div>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">Timestamps</h6></div>
        <div class="card-body">
          <table class="table table-bordered table-sm table-borderless mb-0">
            <tr>
              <th class="text-muted">Created</th>
              <td>{{ $task->created_at }}</td>
            </tr>
            @if($task->claimed_at)
              <tr>
                <th class="text-muted">Claimed</th>
                <td>{{ $task->claimed_at }}</td>
              </tr>
            @endif
            @if($task->decision_at)
              <tr>
                <th class="text-muted">Decided</th>
                <td>{{ $task->decision_at }}</td>
              </tr>
            @endif
            @if($task->escalated_at)
              <tr>
                <th class="text-muted">Escalated</th>
                <td>{{ $task->escalated_at }}</td>
              </tr>
            @endif
            <tr>
              <th class="text-muted">Updated</th>
              <td>{{ $task->updated_at }}</td>
            </tr>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">Metadata</h6></div>
        <div class="card-body">
          <table class="table table-bordered table-sm table-borderless mb-0">
            <tr>
              <th class="text-muted">Task ID</th>
              <td>{{ $task->id }}</td>
            </tr>
            <tr>
              <th class="text-muted">Retry Count</th>
              <td>{{ $task->retry_count }}</td>
            </tr>
            @if($task->previous_task_id)
              <tr>
                <th class="text-muted">Previous Task</th>
                <td><a href="{{ route('workflow.task', $task->previous_task_id) }}">#{{ $task->previous_task_id }}</a></td>
              </tr>
            @endif
            @if($task->queue_id)
              <tr>
                <th class="text-muted">Queue ID</th>
                <td>{{ $task->queue_id }}</td>
              </tr>
            @endif
          </table>
        </div>
      </div>

      @if($task->object_type === 'information_object')
        <div class="card">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">Publish Readiness</h6></div>
          <div class="card-body text-center">
            <a href="{{ route('workflow.publish-readiness', $task->object_id) }}" class="btn atom-btn-white">
              <i class="fas fa-clipboard-check"></i> Check Publish Gates
            </a>
          </div>
        </div>
      @endif
    </div>
  </div>
@endsection
