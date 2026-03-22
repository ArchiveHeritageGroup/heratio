@extends('theme::layouts.1col')
@section('title', 'TIFF/PDF Merge Tool')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-file-pdf me-2"></i>TIFF/PDF Merge Tool</h1>
      <a href="{{ route('preservation.tiffpdfmerge.browse') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-list me-1"></i>Browse Jobs</a>
    </div>
    <p class="text-muted">Merge multiple TIFF or image files into a single PDF or multi-page TIFF document.</p>

    <form method="post" action="{{ Route::has('preservation.tiffpdfmerge.store') ? route('preservation.tiffpdfmerge.store') : '#' }}" enctype="multipart/form-data">
      @csrf
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Merge Configuration</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Output Format <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="output_format" class="form-select">
                <option value="pdf">PDF</option>
                <option value="tiff">Multi-page TIFF</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Output Filename <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="output_filename" class="form-control" placeholder="merged-output">
            </div>
            <div class="col-12 mb-3">
              <label class="form-label">Source Files <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="file" name="files[]" class="form-control" multiple accept=".tif,.tiff,.pdf,.jpg,.jpeg,.png">
              <div class="form-text">Select TIFF, PDF, or image files to merge. Hold Ctrl/Cmd to select multiple.</div>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn atom-btn-white"><i class="fas fa-cogs me-1"></i>Start Merge</button>
    </form>
  </div>
</div>
@endsection