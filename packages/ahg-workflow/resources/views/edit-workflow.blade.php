@extends('theme::layouts.1col')

@section('title', 'Edit Workflow: ' . $workflow->name)
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-edit"></i> Edit Workflow: {{ $workflow->name }}</h1>
    <a href="{{ route('workflow.admin') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left"></i> Back</a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  {{-- Workflow Settings --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Workflow Settings</h5></div>
    <div class="card-body">
      <form action="{{ route('workflow.admin.edit', $workflow->id) }}" method="POST">
        @csrf

        <div class="row">
          <div class="col-md-8">
            <div class="mb-3">
              <label for="name" class="form-label">Workflow Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $workflow->name) }}" required maxlength="255">
            </div>
            <div class="mb-3">
              <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $workflow->description) }}</textarea>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label for="scope_type" class="form-label">Scope <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <select class="form-select" id="scope_type" name="scope_type">
                <option value="global" {{ $workflow->scope_type === 'global' ? 'selected' : '' }}>Global</option>
                <option value="repository" {{ $workflow->scope_type === 'repository' ? 'selected' : '' }}>Repository</option>
                <option value="collection" {{ $workflow->scope_type === 'collection' ? 'selected' : '' }}>Collection</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="scope_id" class="form-label">Scope ID <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="number" class="form-control" id="scope_id" name="scope_id" value="{{ old('scope_id', $workflow->scope_id) }}">
            </div>
            <div class="mb-3">
              <label for="trigger_event" class="form-label">Trigger Event <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <select class="form-select" id="trigger_event" name="trigger_event">
                <option value="submit" {{ $workflow->trigger_event === 'submit' ? 'selected' : '' }}>Submit</option>
                <option value="publish" {{ $workflow->trigger_event === 'publish' ? 'selected' : '' }}>Publish</option>
                <option value="update" {{ $workflow->trigger_event === 'update' ? 'selected' : '' }}>Update</option>
                <option value="create" {{ $workflow->trigger_event === 'create' ? 'selected' : '' }}>Create</option>
                <option value="manual" {{ $workflow->trigger_event === 'manual' ? 'selected' : '' }}>Manual</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="applies_to" class="form-label">Applies To <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <select class="form-select" id="applies_to" name="applies_to">
                <option value="information_object" {{ $workflow->applies_to === 'information_object' ? 'selected' : '' }}>Information Object</option>
                <option value="actor" {{ $workflow->applies_to === 'actor' ? 'selected' : '' }}>Actor</option>
                <option value="repository" {{ $workflow->applies_to === 'repository' ? 'selected' : '' }}>Repository</option>
                <option value="accession" {{ $workflow->applies_to === 'accession' ? 'selected' : '' }}>Accession</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label for="auto_archive_days" class="form-label">Auto Archive (days) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="number" class="form-control" id="auto_archive_days" name="auto_archive_days" value="{{ old('auto_archive_days', $workflow->auto_archive_days) }}">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ $workflow->is_active ? 'checked' : '' }}>
              <label class="form-check-label" for="is_active">Active <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="is_default" name="is_default" {{ $workflow->is_default ? 'checked' : '' }}>
              <label class="form-check-label" for="is_default">Default Workflow <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="require_all_steps" name="require_all_steps" {{ $workflow->require_all_steps ? 'checked' : '' }}>
              <label class="form-check-label" for="require_all_steps">Require All Steps <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="allow_parallel" name="allow_parallel" {{ $workflow->allow_parallel ? 'checked' : '' }}>
              <label class="form-check-label" for="allow_parallel">Allow Parallel Steps <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="notification_enabled" name="notification_enabled" {{ $workflow->notification_enabled ? 'checked' : '' }}>
          <label class="form-check-label" for="notification_enabled">Enable Notifications <span class="badge bg-secondary ms-1">Optional</span></label>
        </div>

        <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save"></i> Save Changes</button>
      </form>
    </div>
  </div>

  {{-- Steps --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="fas fa-list-ol"></i> Workflow Steps</h5>
      <button type="button" class="btn btn-sm atom-btn-outline-success" data-bs-toggle="collapse" data-bs-target="#addStepForm">
        <i class="fas fa-plus"></i> Add Step
      </button>
    </div>
    <div class="card-body">
      {{-- Add Step Form (collapsed) --}}
      <div class="collapse mb-4" id="addStepForm">
        <div class="card card-body bg-light">
          <h6>Add New Step</h6>
          <form action="{{ route('workflow.admin.step.add', $workflow->id) }}" method="POST">
            @csrf
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="step_name" class="form-label">Step Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                <input type="text" class="form-control" id="step_name" name="name" required maxlength="255">
              </div>
              <div class="col-md-4 mb-3">
                <label for="step_type" class="form-label">Step Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                <select class="form-select" id="step_type" name="step_type">
                  <option value="review">Review</option>
                  <option value="approve">Approve</option>
                  <option value="quality_check">Quality Check</option>
                  <option value="final_review">Final Review</option>
                  <option value="publish">Publish</option>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label for="action_required" class="form-label">Action Required <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                <select class="form-select" id="action_required" name="action_required">
                  <option value="approve_reject">Approve / Reject</option>
                  <option value="acknowledge">Acknowledge</option>
                  <option value="review_only">Review Only</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="step_description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea class="form-control" id="step_description" name="description" rows="2"></textarea>
              </div>
              <div class="col-md-3 mb-3">
                <label for="escalation_days" class="form-label">Escalation Days <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" class="form-control" id="escalation_days" name="escalation_days">
              </div>
              <div class="col-md-3 mb-3">
                <label for="step_order" class="form-label">Step Order <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" class="form-control" id="step_order" name="step_order" placeholder="Auto">
              </div>
            </div>
            <div class="row">
              <div class="col-md-12 mb-3">
                <label for="step_instructions" class="form-label">Instructions <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea class="form-control" id="step_instructions" name="instructions" rows="2"></textarea>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="step_pool_enabled" name="pool_enabled" checked>
                  <label class="form-check-label" for="step_pool_enabled">Pool Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="step_is_optional" name="is_optional">
                  <label class="form-check-label" for="step_is_optional">Optional <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="step_is_active" name="is_active" checked>
                  <label class="form-check-label" for="step_is_active">Active <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
            </div>
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-plus"></i> Add Step</button>
          </form>
        </div>
      </div>

      {{-- Steps List --}}
      @if(empty($workflow->steps))
        <p class="text-muted">No steps defined yet. Add a step to build the workflow.</p>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr>
                <th>Order</th>
                <th>Name</th>
                <th>Type</th>
                <th>Action</th>
                <th>Pool</th>
                <th>Escalation</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($workflow->steps as $step)
                <tr>
                  <td><span class="badge bg-primary">{{ $step->step_order }}</span></td>
                  <td>
                    {{ $step->name }}
                    @if($step->is_optional)
                      <span class="badge bg-secondary">Optional</span>
                    @endif
                  </td>
                  <td>{{ ucfirst(str_replace('_', ' ', $step->step_type)) }}</td>
                  <td>{{ str_replace('_', ' ', ucfirst($step->action_required)) }}</td>
                  <td>
                    @if($step->pool_enabled)
                      <span class="badge bg-success">Yes</span>
                    @else
                      <span class="badge bg-secondary">No</span>
                    @endif
                  </td>
                  <td>{{ $step->escalation_days ? $step->escalation_days . ' days' : '-' }}</td>
                  <td>
                    @if($step->is_active)
                      <span class="badge bg-success">Active</span>
                    @else
                      <span class="badge bg-secondary">Inactive</span>
                    @endif
                  </td>
                  <td>
                    <form action="{{ route('workflow.admin.step.delete', $step->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this step?')">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-danger"><i class="fas fa-trash"></i></button>
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
@endsection
