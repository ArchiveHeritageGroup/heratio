@extends('theme::layouts.1col')

@section('title', 'TripoSR Settings')
@section('body-class', 'settings triposr')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-brain me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">TripoSR Settings</h1>
    </div>
  </div>

  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-brain me-2"></i>TripoSR AI</div><div class="card-body">
    <form method="POST" action="{{ $formAction ?? '#' }}">@csrf
      <div class="mb-3"><label for="triposr_endpoint" class="form-label">API Endpoint <span class="badge bg-secondary">field</span></label><input type="url" class="form-control" id="triposr_endpoint" name="triposr_endpoint" value="{{ old('triposr_endpoint', $settings['triposr_endpoint'] ?? '') }}"></div>
      <div class="mb-3"><label for="triposr_api_key" class="form-label">API Key <span class="badge bg-secondary">field</span></label><input type="password" class="form-control" id="triposr_api_key" name="triposr_api_key" value="{{ old('triposr_api_key', $settings['triposr_api_key'] ?? '') }}"></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="triposr_enabled" name="triposr_enabled" {{ ($settings['triposr_enabled'] ?? false) ? 'checked' : '' }}><label class="form-check-label" for="triposr_enabled">Enable TripoSR</label></div>
      <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button>
      <a href="{{ route('admin.3d-models.settings') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </form>
  </div></div>
@endsection
