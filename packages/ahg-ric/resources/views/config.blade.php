@extends('theme::layouts.1col')
@section('title', 'RiC Sync Configuration')
@section('body-class', 'edit')
@section('content')

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">RiC Sync Configuration</h1></div>
  </div>

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ric.index') }}">RiC Dashboard</a></li>
      <li class="breadcrumb-item active">Configuration</li>
    </ol>
  </nav>

  @if(session('notice'))
    <div class="alert alert-success">{{ session('notice') }}</div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <form method="POST" action="{{ route('ric.config') }}">
    @csrf
    <div class="row g-4">
      {{-- Fuseki Connection --}}
      <div class="col-md-6">
        <div class="card">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-plug me-2"></i>Fuseki Connection</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Endpoint URL</label>
              <input type="text" class="form-control" name="config[fuseki_endpoint]"
                     value="{{ old('config.fuseki_endpoint', $config['fuseki_endpoint'] ?? '') }}"
                     placeholder="http://localhost:3030/ric">
            </div>
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" name="config[fuseki_username]"
                     value="{{ old('config.fuseki_username', $config['fuseki_username'] ?? '') }}"
                     placeholder="admin">
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" name="config[fuseki_password]"
                     value="{{ old('config.fuseki_password', $config['fuseki_password'] ?? '') }}">
            </div>
            <button type="button" class="btn btn-outline-secondary" id="test-connection-btn">
              <i class="fas fa-plug me-1"></i> Test Connection
            </button>
            <span id="test-connection-result" class="ms-2"></span>
          </div>
        </div>
      </div>

      {{-- Sync Settings --}}
      <div class="col-md-6">
        <div class="card">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Sync Settings</h5>
          </div>
          <div class="card-body">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="config[sync_enabled]" value="1"
                     id="sync_enabled" {{ ($config['sync_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="sync_enabled">Enable automatic sync</label>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="config[queue_enabled]" value="1"
                     id="queue_enabled" {{ ($config['queue_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="queue_enabled">Use async queue</label>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="config[cascade_delete]" value="1"
                     id="cascade_delete" {{ ($config['cascade_delete'] ?? '1') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="cascade_delete">Cascade delete references</label>
            </div>
            <div class="mb-3">
              <label class="form-label">Batch Size</label>
              <input type="number" class="form-control" name="config[batch_size]"
                     value="{{ old('config.batch_size', $config['batch_size'] ?? '100') }}"
                     min="1" max="10000">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <a href="{{ route('ric.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
      </a>
      <button type="submit" class="btn atom-btn-white">
        <i class="fas fa-save me-1"></i> Save Configuration
      </button>
    </div>
  </form>

@endsection

@section('after-content')
<script>
document.getElementById('test-connection-btn').addEventListener('click', function() {
  var btn = this;
  var result = document.getElementById('test-connection-result');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
  result.innerHTML = '';

  fetch('{{ route("ric.ajax-stats") }}')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-plug me-1"></i> Test Connection';
      if (data.fuseki_status && data.fuseki_status.online) {
        result.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Connection successful!' +
          (data.fuseki_status.triple_count ? ' Triple count: ' + data.fuseki_status.triple_count : '') + '</span>';
      } else {
        var error = (data.fuseki_status && data.fuseki_status.error) ? data.fuseki_status.error : 'Could not reach Fuseki';
        result.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Connection failed: ' + error + '</span>';
      }
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-plug me-1"></i> Test Connection';
      result.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Error: ' + err.message + '</span>';
    });
});
</script>
@endsection
