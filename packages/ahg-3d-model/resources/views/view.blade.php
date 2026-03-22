@extends('theme::layouts.1col')

@section('title', '3D Model')
@section('body-class', 'show model3d')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cube me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">3D Model</h1>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="card mb-4"><div class="card-body p-0" style="height:500px;background:#f8f9fa;"><div class="d-flex align-items-center justify-content-center h-100 text-muted text-center"><div><i class="fas fa-cube fa-4x mb-2"></i><p>{{ $model->model_title ?? $model->original_filename ?? '3D Model' }}</p></div></div></div></div>
    </div>
    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-2"></i>Details</div>
        <div class="card-body"><dl class="row mb-0">
          <dt class="col-sm-5">Title</dt><dd class="col-sm-7">{{ $model->model_title ?? '-' }}</dd>
          <dt class="col-sm-5">Format</dt><dd class="col-sm-7"><span class="badge bg-secondary">{{ strtoupper($model->format ?? '') }}</span></dd>
          <dt class="col-sm-5">Size</dt><dd class="col-sm-7">{{ number_format(($model->file_size ?? 0)/1048576, 2) }} MB</dd>
          <dt class="col-sm-5">Status</dt><dd class="col-sm-7">@if($model->is_public ?? false)<span class="badge bg-success">Public</span>@else<span class="badge bg-warning text-dark">Hidden</span>@endif</dd>
          <dt class="col-sm-5">AR Ready</dt><dd class="col-sm-7">{{ ($model->ar_enabled ?? false) ? 'Yes' : 'No' }}</dd>
        </dl></div>
      </div>
      <div class="d-flex gap-2"><a href="{{ route('admin.3d-models.edit', $model->id) }}" class="btn atom-btn-white"><i class="fas fa-edit me-1"></i>Edit</a><a href="{{ route('admin.3d-models.browse') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back</a></div>
    </div>
  </div>
@endsection
