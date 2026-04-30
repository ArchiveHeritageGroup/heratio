@extends('theme::layouts.1col')
@section('title', 'Donut — Batch Extract')
@section('body-class', 'admin ai-services donut')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.donut.dashboard') }}">Donut</a></li><li class="breadcrumb-item active">Batch</li></ol></nav>
<h1><i class="fas fa-layer-group me-2"></i>{{ __('Batch Extract ILM Fields') }}</h1>

@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('admin.ai.donut.doBatch') }}" enctype="multipart/form-data">
      @csrf
      <div class="mb-3">
        <label class="form-label">Document Images <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label>
        <input type="file" name="files[]" class="form-control" accept="image/*" multiple required>
        <div class="form-text">Select up to 50 images. Accepted: JPG, PNG, TIFF (max 20MB each)</div>
      </div>
      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-magic me-1"></i>{{ __('Extract All') }}</button>
      <a href="{{ route('admin.ai.donut.dashboard') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
