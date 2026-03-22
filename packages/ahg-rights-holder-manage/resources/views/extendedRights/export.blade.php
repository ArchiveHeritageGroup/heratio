@extends('theme::layouts.1col')

@section('title', 'Export Rights Data')
@section('body-class', 'extended-rights export')

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-download me-2"></i>Export Rights Data</h1>
@endsection

@section('content')
<div class="row">
  <div class="col-md-6">
    <div class="card mb-4">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <h5 class="mb-0">Export Single Object</h5>
      </div>
      <div class="card-body">
        <form method="get" action="{{ route('extended-rights.export') }}">
          <div class="mb-3">
            <label for="single_id" class="form-label">Search and select object <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="id" id="single_id" class="form-select">
              <option value="">-- Select an object --</option>
              @foreach($topLevelRecords ?? [] as $record)
                <option value="{{ $record->id }}">{{ $record->title ?? 'Untitled' }}@if(!empty($record->identifier)) [{{ $record->identifier }}]@endif</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Format <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="format" id="format_csv" value="csv" checked>
              <label class="form-check-label" for="format_csv">CSV <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="format" id="format_jsonld" value="json-ld">
              <label class="form-check-label" for="format_jsonld">JSON-LD <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>Export</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-4">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <h5 class="mb-0">Bulk Export</h5>
      </div>
      <div class="card-body">
        <form method="get" action="{{ route('extended-rights.export') }}">
          <input type="hidden" name="format" value="csv">
          <div class="mb-3">
            <label for="bulk_select" class="form-label">Search and select multiple objects <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="ids[]" id="bulk_select" multiple class="form-select">
              @foreach($topLevelRecords ?? [] as $record)
                <option value="{{ $record->id }}">{{ $record->title ?? 'Untitled' }}@if(!empty($record->identifier)) [{{ $record->identifier }}]@endif</option>
              @endforeach
            </select>
            <small class="text-muted">Leave empty to export all objects with rights.</small>
          </div>
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>Export as CSV</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Export Statistics</h5>
  </div>
  <div class="card-body">
    @php $totalWithRights = $stats['total_with_rights'] ?? 0; $inheritedRights = $stats['inherited_rights'] ?? 0; @endphp
    <p>Total objects with extended rights: <strong>{{ number_format($totalWithRights) }}</strong></p>
    <p>Objects with inherited rights: <strong>{{ number_format($inheritedRights) }}</strong></p>
  </div>
</div>

<div class="mt-3">
  <a href="{{ route('extended-rights.dashboard') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
</div>
@endsection
