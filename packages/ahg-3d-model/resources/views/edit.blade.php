@extends('theme::layouts.1col')

@section('title', 'Edit 3D Model')
@section('body-class', 'edit model3d')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-edit me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Edit 3D Model</h1>
    </div>
  </div>

  <form method="POST" action="{{ $formAction ?? '#' }}">
    @csrf
    <div class="row">
      <div class="col-md-8">
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-eye me-2"></i>Preview</div>
          <div class="card-body p-0" style="height:400px;background:#f8f9fa;"><div class="d-flex align-items-center justify-content-center h-100 text-muted"><i class="fas fa-cube fa-3x"></i></div></div>
        </div>
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-2"></i>Basic Information</div>
          <div class="card-body">
            <div class="mb-3"><label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Required</span></label><input type="text" class="form-control" id="title" name="title" value="{{ old('title', $model->model_title ?? '') }}"></div>
            <div class="mb-3"><label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Recommended</span></label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $model->description ?? '') }}</textarea></div>
            <div class="row">
              <div class="col-md-6 mb-3"><label class="form-label">Format <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" value="{{ strtoupper($model->format ?? '') }}" readonly></div>
              <div class="col-md-6 mb-3"><label class="form-label">File Size <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" value="{{ number_format(($model->file_size ?? 0)/1048576, 2) }} MB" readonly></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cog me-2"></i>Display Settings</div>
          <div class="card-body">
            <div class="mb-3"><label for="background_color" class="form-label">Background Colour <span class="badge bg-secondary ms-1">Optional</span></label><input type="color" class="form-control form-control-color" id="background_color" name="background_color" value="{{ old('background_color', $model->background_color ?? '#f8f9fa') }}"></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="auto_rotate" name="auto_rotate" {{ ($model->auto_rotate ?? false) ? 'checked' : '' }}><label class="form-check-label" for="auto_rotate">Auto-rotate <span class="badge bg-secondary ms-1">Optional</span></label></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="ar_enabled" name="ar_enabled" {{ ($model->ar_enabled ?? false) ? 'checked' : '' }}><label class="form-check-label" for="ar_enabled">Enable AR <span class="badge bg-secondary ms-1">Optional</span></label></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_public" name="is_public" {{ ($model->is_public ?? true) ? 'checked' : '' }}><label class="form-check-label" for="is_public">Public <span class="badge bg-secondary ms-1">Optional</span></label></div>
          </div>
        </div>
      </div>
    </div>
    <div class="d-flex gap-2"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form>
@endsection
