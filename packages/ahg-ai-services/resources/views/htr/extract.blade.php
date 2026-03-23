@extends('theme::layouts.1col')
@section('title', 'Extract Vital Record')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Extract</li></ol></nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-file-import me-2"></i>Extract Vital Record</h1>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('admin.ai.htr.doExtract') }}" enctype="multipart/form-data">
      @csrf
      <div class="mb-3">
        <label class="form-label">Document Image/PDF <span class="badge bg-secondary ms-1">Required</span></label>
        <input type="file" name="file" class="form-control" accept="image/*,.pdf" required>
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Document Type <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="doc_type" class="form-select">
            <option value="auto">Auto-detect</option>
            <option value="type_a">Type A — Government Form (Death Certificate)</option>
            <option value="type_b">Type B — Church/Civil Register</option>
            <option value="type_c">Type C — Narrative Document</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Era Hint <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="era_hint" class="form-select">
            <option value="auto">Auto-detect</option>
            <option value="voc">VOC (pre-1806)</option>
            <option value="colonial">Colonial (1806-1910)</option>
            <option value="union">Union (1910-1961)</option>
            <option value="modern">Modern (post-1961)</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Output Format <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="formats[]" value="ilm" id="fmt-ilm" checked><label class="form-check-label" for="fmt-ilm"><strong>ILM (FamilySearch)</strong></label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="formats[]" value="json" id="fmt-json" checked><label class="form-check-label" for="fmt-json">JSON</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="formats[]" value="csv" id="fmt-csv" checked><label class="form-check-label" for="fmt-csv">CSV</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="formats[]" value="gedcom" id="fmt-gedcom" checked><label class="form-check-label" for="fmt-gedcom">GEDCOM</label></div>
        </div>
      </div>
      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-magic me-1"></i>Extract</button>
      <a href="{{ route('admin.ai.htr.dashboard') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
