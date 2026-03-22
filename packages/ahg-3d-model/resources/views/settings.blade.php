@extends('theme::layouts.1col')

@section('title', '3D Model Settings')
@section('body-class', 'settings model3d')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">3D Model Settings</h1>
    </div>
  </div>

  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cog me-2"></i>Settings</div><div class="card-body">
    <form method="POST" action="{{ $formAction ?? '#' }}">@csrf
      <div class="mb-3"><label for="max_file_size" class="form-label">Max File Size (MB) <span class="badge bg-secondary">field</span></label><input type="number" class="form-control" id="max_file_size" name="max_file_size" value="{{ old('max_file_size', $settings['max_file_size'] ?? 100) }}"></div>
      <div class="mb-3"><label for="allowed_formats" class="form-label">Allowed Formats <span class="badge bg-secondary">field</span></label><input type="text" class="form-control" id="allowed_formats" name="allowed_formats" value="{{ old('allowed_formats', $settings['allowed_formats'] ?? 'glb,gltf,obj,stl,ply,usdz') }}"><div class="form-text">Comma-separated</div></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="auto_thumbnail" name="auto_thumbnail" {{ ($settings['auto_thumbnail'] ?? true) ? 'checked' : '' }}><label class="form-check-label" for="auto_thumbnail">Auto-generate thumbnails</label></div>
      <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button>
    </form>
  </div></div>
  <div class="mt-3"><a href="{{ route('admin.3d-models.triposr') }}" class="btn atom-btn-white"><i class="fas fa-brain me-1"></i> TripoSR Settings</a></div>
@endsection
