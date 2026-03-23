@extends('theme::layouts.2col')
@section('title', 'HTR Extraction Results')
@section('body-class', 'admin ai-services htr')
@section('sidebar')
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">Document Info</div>
  <div class="card-body">
    <p><strong>Type:</strong> {{ $results['doc_type'] ?? 'Unknown' }}</p>
    <p><strong>Processing:</strong> {{ number_format($results['processing_time'] ?? 0, 1) }}s</p>
    <p><strong>Fields found:</strong> {{ count($results['fields'] ?? []) }}</p>
  </div>
</div>

{{-- ILM Output --}}
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: white;"><i class="fas fa-tree me-1"></i>ILM Output</div>
  <div class="card-body">
    @php
      // Derive ILM from raw fields
      $fields = $results['fields'] ?? [];
      $year = '';
      $place = '';
      foreach ($fields as $f) {
          $name = strtolower($f['name'] ?? '');
          $val = $f['value'] ?? $f['raw'] ?? '';
          if (str_contains($name, 'date') && !$year) {
              preg_match('/\b(1[6-9]\d{2}|20[0-2]\d)\b/', $val, $m);
              if (!empty($m[1])) $year = $m[1];
          }
          if ((str_contains($name, 'place') || str_contains($name, 'district')) && !$place) {
              $place = $val;
          }
      }
      $docType = $results['doc_type'] ?? 'type_a';
      $rtMap = ['type_a' => ['Death Records','1000015'], 'type_b' => ['Church Records','1000004'], 'type_c' => ['Other Records','1000000']];
      $rt = $rtMap[$docType] ?? $rtMap['type_a'];
    @endphp
    <table class="table table-sm mb-0">
      <tr><td class="fw-bold">EVENT_YEAR_ORIG</td><td>{{ $year ?: '—' }}</td></tr>
      <tr><td class="fw-bold">FS_RECORD_TYPE</td><td>{{ $rt[0] }}</td></tr>
      <tr><td class="fw-bold">FS_RECORD_TYPE_ID</td><td>{{ $rt[1] }}</td></tr>
      <tr><td class="fw-bold">EVENT_PLACE_ORIG</td><td>{{ $place ? $place . ', South Africa' : 'South Africa' }}</td></tr>
      <tr><td class="fw-bold">non_genealogical</td><td><span class="badge bg-success">false</span></td></tr>
    </table>
  </div>
</div>
@endsection

@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Results</li></ol></nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-clipboard-check me-2"></i>Extraction Results</h1>

<div class="card mb-4">
  <div class="card-body table-responsive">
    <table class="table table-striped mb-0">
      <thead style="background: var(--ahg-primary); color: white;"><tr><th>Field</th><th>Extracted Text</th><th>Confidence</th><th>Location</th></tr></thead>
      <tbody>
      @forelse($results['fields'] ?? [] as $field)
        <tr>
          <td><strong>{{ str_replace('_', ' ', ucfirst($field['name'] ?? '')) }}</strong></td>
          <td>{{ $field['value'] ?? $field['raw'] ?? '' }}</td>
          <td>
            @php $c = ($field['confidence'] ?? 0) * 100; @endphp
            <span class="badge {{ $c > 80 ? 'bg-success' : ($c > 50 ? 'bg-warning' : 'bg-danger') }}">{{ number_format($c, 1) }}%</span>
          </td>
          <td>
            @if(!empty($field['bbox']))
              <span class="text-muted small">{{ $field['bbox']['x'] ?? '' }},{{ $field['bbox']['y'] ?? '' }} {{ $field['bbox']['width'] ?? $field['bbox']['w'] ?? '' }}×{{ $field['bbox']['height'] ?? $field['bbox']['h'] ?? '' }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-muted text-center">No fields extracted.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="d-flex gap-2">
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'json']) }}" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>JSON</a>
  <a href="{{ route('admin.ai.htr.download', [$jobId, 'ilm']) }}" class="btn atom-btn-outline-success"><i class="fas fa-tree me-1"></i>ILM</a>
  <a href="{{ route('admin.ai.htr.extract') }}" class="btn atom-btn-white"><i class="fas fa-redo me-1"></i>Extract Another</a>
</div>
@endsection
