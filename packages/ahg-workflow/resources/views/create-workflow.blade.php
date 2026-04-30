@extends('theme::layouts.1col')

@section('title', 'Create Workflow')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-plus-circle"></i> Create Workflow</h1>
    <a href="{{ route('workflow.admin') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left"></i> {{ __('Back') }}</a>
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
      <form action="{{ route('workflow.admin.create') }}" method="POST">
        @csrf

        <div class="row">
          <div class="col-md-8">
            <div class="mb-3">
              <label for="name" class="form-label">Workflow Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="255">
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
            </div>
          </div>

          <div class="col-md-4">
            <div class="mb-3">
              <label for="scope_type" class="form-label">Scope <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select class="form-select" id="scope_type" name="scope_type">
                <option value="global" {{ old('scope_type') === 'global' ? 'selected' : '' }}>{{ __('Global') }}</option>
                <option value="repository" {{ old('scope_type') === 'repository' ? 'selected' : '' }}>{{ __('Repository') }}</option>
                <option value="collection" {{ old('scope_type') === 'collection' ? 'selected' : '' }}>{{ __('Collection') }}</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="scope_id" class="form-label">Scope ID <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="number" class="form-control" id="scope_id" name="scope_id" value="{{ old('scope_id') }}" placeholder="{{ __('Leave empty for global') }}">
            </div>

            <div class="mb-3">
              <label for="trigger_event" class="form-label">Trigger Event <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select class="form-select" id="trigger_event" name="trigger_event">
                <option value="submit" {{ old('trigger_event') === 'submit' ? 'selected' : '' }}>{{ __('Submit') }}</option>
                <option value="publish" {{ old('trigger_event') === 'publish' ? 'selected' : '' }}>{{ __('Publish') }}</option>
                <option value="update" {{ old('trigger_event') === 'update' ? 'selected' : '' }}>{{ __('Update') }}</option>
                <option value="create" {{ old('trigger_event') === 'create' ? 'selected' : '' }}>{{ __('Create') }}</option>
                <option value="manual" {{ old('trigger_event') === 'manual' ? 'selected' : '' }}>{{ __('Manual') }}</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="applies_to" class="form-label">Applies To <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select class="form-select" id="applies_to" name="applies_to">
                <option value="information_object" {{ old('applies_to') === 'information_object' ? 'selected' : '' }}>{{ __('Information Object') }}</option>
                <option value="actor" {{ old('applies_to') === 'actor' ? 'selected' : '' }}>{{ __('Actor') }}</option>
                <option value="repository" {{ old('applies_to') === 'repository' ? 'selected' : '' }}>{{ __('Repository') }}</option>
                <option value="accession" {{ old('applies_to') === 'accession' ? 'selected' : '' }}>{{ __('Accession') }}</option>
              </select>
            </div>
          </div>
        </div>

        <hr>

        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label for="auto_archive_days" class="form-label">Auto Archive (days) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="number" class="form-control" id="auto_archive_days" name="auto_archive_days" value="{{ old('auto_archive_days') }}" placeholder="{{ __('Leave empty to disable') }}">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
              <label class="form-check-label" for="is_active">Active <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="is_default" name="is_default" {{ old('is_default') ? 'checked' : '' }}>
              <label class="form-check-label" for="is_default">Default Workflow <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="require_all_steps" name="require_all_steps" {{ old('require_all_steps', true) ? 'checked' : '' }}>
              <label class="form-check-label" for="require_all_steps">Require All Steps <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="allow_parallel" name="allow_parallel" {{ old('allow_parallel') ? 'checked' : '' }}>
              <label class="form-check-label" for="allow_parallel">Allow Parallel Steps <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            </div>
          </div>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="notification_enabled" name="notification_enabled" {{ old('notification_enabled', true) ? 'checked' : '' }}>
          <label class="form-check-label" for="notification_enabled">Enable Notifications <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save"></i> {{ __('Create Workflow') }}</button>
          <a href="{{ route('workflow.admin') }}" class="btn atom-btn-white">Cancel</a>
        </div>
      </form>
    </div>
  </div>
@endsection
