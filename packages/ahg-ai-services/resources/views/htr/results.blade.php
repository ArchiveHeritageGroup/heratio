@extends('theme::layouts.2col')
@section('title', 'HTR Extraction Results')
@section('body-class', 'admin ai-services htr')
@section('sidebar')
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">Document Info</div>
  <div class="card-body">
    <p><strong>Type:</strong> {{ $results['doc_type'] ?? 'Unknown' }}</p>
    <p><strong>Era:</strong> {{ $results['era'] ?? 'Unknown' }}</p>
    <p><strong>Language:</strong> {{ $results['language'] ?? 'Unknown' }}</p>
    <p><strong>Overall Confidence:</strong>
      @php $conf = ($results['overall_confidence'] ?? 0) * 100; @endphp
      <span class="badge {{ $conf > 80 ? 'bg-success' : ($conf > 50 ? 'bg-warning' : 'bg-danger') }}">{{ number_format($conf, 1) }}%</span>
    </p>
  </div>
</div>
@endsection
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Results</li></ol></nav>
<h1><i class="fas fa-clipboard-check me-2"></i>Extraction Results</h1>

<div class="card mb-4">
  <div class="card-body table-responsive">
    <table class="table table-striped mb-0">
      <thead style="background: var(--ahg-primary); color: white;"><tr><th>Field</th><th>Raw Text</th><th>Validated</th><th>Confidence</th></tr></thead>
      <tbody>
      @foreach($results['fields'] ?? [] as $field)
        <tr>
          <td><strong>{{ str_replace('_', ' ', ucfirst($field['name'] ?? '')) }}</strong></td>
          <td>{{ $field['raw'] ?? '' }}</td>
          <td>{{ $field['validated'] ?? $field['raw'] ?? '' }}</td>
          <td>
            @php $c = ($field['confidence'] ?? 0) * 100; @endphp
            <span class="badge {{ $c > 80 ? 'bg-success' : ($c > 50 ? 'bg-warning' : 'bg-danger') }}">{{ number_format($c, 1) }}%</span>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="d-flex gap-2">
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'json']) }}" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>Download JSON</a>
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'csv']) }}" class="btn atom-btn-white"><i class="fas fa-file-csv me-1"></i>Download CSV</a>
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'gedcom']) }}" class="btn atom-btn-white"><i class="fas fa-sitemap me-1"></i>Download GEDCOM</a>
  <a href="{{ route('admin.ai.htr.extract') }}" class="btn atom-btn-white"><i class="fas fa-redo me-1"></i>Extract Another</a>
</div>
@endsection
