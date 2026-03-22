@extends('theme::layouts.1col')

@section('title', 'Watermark Settings')
@section('body-class', 'admin media-processing watermark')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-stamp"></i> Watermark Settings</h1>
    <a href="{{ route('media-processing.index') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left"></i> Back to Media Processing
    </a>
  </div>
  <p class="text-muted mb-4">Configure watermark application for digital object derivatives and downloads.</p>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    {{-- Global Settings --}}
    <div class="col-lg-8">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-cog"></i> Global Watermark Settings</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('media-processing.watermark-settings') }}">
            @csrf

            <div class="row mb-3">
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="default_watermark_enabled"
                    name="default_watermark_enabled" value="1"
                    {{ ($settings['default_watermark_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="default_watermark_enabled">
                    <strong>Enable default watermark</strong> <span class="badge bg-secondary ms-1">Optional</span>
                  </label>
                  <div class="form-text">Apply watermark to all images by default</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="security_watermark_override"
                    name="security_watermark_override" value="1"
                    {{ ($settings['security_watermark_override'] ?? '1') === '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="security_watermark_override">
                    <strong>Security classification override</strong> <span class="badge bg-secondary ms-1">Optional</span>
                  </label>
                  <div class="form-text">Security classification watermarks take priority</div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="apply_watermark_on_view"
                    name="apply_watermark_on_view" value="1"
                    {{ ($settings['apply_watermark_on_view'] ?? '1') === '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="apply_watermark_on_view">
                    Apply watermark on view (IIIF) <span class="badge bg-secondary ms-1">Optional</span>
                  </label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="apply_watermark_on_download"
                    name="apply_watermark_on_download" value="1"
                    {{ ($settings['apply_watermark_on_download'] ?? '1') === '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="apply_watermark_on_download">
                    Apply watermark on download <span class="badge bg-secondary ms-1">Optional</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="default_watermark_type" class="form-label">Default Watermark Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" id="default_watermark_type" name="default_watermark_type">
                  @foreach($watermarkTypes as $type)
                    <option value="{{ $type->code }}"
                      {{ ($settings['default_watermark_type'] ?? 'COPYRIGHT') === $type->code ? 'selected' : '' }}>
                      {{ $type->name }}
                      @if($type->code !== 'NONE')
                        ({{ $type->position ?? 'center' }}, {{ (int)(($type->opacity ?? 0.40) * 100) }}%)
                      @endif
                    </option>
                  @endforeach
                </select>
                <div class="form-text">Watermark type applied when no per-object setting exists</div>
              </div>
              <div class="col-md-6">
                <label for="watermark_min_size" class="form-label">Minimum Image Size (px) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" class="form-control" id="watermark_min_size" name="watermark_min_size"
                  value="{{ $settings['watermark_min_size'] ?? '200' }}" min="50" max="2000">
                <div class="form-text">Images smaller than this dimension (width or height) will not be watermarked</div>
              </div>
            </div>

            @if($customWatermarks->isNotEmpty())
            <div class="mb-3">
              <label for="default_custom_watermark_id" class="form-label">Default Custom Watermark <span class="badge bg-secondary ms-1">Optional</span></label>
              <select class="form-select" id="default_custom_watermark_id" name="default_custom_watermark_id">
                <option value="">-- Use system watermark type --</option>
                @foreach($customWatermarks as $cw)
                  <option value="{{ $cw->id }}"
                    {{ ($settings['default_custom_watermark_id'] ?? '') == $cw->id ? 'selected' : '' }}>
                    {{ $cw->name }} ({{ $cw->position ?? 'center' }}, {{ (int)(($cw->opacity ?? 0.40) * 100) }}%)
                  </option>
                @endforeach
              </select>
              <div class="form-text">Override the system watermark type with a custom uploaded watermark</div>
            </div>
            @endif

            <div class="text-end">
              <button type="submit" class="btn atom-btn-outline-success">
                <i class="fas fa-save"></i> Save Settings
              </button>
            </div>
          </form>
        </div>
      </div>

      {{-- Watermark Position Preview --}}
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-crosshairs"></i> Position Reference</h5>
        </div>
        <div class="card-body">
          <div class="position-preview-grid mx-auto" style="max-width: 400px;">
            <div class="border rounded p-2 bg-light">
              <div class="row g-1 text-center" style="font-size: 0.8rem;">
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Top Left<br><small>NorthWest</small></span></div>
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Top Center<br><small>North</small></span></div>
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Top Right<br><small>NorthEast</small></span></div>
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Center Left<br><small>West</small></span></div>
                <div class="col-4"><span class="badge bg-primary w-100 py-2">Center<br><small>Center</small></span></div>
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Center Right<br><small>East</small></span></div>
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Bottom Left<br><small>SouthWest</small></span></div>
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Bottom Center<br><small>South</small></span></div>
                <div class="col-4"><span class="badge bg-secondary w-100 py-2">Bottom Right<br><small>SouthEast</small></span></div>
              </div>
              <div class="text-center mt-2">
                <span class="badge bg-info w-100 py-2">Repeat (Tile)<br><small>Covers entire image</small></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Custom Watermarks Sidebar --}}
    <div class="col-lg-4">
      {{-- Upload Custom Watermark --}}
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-upload"></i> Upload Custom Watermark</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('media-processing.watermark-settings') }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
              <label for="custom_watermark_name" class="form-label">Name <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" class="form-control" id="custom_watermark_name"
                name="custom_watermark_name" required maxlength="100" placeholder="e.g. Company Logo">
            </div>

            <div class="mb-3">
              <label for="custom_watermark_file" class="form-label">Watermark Image <span class="badge bg-danger ms-1">Required</span></label>
              <input type="file" class="form-control" id="custom_watermark_file"
                name="custom_watermark_file" required accept="image/png,image/jpeg,image/gif">
              <div class="form-text">PNG, JPEG, or GIF. Max 5 MB. Transparent PNG recommended.</div>
            </div>

            <div class="mb-3">
              <label for="custom_watermark_position" class="form-label">Position <span class="badge bg-secondary ms-1">Optional</span></label>
              <select class="form-select" id="custom_watermark_position" name="custom_watermark_position">
                @foreach($positions as $value => $label)
                  <option value="{{ strtolower(str_replace(['North', 'South', 'East', 'West'], ['top', 'bottom', 'right', 'left'], $value)) }}"
                    {{ $value === 'SouthEast' ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="custom_watermark_opacity" class="form-label">
                Opacity: <span id="opacity_value">40</span>% <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="range" class="form-range" id="custom_watermark_opacity"
                name="custom_watermark_opacity" min="0" max="1" step="0.05" value="0.40"
                oninput="document.getElementById('opacity_value').textContent = Math.round(this.value * 100)">
            </div>

            <button type="submit" class="btn atom-btn-outline-success w-100">
              <i class="fas fa-upload"></i> Upload Watermark
            </button>
          </form>
        </div>
      </div>

      {{-- Existing Custom Watermarks --}}
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-images"></i> Custom Watermarks</h5>
        </div>
        <div class="card-body p-0">
          @forelse($customWatermarks as $cw)
          <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <div>
              <strong>{{ $cw->name }}</strong>
              <div class="small text-muted">
                {{ $cw->position ?? 'center' }} / {{ (int)(($cw->opacity ?? 0.40) * 100) }}% opacity
              </div>
              <div class="small text-muted">{{ $cw->filename }}</div>
            </div>
            <form method="POST" action="{{ route('media-processing.watermark-settings') }}" class="d-inline">
              @csrf
              <input type="hidden" name="delete_custom_watermark" value="{{ $cw->id }}">
              <button type="submit" class="btn btn-sm atom-btn-outline-danger"
                onclick="return confirm('Delete this custom watermark?')" title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
          @empty
          <div class="p-3 text-muted text-center">No custom watermarks uploaded yet.</div>
          @endforelse
        </div>
      </div>

      {{-- System Watermark Types --}}
      <div class="card shadow-sm">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-shield-alt"></i> System Watermark Types</h5>
        </div>
        <div class="card-body p-0">
          @foreach($watermarkTypes as $type)
          <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <div>
              <strong>{{ $type->name }}</strong>
              <span class="badge bg-secondary ms-1">{{ $type->code }}</span>
              @if($type->code !== 'NONE')
              <div class="small text-muted">
                {{ $type->image_file }} / {{ $type->position ?? 'center' }} / {{ (int)(($type->opacity ?? 0.40) * 100) }}%
              </div>
              @endif
            </div>
            <span class="badge {{ $type->active ? 'bg-success' : 'bg-danger' }}">
              {{ $type->active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
@endsection
