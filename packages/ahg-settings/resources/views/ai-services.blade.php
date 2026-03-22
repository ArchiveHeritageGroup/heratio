@extends('theme::layouts.1col')
@section('title', 'AI Services Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="mb-0"><i class="fas fa-brain text-primary"></i> AI Services Settings</h1>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to AHG Settings</a>
    </div>

    <form method="post" action="{{ route('settings.ai-services') }}">
      @csrf

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff;"><h5 class="mb-0"><i class="fas fa-cog me-2"></i>General Settings</h5></div>
        <div class="card-body">
          <div class="row mb-3">
            <label class="col-sm-3 col-form-label">Processing Mode <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="processing_mode" id="mode_hybrid" value="hybrid" {{ ($settings['processing_mode'] ?? 'job') === 'hybrid' ? 'checked' : '' }}>
                <label class="form-check-label" for="mode_hybrid"><strong>Hybrid</strong> - Interactive for small docs, background for large</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="processing_mode" id="mode_job" value="job" {{ ($settings['processing_mode'] ?? 'job') === 'job' ? 'checked' : '' }}>
                <label class="form-check-label" for="mode_job"><strong>Background Job</strong> - Always queue (recommended for production)</label>
              </div>
            </div>
          </div>
          <div class="row mb-3">
            <label class="col-sm-3 col-form-label">API Endpoint <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="api_url" value="{{ $settings['api_url'] ?? '' }}">
              <small class="text-muted">URL of the AI service (e.g., http://localhost:5004/ai/v1)</small>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff;"><h5 class="mb-0"><i class="fas fa-spell-check me-2"></i>NER Settings</h5></div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input type="hidden" name="ner_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="ner_enabled" id="ner_enabled" value="1" {{ ($settings['ner_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="ner_enabled">Enable Named Entity Recognition</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Confidence Threshold <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" class="form-control" name="ner_confidence" value="{{ $settings['ner_confidence'] ?? '0.75' }}" min="0" max="1" step="0.05" style="max-width:200px;">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff;"><h5 class="mb-0"><i class="fas fa-language me-2"></i>Translation Settings</h5></div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input type="hidden" name="translation_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="translation_enabled" id="translation_enabled" value="1" {{ ($settings['translation_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="translation_enabled">Enable Auto-Translation</label>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff;"><h5 class="mb-0"><i class="fas fa-spell-check me-2"></i>Spellcheck Settings</h5></div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input type="hidden" name="spellcheck_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="spellcheck_enabled" id="spellcheck_enabled" value="1" {{ ($settings['spellcheck_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="spellcheck_enabled">Enable Spellcheck</label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
