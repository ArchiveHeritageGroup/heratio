{{-- Watermark Settings - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/watermarkSettingsSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Watermark Settings')

@section('content')

<h1>Watermark Settings</h1>

<form method="post" action="{{ route('acl.watermark-settings-store') }}" enctype="multipart/form-data">
  @csrf

  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Default Watermark</h5>
    </div>
    <div class="card-body">

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="default_watermark_enabled"
                 name="default_watermark_enabled" value="1"
                 {{ ($defaultEnabled ?? '') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="default_watermark_enabled">
            <strong>Enable default watermark</strong>
          </label>
        </div>
        <small class="text-muted">Apply watermark to all images by default.</small>
      </div>

      <div class="mb-3">
        <label for="default_watermark_type" class="form-label">Default Watermark Type</label>
        <select class="form-select" id="default_watermark_type" name="default_watermark_type">
          @foreach($watermarkTypes ?? [] as $wtype)
            <option value="{{ $wtype->code ?? $wtype->id ?? '' }}"
                    {{ ($defaultType ?? '') === ($wtype->code ?? '') ? 'selected' : '' }}>
              {{ $wtype->name ?? '' }}
            </option>
          @endforeach
        </select>
        <small class="text-muted">Watermark applied when no specific watermark is set.</small>
      </div>

    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Application Settings</h5>
    </div>
    <div class="card-body">

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="apply_watermark_on_view"
                 name="apply_watermark_on_view" value="1"
                 {{ ($applyOnView ?? '') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="apply_watermark_on_view">
            Apply watermark when viewing images
          </label>
        </div>
        <small class="text-muted">Watermark will be overlaid on IIIF image viewer.</small>
      </div>

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="apply_watermark_on_download"
                 name="apply_watermark_on_download" value="1"
                 {{ ($applyOnDownload ?? '') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="apply_watermark_on_download">
            Apply watermark on download
          </label>
        </div>
        <small class="text-muted">Downloaded images will have watermark applied. Master files are never modified.</small>
      </div>

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="security_watermark_override"
                 name="security_watermark_override" value="1"
                 {{ ($securityOverride ?? '') === '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="security_watermark_override">
            Security classification overrides default
          </label>
        </div>
        <small class="text-muted">Security classification watermarks take priority over default/custom watermarks.</small>
      </div>

      <div class="mb-3">
        <label for="watermark_min_size" class="form-label">Minimum Image Size</label>
        <div class="input-group" style="max-width: 200px;">
          <input type="number" class="form-control" id="watermark_min_size"
                 name="watermark_min_size" value="{{ $minSize ?? 0 }}" min="0">
          <span class="input-group-text">px</span>
        </div>
        <small class="text-muted">Images smaller than this dimension will not receive watermarks.</small>
      </div>

    </div>
  </div>

  {{-- Custom Watermarks Section --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Custom Watermarks</h5>
    </div>
    <div class="card-body">

      {{-- Upload New --}}
      <h6>Upload New Watermark</h6>
      <div class="row mb-4">
        <div class="col-md-3">
          <label for="custom_watermark_name" class="form-label">Name</label>
          <input type="text" class="form-control" id="custom_watermark_name" name="custom_watermark_name" placeholder="My Logo">
        </div>
        <div class="col-md-4">
          <label for="custom_watermark_position" class="form-label">Position</label>
          <select class="form-select" id="custom_watermark_position" name="custom_watermark_position">
            <option value="center">Center</option>
            <option value="top-left">Top Left</option>
            <option value="top-right">Top Right</option>
            <option value="bottom-left">Bottom Left</option>
            <option value="bottom-right" selected>Bottom Right</option>
            <option value="repeat">Repeat/Tile</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="custom_watermark_opacity" class="form-label">Opacity</label>
          <input type="number" class="form-control" id="custom_watermark_opacity" name="custom_watermark_opacity" value="0.40" min="0.1" max="1.0" step="0.1">
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-md-6">
          <label for="custom_watermark_file" class="form-label">Watermark Image File</label>
          <input type="file" class="form-control" id="custom_watermark_file" name="custom_watermark_file" accept="image/png,image/jpeg,image/gif">
          <small class="text-muted">Supported: PNG, JPEG, GIF. Recommended: transparent PNG.</small>
        </div>
        <div class="col-md-2 d-flex align-items-center" style="padding-top: 25px;">
          <button type="submit" name="upload_watermark" value="1" class="btn btn-success">
            <i class="fas fa-upload me-1"></i> Upload
          </button>
        </div>
      </div>

      {{-- Existing Custom Watermarks --}}
      @if(!empty($customWatermarks) && count($customWatermarks) > 0)
      <h6 class="mt-4">Existing Custom Watermarks</h6>
      <div class="row">
        @foreach($customWatermarks as $cw)
        <div class="col-md-3 mb-3">
          <div class="card h-100">
            <div class="card-body text-center p-2">
              <img src="/uploads/watermarks/{{ $cw->filename ?? '' }}" alt="{{ $cw->name ?? '' }}" style="max-width: 80px; max-height: 60px; object-fit: contain;">
              <p class="mb-1 mt-2"><small><strong>{{ $cw->name ?? '' }}</strong></small></p>
              <p class="mb-1"><small class="text-muted">{{ $cw->position ?? '' }} / {{ $cw->opacity ?? '' }}</small></p>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="default_custom_watermark_id" id="custom_{{ $cw->id }}" value="{{ $cw->id }}" {{ ($defaultCustomWatermarkId ?? '') == $cw->id ? 'checked' : '' }}>
                <label class="form-check-label" for="custom_{{ $cw->id }}"><small>Use as Default</small></label>
              </div>
            </div>
            <div class="card-footer p-1 text-center">
              <button type="submit" name="delete_custom_watermark" value="{{ $cw->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this watermark?');">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
        @endforeach
        <div class="col-md-3 mb-3">
          <div class="card h-100 border-dashed">
            <div class="card-body text-center d-flex align-items-center justify-content-center">
              <div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="default_custom_watermark_id" id="custom_none" value="" {{ empty($defaultCustomWatermarkId) ? 'checked' : '' }}>
                  <label class="form-check-label" for="custom_none"><small>No Custom (Use System)</small></label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      @else
      <p class="text-muted"><em>No custom watermarks uploaded yet.</em></p>
      @endif

    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Available Watermarks</h5>
    </div>
    <div class="card-body">
      <div class="row">
        @foreach($watermarkTypes ?? [] as $wtype)
          @if($wtype->image_file ?? null)
          <div class="col-md-3 mb-3 text-center">
            <div class="border rounded p-2" style="height: 150px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
              <img src="/images/watermarks/{{ $wtype->image_file }}"
                   alt="{{ $wtype->name ?? '' }}"
                   style="max-width: 100px; max-height: 80px; object-fit: contain;">
              <p class="mb-0 mt-2"><small><strong>{{ $wtype->name ?? '' }}</strong></small></p>
              <p class="mb-0"><small class="text-muted">{{ $wtype->code ?? '' }}</small></p>
            </div>
          </div>
          @endif
        @endforeach
      </div>
    </div>
  </div>

  <div class="actions">
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i> Save Settings
    </button>
    <a href="{{ route('settings.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i>
      Back to Settings
    </a>
  </div>

</form>

@endsection
