@extends('theme::layouts.1col')

@section('title', $rule ? 'Edit Gate Rule' : 'Create Gate Rule')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-shield-alt"></i> {{ $rule ? 'Edit' : 'Create' }} Gate Rule</h1>
    <a href="{{ route('workflow.gates.admin') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form action="{{ route('workflow.gates.edit', $rule->id ?? null) }}" method="POST">
        @csrf

        <div class="row">
          <div class="col-md-8">
            <div class="mb-3">
              <label for="name" class="form-label">Rule Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $rule->name ?? '') }}" required maxlength="255">
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label for="sort_order" class="form-label">Sort Order</label>
              <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', $rule->sort_order ?? 0) }}">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label for="rule_type" class="form-label">Rule Type <span class="text-danger">*</span></label>
              <select class="form-select" id="rule_type" name="rule_type">
                <option value="required_field" {{ old('rule_type', $rule->rule_type ?? '') === 'required_field' ? 'selected' : '' }}>Required Field</option>
                <option value="workflow_completed" {{ old('rule_type', $rule->rule_type ?? '') === 'workflow_completed' ? 'selected' : '' }}>Workflow Completed</option>
                <option value="digital_object_required" {{ old('rule_type', $rule->rule_type ?? '') === 'digital_object_required' ? 'selected' : '' }}>Digital Object Required</option>
                <option value="min_description_length" {{ old('rule_type', $rule->rule_type ?? '') === 'min_description_length' ? 'selected' : '' }}>Min Description Length</option>
                <option value="custom_sql" {{ old('rule_type', $rule->rule_type ?? '') === 'custom_sql' ? 'selected' : '' }}>Custom SQL</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label for="entity_type" class="form-label">Entity Type</label>
              <select class="form-select" id="entity_type" name="entity_type">
                <option value="information_object" {{ old('entity_type', $rule->entity_type ?? 'information_object') === 'information_object' ? 'selected' : '' }}>Information Object</option>
                <option value="actor" {{ old('entity_type', $rule->entity_type ?? '') === 'actor' ? 'selected' : '' }}>Actor</option>
                <option value="repository" {{ old('entity_type', $rule->entity_type ?? '') === 'repository' ? 'selected' : '' }}>Repository</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label for="severity" class="form-label">Severity <span class="text-danger">*</span></label>
              <select class="form-select" id="severity" name="severity">
                <option value="blocker" {{ old('severity', $rule->severity ?? 'blocker') === 'blocker' ? 'selected' : '' }}>Blocker</option>
                <option value="warning" {{ old('severity', $rule->severity ?? '') === 'warning' ? 'selected' : '' }}>Warning</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label for="field_name" class="form-label">Field Name</label>
              <input type="text" class="form-control" id="field_name" name="field_name" value="{{ old('field_name', $rule->field_name ?? '') }}" placeholder="e.g. title, scope_and_content">
              <small class="text-muted">Used by required_field and min_description_length rules</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label for="level_of_description_id" class="form-label">Level of Description ID</label>
              <input type="number" class="form-control" id="level_of_description_id" name="level_of_description_id" value="{{ old('level_of_description_id', $rule->level_of_description_id ?? '') }}" placeholder="Leave empty for all">
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label for="repository_id" class="form-label">Repository ID</label>
              <input type="number" class="form-control" id="repository_id" name="repository_id" value="{{ old('repository_id', $rule->repository_id ?? '') }}" placeholder="Leave empty for all">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label for="material_type" class="form-label">Material Type</label>
              <input type="text" class="form-control" id="material_type" name="material_type" value="{{ old('material_type', $rule->material_type ?? '') }}" placeholder="Leave empty for all">
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label for="rule_config" class="form-label">Rule Configuration (JSON)</label>
              <textarea class="form-control" id="rule_config" name="rule_config" rows="2" placeholder='e.g. {"min_length": 100}'>{{ old('rule_config', $rule->rule_config ?? '') }}</textarea>
              <small class="text-muted">JSON config for min_description_length (min_length) or custom_sql (sql)</small>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="error_message" class="form-label">Error Message <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="error_message" name="error_message" value="{{ old('error_message', $rule->error_message ?? '') }}" required maxlength="500">
          <small class="text-muted">Displayed when the rule fails</small>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ old('is_active', $rule->is_active ?? 1) ? 'checked' : '' }}>
          <label class="form-check-label" for="is_active">Active</label>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> {{ $rule ? 'Update' : 'Create' }} Rule</button>
          <a href="{{ route('workflow.gates.admin') }}" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
@endsection
