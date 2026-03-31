@extends('theme::layouts.1col')
@section('title', 'Donut — Extraction Results')
@section('body-class', 'admin ai-services donut')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.donut.dashboard') }}">Donut</a></li><li class="breadcrumb-item active">Results</li></ol></nav>
<h1><i class="fas fa-check-circle me-2"></i>Extraction Results</h1>

<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">
    <i class="fas fa-file me-2"></i>{{ $filename }}
    <span class="float-end badge {{ ($result['confidence'] ?? 0) >= 0.7 ? 'bg-success' : 'bg-warning text-dark' }}">
      Confidence: {{ number_format(($result['confidence'] ?? 0) * 100, 1) }}%
    </span>
  </div>
  <div class="card-body">
    <table class="table table-bordered">
      <tbody>
        <tr>
          <th style="width:30%">FS_RECORD_TYPE</th>
          <td>{{ $result['FS_RECORD_TYPE'] ?? '' }}</td>
        </tr>
        <tr>
          <th>FS_RECORD_TYPE_ID</th>
          <td><code>{{ $result['FS_RECORD_TYPE_ID'] ?? '' }}</code></td>
        </tr>
        <tr>
          <th>EVENT_YEAR_ORIG</th>
          <td>{{ $result['EVENT_YEAR_ORIG'] ?? '' }}</td>
        </tr>
        <tr>
          <th>EVENT_PLACE_ORIG</th>
          <td>{{ $result['EVENT_PLACE_ORIG'] ?? '' }}</td>
        </tr>
        <tr>
          <th>Non-Genealogical</th>
          <td>
            @if($result['non_genealogical'] ?? false)
              <span class="badge bg-secondary">Yes</span>
            @else
              <span class="badge bg-success">No (genealogical)</span>
            @endif
          </td>
        </tr>
        <tr>
          <th>Needs Review</th>
          <td>
            @if($result['needs_review'] ?? true)
              <span class="badge bg-warning text-dark">Yes</span>
            @else
              <span class="badge bg-success">No</span>
            @endif
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <a href="{{ route('admin.ai.donut.download', $jobId) }}" class="btn atom-btn-outline-success btn-sm"><i class="fas fa-download me-1"></i>Download JSON</a>
    <a href="{{ route('admin.ai.donut.extract') }}" class="btn atom-btn-white btn-sm ms-2"><i class="fas fa-redo me-1"></i>Extract Another</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="fas fa-code me-2"></i>Raw JSON</div>
  <div class="card-body">
    <pre class="mb-0" style="max-height:300px; overflow:auto;"><code>{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
  </div>
</div>
@endsection
