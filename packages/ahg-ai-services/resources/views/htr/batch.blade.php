@extends('theme::layouts.1col')
@section('title', 'Batch HTR Processing')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Batch</li></ol></nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-layer-group me-2"></i>Batch HTR Processing</h1>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-4">
  <div class="card-body">
    <form method="POST" action="{{ route('admin.ai.htr.doBatch') }}" enctype="multipart/form-data">
      @csrf
      <div class="mb-3">
        <label class="form-label">Upload Files <span class="badge bg-secondary ms-1">Required</span></label>
        <input type="file" name="files[]" class="form-control" multiple accept="image/*,.pdf" required>
        <div class="form-text">Select multiple images or PDFs for batch processing.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Output Format <span class="badge bg-secondary ms-1">Optional</span></label>
        <select name="format" class="form-select" style="max-width:200px">
          <option value="csv">CSV</option>
          <option value="json">JSON</option>
          <option value="gedcom">GEDCOM</option>
        </select>
      </div>
      <div class="progress mb-3 d-none" id="batch-progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div>
      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-play me-1"></i>Start Batch</button>
      <a href="{{ route('admin.ai.htr.dashboard') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>

@if(isset($batchResults))
<div class="card">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">Batch Results</div>
  <div class="card-body table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>File</th><th>Type</th><th>Fields</th><th>Confidence</th><th>Actions</th></tr></thead>
      <tbody>
      @foreach($batchResults['results'] ?? [] as $result)
        <tr>
          <td>{{ $result['filename'] ?? '' }}</td>
          <td>{{ $result['doc_type'] ?? '' }}</td>
          <td>{{ count($result['fields'] ?? []) }}</td>
          <td>
            @php $c = ($result['overall_confidence'] ?? 0) * 100; @endphp
            <span class="badge {{ $c > 80 ? 'bg-success' : ($c > 50 ? 'bg-warning' : 'bg-danger') }}">{{ number_format($c, 1) }}%</span>
          </td>
          <td><a href="{{ route('admin.ai.htr.results', $result['job_id'] ?? '') }}" class="btn btn-sm atom-btn-white">View</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  @if(isset($batchResults['job_id']))
  <div class="card-footer">
    <a href="{{ route('admin.ai.htr.download', [$batchResults['job_id'], 'csv']) }}" class="btn atom-btn-outline-success"><i class="fas fa-download me-1"></i>Download All</a>
  </div>
  @endif
</div>
@endif
@endsection
