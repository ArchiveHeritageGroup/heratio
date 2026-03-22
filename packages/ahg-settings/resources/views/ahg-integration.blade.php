@extends('theme::layouts.1col')
@section('title', 'AHG Central Integration')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-cloud me-2"></i>AHG Central Integration</h1>

    @if(isset($testResult))
      <div class="alert alert-{{ $testResult['success'] ? 'success' : 'danger' }} alert-dismissible fade show">
        <strong>{{ $testResult['success'] ? 'Success!' : 'Error:' }}</strong> {{ $testResult['message'] }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-cloud me-2"></i>About AHG Central</h5></div>
      <div class="card-body">
        <p class="mb-2">AHG Central is a cloud service provided by The Archive and Heritage Group that enhances your instance with:</p>
        <ul class="mb-3">
          <li><strong>Shared NER Training</strong> - Contribute and benefit from a community-trained Named Entity Recognition model</li>
          <li><strong>Future AI Services</strong> - Access to upcoming cloud-based AI features</li>
          <li><strong>Usage Analytics</strong> - Optional aggregate statistics to improve the platform</li>
        </ul>
        <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>Note: This is separate from local AI services. Local AI services run on your own infrastructure while AHG Central is a cloud service.</p>
      </div>
    </div>

    <form method="post" action="{{ route('settings.ahg-integration') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-cog me-2"></i>Connection Settings</h5></div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input type="hidden" name="settings[ahg_central_enabled]" value="0">
            <input class="form-check-input" type="checkbox" name="settings[ahg_central_enabled]" id="ahg_central_enabled" value="1" {{ ($settings['ahg_central_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="ahg_central_enabled">Enable AHG Central Integration</label>
            <div class="form-text">Allow this instance to communicate with AHG Central services.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">AHG Central API URL <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="url" name="settings[ahg_central_api_url]" class="form-control" value="{{ $settings['ahg_central_api_url'] ?? 'https://central.theahg.co.za/api/v1' }}">
          </div>
          <div class="mb-3">
            <label class="form-label">API Key <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="password" name="settings[ahg_central_api_key]" class="form-control" id="ahg_api_key" value="{{ $settings['ahg_central_api_key'] ?? '' }}">
              <button class="btn atom-btn-white" type="button" onclick="var i=document.getElementById('ahg_api_key');i.type=i.type==='password'?'text':'password';"><i class="fas fa-eye"></i></button>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
        <button type="submit" name="action" value="test" class="btn btn-outline-info"><i class="fas fa-plug me-1"></i>Test Connection</button>
        <a href="{{ route('settings.index') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
