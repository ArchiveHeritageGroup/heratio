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

@if(!empty($results['ilm']))
<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-tree me-2"></i>FamilySearch ILM Output</div>
  <div class="card-body table-responsive">
    <table class="table table-sm table-bordered mb-0">
      <thead><tr><th>Field</th>@foreach($results['ilm'] as $idx => $ilm)<th>Entry {{ $idx + 1 }}</th>@endforeach</tr></thead>
      <tbody>
        @foreach(['EVENT_YEAR_ORIG','FS_RECORD_TYPE','FS_RECORD_TYPE_ID','EVENT_PLACE_ORIG','non_genealogical','non_genealogical_type_id','confidence','needs_review'] as $key)
          <tr>
            <td><strong>{{ $key }}</strong></td>
            @foreach($results['ilm'] as $ilm)
              <td>
                @if(is_bool($ilm[$key] ?? null))
                  <span class="badge {{ $ilm[$key] ? 'bg-warning' : 'bg-success' }}">{{ $ilm[$key] ? 'true' : 'false' }}</span>
                @elseif(is_null($ilm[$key] ?? null))
                  <span class="text-muted">null</span>
                @elseif($key === 'confidence')
                  <span class="badge {{ ($ilm[$key] ?? 0) >= 0.7 ? 'bg-success' : 'bg-warning' }}">{{ number_format(($ilm[$key] ?? 0) * 100, 1) }}%</span>
                @else
                  {{ $ilm[$key] ?? '' }}
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

<div class="d-flex gap-2">
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'json']) }}" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>JSON</a>
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'ilm']) }}" class="btn atom-btn-outline-success"><i class="fas fa-tree me-1"></i>ILM (FamilySearch)</a>
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'ilm-csv']) }}" class="btn atom-btn-white"><i class="fas fa-file-csv me-1"></i>ILM CSV</a>
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'csv']) }}" class="btn atom-btn-white"><i class="fas fa-file-csv me-1"></i>CSV</a>
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'gedcom']) }}" class="btn atom-btn-white"><i class="fas fa-sitemap me-1"></i>GEDCOM</a>
  <a href="{{ route('admin.ai.htr.extract') }}" class="btn atom-btn-white"><i class="fas fa-redo me-1"></i>Extract Another</a>
</div>
@endsection
