@extends('theme::layouts.1col')
@section('title', __('Print Labels'))
@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="mb-0"><i class="fas fa-tags me-2"></i>{{ __('Print Labels') }}</h1>
    <div class="d-flex gap-2">
      <a href="{{ route('library.labels.templates') }}" class="btn atom-btn-white"><i class="fas fa-table-cells me-1"></i>{{ __('Stationery Templates') }}</a>
      <a href="{{ route('library.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
  </div>

  @if(! $ready || $formats === [])
    <div class="alert alert-warning">{{ __('Label printing is not set up on this installation yet. Run the database migrations.') }}</div>
  @else
    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('library.labels.preview') }}" target="_blank" class="card card-body">
      @csrf
      <div class="row g-3">
        <div class="col-md-6">
          <label for="template_id" class="form-label">{{ __('Stationery') }}</label>
          <select name="template_id" id="template_id" class="form-select" required>
            @foreach($templates as $t)
              <option value="{{ $t->id }}" @selected(old('template_id') == $t->id)>{{ $t->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label for="format" class="form-label">{{ __('Label') }}</label>
          <select name="format" id="format" class="form-select" required>
            @foreach($formats as $code => $label)
              <option value="{{ $code }}" @selected(old('format') === $code)>{{ __($label) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label for="identifiers" class="form-label">{{ __('Selected copies') }}</label>
          <textarea name="identifiers" id="identifiers" rows="7" class="form-control font-monospace" aria-describedby="identifiers-help">{{ old('identifiers') }}</textarea>
          <div id="identifiers-help" class="form-text">{{ __('Scan or paste barcodes or accession numbers, one per line.') }}</div>
        </div>
        <div class="col-md-6">
          <label for="accession" class="form-label">{{ __('Whole accession') }}</label>
          <input type="text" name="accession" id="accession" maxlength="51" class="form-control" value="{{ old('accession') }}" aria-describedby="accession-help">
          <div id="accession-help" class="form-text mb-3">{{ __('Exact accession number. End with * to take every copy numbered under it, such as 2026/014-*.') }}</div>
          <label for="skip" class="form-label">{{ __('Positions already used on the first sheet') }}</label>
          <input type="number" name="skip" id="skip" min="0" class="form-control mb-3" value="{{ old('skip', 0) }}">
          <div class="row g-2">
            <div class="col-6">
              <label for="nudge_x" class="form-label">{{ __('Shift right (mm)') }}</label>
              <input type="number" step="0.1" min="-10" max="10" name="nudge_x" id="nudge_x" class="form-control" value="{{ old('nudge_x', 0) }}">
            </div>
            <div class="col-6">
              <label for="nudge_y" class="form-label">{{ __('Shift down (mm)') }}</label>
              <input type="number" step="0.1" min="-10" max="10" name="nudge_y" id="nudge_y" class="form-control" value="{{ old('nudge_y', 0) }}">
            </div>
          </div>
          <div class="form-check mt-3">
            <input type="hidden" name="outlines" value="0">
            <input class="form-check-input" type="checkbox" name="outlines" id="outlines" value="1" @checked(old('outlines'))>
            <label class="form-check-label" for="outlines">{{ __('Print label outlines (for a test on plain paper)') }}</label>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-eye me-1"></i>{{ __('Preview at true scale') }}</button>
        <span class="form-text ms-2">{{ __('Up to :n labels per run.', ['n' => number_format($maxLabels)]) }}</span>
      </div>
    </form>
  @endif
</div>
@endsection
