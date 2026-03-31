@extends('theme::layouts.1col')
@section('title', 'Donut — Document Understanding')
@section('body-class', 'admin ai-services donut')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item active">Donut</li></ol></nav>
<h1><i class="fas fa-file-invoice me-2"></i>Document Understanding (Donut)</h1>
<p class="text-muted mb-4">End-to-end document image understanding for FamilySearch ILM field extraction. Complements HTR by recognising form structure and typed metadata.</p>

@if($health)
<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-server me-2"></i>Service Status</div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-3"><strong>Status:</strong> <span class="badge bg-success">Online</span></div>
      <div class="col-md-3"><strong>Version:</strong> {{ $health['version'] ?? '0.1.0' }}</div>
      <div class="col-md-3"><strong>Model Ready:</strong>
        @if($health['model_ready'] ?? false)
          <span class="badge bg-success">Yes</span>
        @else
          <span class="badge bg-warning text-dark">Not trained yet</span>
        @endif
      </div>
      <div class="col-md-3"><strong>Annotations:</strong> {{ $health['total_annotations'] ?? 0 }}</div>
    </div>
  </div>
</div>
@else
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Donut service is offline. Start it on port 5008.</div>
@endif

<div class="row">
  <div class="col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-file-import me-2"></i>Extract ILM Fields</div>
      <div class="card-body"><p>Upload a document image and extract FamilySearch ILM fields (record type, event year, event place) using Donut.</p></div>
      <div class="card-footer"><a href="{{ route('admin.ai.donut.extract') }}" class="btn atom-btn-white w-100"><i class="fas fa-upload me-1"></i>Extract</a></div>
    </div>
  </div>
  <div class="col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-layer-group me-2"></i>Batch Extract</div>
      <div class="card-body"><p>Process multiple document images at once for bulk ILM field extraction.</p></div>
      <div class="card-footer"><a href="{{ route('admin.ai.donut.batch') }}" class="btn atom-btn-white w-100"><i class="fas fa-tasks me-1"></i>Batch</a></div>
    </div>
  </div>
  <div class="col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-graduation-cap me-2"></i>Model Training</div>
      <div class="card-body">
        <p>Fine-tune Donut on your annotated training data.</p>
        @if($training)
          <table class="table table-sm mb-0">
            <tr><td>Type A</td><td class="text-end">{{ $training['annotations']['type_a'] ?? 0 }}</td></tr>
            <tr><td>Type B</td><td class="text-end">{{ $training['annotations']['type_b'] ?? 0 }}</td></tr>
            <tr><td>Type C</td><td class="text-end">{{ $training['annotations']['type_c'] ?? 0 }}</td></tr>
            <tr class="fw-bold"><td>Total</td><td class="text-end">{{ $training['total'] ?? 0 }}</td></tr>
          </table>
          @if($training['model_exists'] ?? false)
            <span class="badge bg-success mt-2">Model trained</span>
          @endif
        @endif
      </div>
      <div class="card-footer">
        <form method="POST" action="{{ route('admin.ai.donut.startTraining') }}" class="d-inline">
          @csrf
          <button type="submit" class="btn atom-btn-white w-100" {{ ($training['training']['running'] ?? false) ? 'disabled' : '' }}>
            <i class="fas fa-brain me-1"></i>{{ ($training['training']['running'] ?? false) ? 'Training...' : 'Start Training' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-info-circle me-2"></i>How It Works</div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <h5>Donut (Document Understanding Transformer)</h5>
        <ul>
          <li>End-to-end: image in, structured JSON out</li>
          <li>No separate OCR step — the model reads and understands form layout</li>
          <li>Fine-tuned on your {{ $health['total_annotations'] ?? 0 }} annotated SA vital records</li>
          <li>Extracts: <code>FS_RECORD_TYPE</code>, <code>EVENT_YEAR_ORIG</code>, <code>EVENT_PLACE_ORIG</code></li>
        </ul>
      </div>
      <div class="col-md-6">
        <h5>Combined Pipeline (Donut + HTR)</h5>
        <ol>
          <li><strong>Donut</strong> classifies document type and extracts typed metadata fields</li>
          <li><strong>TrOCR (HTR)</strong> reads handwritten genealogical content</li>
          <li><strong>ILM Formatter</strong> combines both into FamilySearch ILM output</li>
        </ol>
        <p class="text-muted">Use <a href="{{ route('admin.ai.htr.extract') }}">HTR Extract</a> for the full combined pipeline.</p>
      </div>
    </div>
  </div>
</div>
@endsection
