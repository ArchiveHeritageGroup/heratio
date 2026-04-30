@extends('theme::layouts.1col')

@section('title', 'Edit Detection Rule')
@section('body-class', 'admin dedupe rule-edit')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-edit me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Edit Detection Rule') }}</h1>
      <span class="small text-muted">{{ __('Duplicate Detection') }}</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('dedupe.rules') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Rules') }}
      </a>
    </div>
  </div>

  <form method="post" action="{{ route('dedupe.rule.update', $rule->id) }}">
    @csrf
    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0">{{ __('Rule Details') }}</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="name" class="form-label">Rule Name <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" class="form-control" id="name" name="name" required
                     value="{{ e($rule->name) }}">
            </div>

            <div class="mb-3">
              <label for="rule_type" class="form-label">Rule Type <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select class="form-select" id="rule_type" name="rule_type" required>
                @foreach($ruleTypes as $value => $label)
                  <option value="{{ $value }}" {{ $rule->rule_type === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="threshold" class="form-label">Threshold (0.0 - 1.0) <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                  <input type="number" class="form-control" id="threshold" name="threshold"
                         min="0" max="1" step="0.01" value="{{ $rule->threshold }}" required>
                  <div class="form-text">Minimum similarity score to flag as duplicate</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="priority" class="form-label">Priority <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <input type="number" class="form-control" id="priority" name="priority"
                         value="{{ $rule->priority }}" min="1" max="1000">
                  <div class="form-text">Higher priority rules run first</div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label">Apply to Repository <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <select class="form-select" id="repository_id" name="repository_id">
                <option value="">{{ __('All Repositories (Global)') }}</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo->id }}" {{ $rule->repository_id == $repo->id ? 'selected' : '' }}>
                    {{ $repo->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="config_json" class="form-label">Configuration (JSON) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <textarea class="form-control font-monospace" id="config_json" name="config_json"
                        rows="4">{{ e($rule->config_json ?? '') }}</textarea>
              <div class="form-text">Optional rule-specific configuration in JSON format</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1"
                         {{ $rule->is_enabled ? 'checked' : '' }}>
                  <label class="form-check-label" for="is_enabled">Enabled <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="is_blocking" name="is_blocking" value="1"
                         {{ $rule->is_blocking ? 'checked' : '' }}>
                  <label class="form-check-label" for="is_blocking">Blocking <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <div class="form-text">Block record save if duplicate found</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-save me-1"></i> {{ __('Update Rule') }}
          </button>
          <a href="{{ route('dedupe.rules') }}" class="btn atom-btn-white">Cancel</a>
          <a href="{{ route('dedupe.rule.delete', $rule->id) }}" class="btn atom-btn-outline-danger ms-auto"
             onclick="return confirm('Delete this rule?');">
            <i class="fas fa-trash me-1"></i> {{ __('Delete') }}
          </a>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Rule Info') }}</h5>
          </div>
          <div class="card-body">
            <p><strong>{{ __('Created:') }}</strong><br>
              {{ $rule->created_at ? \Carbon\Carbon::parse($rule->created_at)->format('M j, Y H:i') : '-' }}
            </p>
            <p class="mb-0"><strong>{{ __('Last Updated:') }}</strong><br>
              {{ $rule->updated_at ? \Carbon\Carbon::parse($rule->updated_at)->format('M j, Y H:i') : '-' }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection
