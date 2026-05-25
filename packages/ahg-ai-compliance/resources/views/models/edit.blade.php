{{--
  Heratio - AI model registry edit/create form.
  Copyright (c) 2026 Plain Sailing Information Systems.
  Johan Pieterse <johan@plainsailingisystems.co.za>. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')
@section('title', ($model->exists ? 'Edit' : 'Add') . ' AI Model')
@section('body-class', 'admin ai-compliance')

@section('content')
<div class="row">
  <div class="col-md-12">
    <h1><i class="fas fa-microchip me-2"></i>{{ $model->exists ? __('Edit model') : __('Add model') }}</h1>

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="post" action="{{ $model->exists ? route('ai-compliance.models.update', $model->id) : route('ai-compliance.models.store') }}">
      @csrf
      @if($model->exists)
        @method('PUT')
      @endif

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-id-card me-2"></i>{{ __('Identity') }}</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">{{ __('Service') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select name="service" class="form-select" required>
                @foreach($services as $svc)
                  <option value="{{ $svc }}" @selected(old('service', $model->service) === $svc)>{{ $svc }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-5 mb-3">
              <label class="form-label">{{ __('Model ID') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" name="model_id" class="form-control" maxlength="128" value="{{ old('model_id', $model->model_id) }}" required>
              <div class="form-text">{{ __('Vendor/family identifier, e.g.') }} <code>mistral:7b-instruct-v0.2</code></div>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">{{ __('Version') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" name="model_version" class="form-control" maxlength="64" value="{{ old('model_version', $model->model_version) }}" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">{{ __('Deployed at') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="datetime-local" name="deployed_at" class="form-control" value="{{ old('deployed_at', optional($model->deployed_at)->format('Y-m-d\TH:i') ?: now()->format('Y-m-d\TH:i')) }}" required>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">{{ __('Retired at') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="datetime-local" name="retired_at" class="form-control" value="{{ old('retired_at', optional($model->retired_at)->format('Y-m-d\TH:i')) }}">
              <div class="form-text">{{ __('Leave empty while the model is still in production.') }}</div>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">{{ __('Gateway endpoint') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="text" name="gateway_endpoint" class="form-control" maxlength="255" value="{{ old('gateway_endpoint', $model->gateway_endpoint) }}">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-clipboard-list me-2"></i>{{ __('Annex IV narrative') }}</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Intended purpose') }}</label>
            <textarea name="intended_purpose" class="form-control" rows="2">{{ old('intended_purpose', $model->intended_purpose) }}</textarea>
            <div class="form-text">{{ __('Annex IV section 1 - intended purpose of the AI system.') }}</div>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Training data summary') }}</label>
            <textarea name="training_data_summary" class="form-control" rows="3">{{ old('training_data_summary', $model->training_data_summary) }}</textarea>
            <div class="form-text">{{ __('Annex IV section 2 - summary of the data used to train, validate, and test the model.') }}</div>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Known limits') }}</label>
            <textarea name="known_limits" class="form-control" rows="3">{{ old('known_limits', $model->known_limits) }}</textarea>
            <div class="form-text">{{ __('Annex IV section 3 - capabilities and limitations of the system.') }}</div>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Accuracy metrics (JSON)') }}</label>
            <textarea name="accuracy_metrics_json" class="form-control font-monospace" rows="4">{{ old('accuracy_metrics_json', is_array($model->accuracy_metrics_json) ? json_encode($model->accuracy_metrics_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
            <div class="form-text">{{ __('Annex IV section 4 - structured accuracy metrics. JSON object, free-form schema. Example:') }} <code>{"bleu": 0.42, "chrf": 0.61}</code></div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
      <a href="{{ route('ai-compliance.models.index') }}" class="btn atom-btn-white ms-2">{{ __('Cancel') }}</a>
    </form>
  </div>
</div>
@endsection
