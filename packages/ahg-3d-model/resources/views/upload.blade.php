@extends('theme::layouts.1col')

@section('title', 'Upload 3D Model')
@section('body-class', 'upload model3d')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-upload me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Upload 3D Model</h1>
    </div>
  </div>

  <p class="text-muted">Add a 3D model to: <strong>{{ $object->title ?? 'Object' }}</strong></p>
  <div class="card"><div class="card-body">
    <form method="POST" action="{{ $formAction ?? '#' }}" enctype="multipart/form-data">@csrf
      <div class="mb-4"><label class="form-label"><strong>3D Model File</strong> <span class="text-danger">*</span> <span class="badge bg-secondary ms-1">Required</span></label><input type="file" class="form-control" name="model_file" accept=".glb,.gltf,.obj,.stl,.ply,.usdz" required><div class="form-text">Supported: GLB, GLTF, OBJ, STL, PLY, USDZ</div></div>
      <div class="mb-3"><label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Recommended</span></label><input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}"></div>
      <div class="mb-3"><label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea></div>
      <div class="d-flex gap-2"><button type="submit" class="btn atom-btn-white"><i class="fas fa-upload me-1"></i> Upload</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
    </form>
  </div></div>
@endsection
