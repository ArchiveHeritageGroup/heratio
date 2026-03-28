@extends('ahg-theme-b5::layout')

@section('title', 'Watermark Settings')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item active">Watermark Settings</li>
  </ol></nav>

  <h1><i class="fas fa-stamp"></i> Watermark Settings</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <form method="POST" action="{{ route('security-clearance.watermark-settings-store') }}">
    @csrf

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">General Settings</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3 form-check">
              <input type="hidden" name="default_enabled" value="0">
              <input type="checkbox" name="default_enabled" value="1" class="form-check-input" id="defaultEnabled"
                     {{ ($settings['default_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="defaultEnabled">Enable watermarks by default</label>
            </div>
            <div class="mb-3">
              <label class="form-label">Default Watermark Type</label>
              <select name="default_type" class="form-select">
                <option value="COPYRIGHT" {{ ($settings['default_type'] ?? '') === 'COPYRIGHT' ? 'selected' : '' }}>Copyright</option>
                <option value="CONFIDENTIAL" {{ ($settings['default_type'] ?? '') === 'CONFIDENTIAL' ? 'selected' : '' }}>Confidential</option>
                <option value="DRAFT" {{ ($settings['default_type'] ?? '') === 'DRAFT' ? 'selected' : '' }}>Draft</option>
                <option value="CUSTOM" {{ ($settings['default_type'] ?? '') === 'CUSTOM' ? 'selected' : '' }}>Custom</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Minimum Image Size (px)</label>
              <input type="number" name="min_size" class="form-control" value="{{ $settings['min_size'] ?? 200 }}">
              <small class="text-muted">Images smaller than this won't be watermarked.</small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3 form-check">
              <input type="hidden" name="apply_on_view" value="0">
              <input type="checkbox" name="apply_on_view" value="1" class="form-check-input" id="applyOnView"
                     {{ ($settings['apply_on_view'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="applyOnView">Apply watermark on view</label>
            </div>
            <div class="mb-3 form-check">
              <input type="hidden" name="apply_on_download" value="0">
              <input type="checkbox" name="apply_on_download" value="1" class="form-check-input" id="applyOnDownload"
                     {{ ($settings['apply_on_download'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="applyOnDownload">Apply watermark on download</label>
            </div>
            <div class="mb-3 form-check">
              <input type="hidden" name="security_override" value="0">
              <input type="checkbox" name="security_override" value="1" class="form-check-input" id="securityOverride"
                     {{ ($settings['security_override'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="securityOverride">Security clearance overrides watermark</label>
              <small class="text-muted d-block">Users with sufficient clearance can bypass watermarks.</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Watermark Types --}}
    @if($watermarkTypes->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Available Watermark Types</h5></div>
      <div class="card-body table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Name</th><th>Code</th><th>Preview</th><th>Status</th></tr></thead>
          <tbody>
            @foreach($watermarkTypes as $type)
            <tr>
              <td>{{ e($type->name ?? '') }}</td>
              <td><code>{{ e($type->code ?? '') }}</code></td>
              <td>{{ e($type->description ?? '') }}</td>
              <td><span class="badge bg-{{ ($type->active ?? 0) ? 'success' : 'secondary' }}">{{ ($type->active ?? 0) ? 'Active' : 'Inactive' }}</span></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    {{-- Custom Watermarks --}}
    @if($customWatermarks->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Custom Watermarks</h5></div>
      <div class="card-body table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Name</th><th>File</th><th>Position</th><th>Opacity</th></tr></thead>
          <tbody>
            @foreach($customWatermarks as $wm)
            <tr>
              <td>{{ e($wm->name ?? '') }}</td>
              <td>{{ e($wm->file_path ?? '') }}</td>
              <td>{{ e($wm->position ?? 'center') }}</td>
              <td>{{ ($wm->opacity ?? 50) }}%</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
    <a href="{{ route('security-clearance.trace-watermark') }}" class="btn btn-outline-secondary"><i class="fas fa-search"></i> Trace Watermark</a>
  </form>
</div>
@endsection
