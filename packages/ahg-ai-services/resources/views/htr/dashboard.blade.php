@extends('theme::layouts.1col')
@section('title', 'Vital Records HTR')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item active">Vital Records HTR</li></ol></nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-file-alt me-2"></i>Vital Records HTR</h1>

@if($health)
<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-server me-2"></i>Service Status</div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-3"><strong>GPU:</strong> {{ $health['gpu_name'] ?? 'N/A' }}</div>
      <div class="col-md-3"><strong>VRAM:</strong> {{ $health['vram'] ?? 'N/A' }}</div>
      <div class="col-md-3"><strong>Model:</strong> <span class="badge bg-success">{{ !empty($health['model_loaded']) ? 'Loaded' : 'Not loaded' }}</span></div>
      <div class="col-md-3"><strong>Version:</strong> {{ $health['version'] ?? '1.0.0' }}</div>
    </div>
  </div>
</div>
@else
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>HTR service is offline.</div>
@endif

<div class="row">
  <div class="col-md-3 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-file-import me-2"></i>Extract Single</div>
      <div class="card-body"><p>Upload a single vital record image or PDF for HTR extraction.</p></div>
      <div class="card-footer"><a href="{{ route('admin.ai.htr.extract') }}" class="btn atom-btn-white w-100"><i class="fas fa-upload me-1"></i>Extract</a></div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-layer-group me-2"></i>Batch Process</div>
      <div class="card-body"><p>Process multiple vital records at once for bulk extraction.</p></div>
      <div class="card-footer"><a href="{{ route('admin.ai.htr.batch') }}" class="btn atom-btn-white w-100"><i class="fas fa-tasks me-1"></i>Batch</a></div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-database me-2"></i>Training Data Sources</div>
      <div class="card-body"><p>Download SA vital record images from FamilySearch and Internet Archive.</p></div>
      <div class="card-footer"><a href="{{ route('admin.ai.htr.sources') }}" class="btn atom-btn-white w-100"><i class="fas fa-download me-1"></i>Sources</a></div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-pen-square me-2"></i>Annotate for Training</div>
      <div class="card-body"><p>Annotate document images to build training data for model fine-tuning.</p></div>
      <div class="card-footer"><a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-white w-100"><i class="fas fa-pen me-1"></i>Annotate</a></div>
    </div>
  </div>
  <div class="col-md-3 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-graduation-cap me-2"></i>Model Training</div>
      <div class="card-body"><p>Fine-tune TrOCR on SA historical vital records for improved accuracy.</p></div>
      <div class="card-footer"><a href="{{ route('admin.ai.htr.training') }}" class="btn atom-btn-white w-100"><i class="fas fa-brain me-1"></i>Training</a></div>
    </div>
  </div>
</div>
@endsection
