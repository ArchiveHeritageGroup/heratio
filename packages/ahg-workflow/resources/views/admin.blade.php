@extends('theme::layouts.1col')

@section('title', 'Manage Workflows')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-cogs"></i> Manage Workflows</h1>
    <div>
      <a href="{{ route('workflow.gates.admin') }}" class="btn atom-btn-white"><i class="fas fa-shield-alt"></i> Publish Gates</a>
      <a href="{{ route('workflow.admin.create') }}" class="btn atom-btn-outline-success"><i class="fas fa-plus"></i> Create Workflow</a>
    </div>
  </div>

  @if(count($workflows) === 0)
    <div class="alert alert-info">No workflows configured yet. Create your first workflow to get started.</div>
  @else
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                <th>#</th>
                <th>Name</th>
                <th>Scope</th>
                <th>Trigger</th>
                <th>Applies To</th>
                <th>Steps</th>
                <th>Active Tasks</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($workflows as $wf)
                <tr>
                  <td>{{ $wf->id }}</td>
                  <td>
                    <a href="{{ route('workflow.admin.edit', $wf->id) }}">{{ $wf->name }}</a>
                    @if($wf->is_default)
                      <span class="badge bg-primary">Default</span>
                    @endif
                  </td>
                  <td><span class="badge bg-secondary">{{ ucfirst($wf->scope_type) }}</span></td>
                  <td>{{ str_replace('_', ' ', ucfirst($wf->trigger_event)) }}</td>
                  <td>{{ str_replace('_', ' ', ucfirst($wf->applies_to)) }}</td>
                  <td><span class="badge bg-info">{{ $wf->step_count }}</span></td>
                  <td><span class="badge bg-warning text-dark">{{ $wf->active_task_count }}</span></td>
                  <td>
                    @if($wf->is_active)
                      <span class="badge bg-success">Active</span>
                    @else
                      <span class="badge bg-secondary">Inactive</span>
                    @endif
                  </td>
                  <td>
                    <a href="{{ route('workflow.admin.edit', $wf->id) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-edit"></i></a>
                    <form action="{{ route('workflow.admin.delete', $wf->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this workflow and all its steps and tasks?')">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-danger"><i class="fas fa-trash"></i></button>
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
