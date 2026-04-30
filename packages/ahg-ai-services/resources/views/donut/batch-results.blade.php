@extends('theme::layouts.1col')
@section('title', 'Donut — Batch Results')
@section('body-class', 'admin ai-services donut')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.donut.dashboard') }}">Donut</a></li><li class="breadcrumb-item active">Batch Results</li></ol></nav>
<h1><i class="fas fa-check-circle me-2"></i>Batch Results ({{ $count }} images)</h1>

@if($jobId)
<div class="mb-3">
  <a href="{{ route('admin.ai.donut.download', $jobId) }}" class="btn atom-btn-outline-success btn-sm"><i class="fas fa-download me-1"></i>{{ __('Download All (JSON)') }}</a>
  <a href="{{ route('admin.ai.donut.batch') }}" class="btn atom-btn-white btn-sm ms-2"><i class="fas fa-redo me-1"></i>{{ __('New Batch') }}</a>
</div>
@endif

<div class="table-responsive">
  <table class="table table-bordered table-hover">
    <thead>
      <tr>
        <th>{{ __('File') }}</th>
        <th>{{ __('Record Type') }}</th>
        <th>{{ __('Type ID') }}</th>
        <th>{{ __('Event Year') }}</th>
        <th>{{ __('Event Place') }}</th>
        <th>{{ __('Non-Genealogical') }}</th>
        <th>{{ __('Confidence') }}</th>
        <th>{{ __('Review') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($results as $r)
      <tr>
        <td>{{ $r['filename'] ?? '—' }}</td>
        <td>{{ $r['FS_RECORD_TYPE'] ?? '' }}</td>
        <td><code>{{ $r['FS_RECORD_TYPE_ID'] ?? '' }}</code></td>
        <td>{{ $r['EVENT_YEAR_ORIG'] ?? '' }}</td>
        <td>{{ $r['EVENT_PLACE_ORIG'] ?? '' }}</td>
        <td>
          @if($r['non_genealogical'] ?? false)
            <span class="badge bg-secondary">{{ __('Yes') }}</span>
          @else
            <span class="badge bg-success">No</span>
          @endif
        </td>
        <td>
          <span class="badge {{ ($r['confidence'] ?? 0) >= 0.7 ? 'bg-success' : 'bg-warning text-dark' }}">
            {{ number_format(($r['confidence'] ?? 0) * 100, 1) }}%
          </span>
        </td>
        <td>
          @if($r['needs_review'] ?? true)
            <span class="badge bg-warning text-dark">{{ __('Yes') }}</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
