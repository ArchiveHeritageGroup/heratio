@extends('theme::layouts.1col')

@section('title', 'Manage Workflows')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="fas fa-cog me-2"></i>{{ __('Workflow Administration') }}</h1>
    <div>
      <a href="{{ route('workflow.admin.create') }}" class="btn atom-btn-outline-success">
        <i class="fas fa-plus me-1"></i>{{ __('Create Workflow') }}
      </a>
      <a href="{{ route('workflow.dashboard') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Dashboard') }}
      </a>
    </div>
  </div>

  {{-- Workflow Settings --}}
  <div class="card mb-4">
    <div class="card-header" style="background: var(--ahg-primary); color: white;">
      <i class="fas fa-shield-alt me-1"></i>{{ __('Workflow Settings') }}
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('workflow.admin.settings') }}">
        @csrf
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="workflow-required-publish"
                 name="workflow_required_for_publish" value="1"
                 {{ ($workflowRequiredForPublish ?? false) ? 'checked' : '' }}>
          <label class="form-check-label" for="workflow-required-publish">
            <strong>{{ __('Require workflow approval before publishing') }}</strong>
          </label>
        </div>
        <p class="text-muted small mt-1 mb-3">
          When enabled, items cannot be published without a completed workflow approval. Users will be prompted to start a workflow instead.
        </p>
        <button type="submit" class="btn btn-sm atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save Settings') }}</button>
      </form>
    </div>
  </div>

  {{-- Spectrum#A — filter UI (no-op if no procedures defined) --}}
  @if(!empty($spectrumProcedures ?? []))
    <form method="GET" action="{{ route('workflow.admin') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3">
      <div class="flex-grow-1" style="max-width: 28rem;">
        <label for="spectrum" class="form-label small mb-1">{{ __('Filter by Spectrum 5.1 procedure') }}</label>
        <select name="spectrum" id="spectrum" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">{{ __('All workflows') }}</option>
          @foreach($spectrumProcedures as $code => $label)
            <option value="{{ $code }}" {{ ($spectrumFilter ?? '') === $code ? 'selected' : '' }}>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
      @if(!empty($spectrumFilter))
        <a href="{{ route('workflow.admin') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>{{ __('Clear filter') }}</a>
      @endif
    </form>
  @endif

  @if(count($workflows) === 0)
    <div class="alert alert-info">
      @if(!empty($spectrumFilter))
        {{ __('No workflows are tagged with that Spectrum procedure yet.') }}
      @else
        No workflows configured yet. Create your first workflow to get started.
      @endif
    </div>
  @else
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Scope') }}</th>
                <th>{{ __('Trigger') }}</th>
                <th>{{ __('Applies To') }}</th>
                <th>{{ __('Spectrum') }}</th>
                <th>{{ __('Steps') }}</th>
                <th>{{ __('Active Tasks') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($workflows as $wf)
                <tr>
                  <td>{{ $wf->id }}</td>
                  <td>
                    <a href="{{ route('workflow.admin.edit', $wf->id) }}">{{ $wf->name }}</a>
                    @if($wf->is_default)
                      <span class="badge bg-primary">{{ __('Default') }}</span>
                    @endif
                  </td>
                  <td><span class="badge bg-secondary">{{ ucfirst($wf->scope_type) }}</span></td>
                  <td>{{ str_replace('_', ' ', ucfirst($wf->trigger_event)) }}</td>
                  <td>{{ str_replace('_', ' ', ucfirst($wf->applies_to)) }}</td>
                  <td>
                    @if(!empty($wf->spectrum_procedure) && isset($spectrumProcedures[$wf->spectrum_procedure]))
                      <span class="badge bg-info text-dark"><i class="fas fa-university me-1"></i>{{ $spectrumProcedures[$wf->spectrum_procedure] }}</span>
                    @else
                      <span class="text-muted small">—</span>
                    @endif
                  </td>
                  <td><span class="badge bg-info">{{ $wf->step_count }}</span></td>
                  <td><span class="badge bg-warning text-dark">{{ $wf->active_task_count }}</span></td>
                  <td>
                    @if($wf->is_active)
                      <span class="badge bg-success">{{ __('Active') }}</span>
                    @else
                      <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                    @endif
                  </td>
                  <td>
                    <a href="{{ route('workflow.diagram', $wf->id) }}" class="btn btn-sm btn-outline-info" title="{{ __('View diagram') }}"><i class="fas fa-project-diagram"></i></a>
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
