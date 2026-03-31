@extends('theme::layouts.1col')
@section('title', 'Donut — Extract ILM Fields')
@section('body-class', 'admin ai-services donut')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.donut.dashboard') }}">Donut</a></li><li class="breadcrumb-item active">Extract</li></ol></nav>
<h1><i class="fas fa-file-import me-2"></i>Extract ILM Fields</h1>

@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('admin.ai.donut.doExtract') }}" enctype="multipart/form-data">
      @csrf
      <div class="mb-3">
        <label class="form-label">Document Image <span class="badge bg-secondary ms-1">Required</span></label>
        <input type="file" name="file" class="form-control" accept="image/*,.pdf" required>
        <div class="form-text">Accepted: JPG, PNG, TIFF, PDF (max 20MB)</div>
      </div>
      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-magic me-1"></i>Extract ILM Fields</button>
      <a href="{{ route('admin.ai.donut.dashboard') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
