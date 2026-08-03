{{-- CSV import of contexts + relationships - #1428 Phase 4b --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-1">{{ __('Import contexts') }}</h1>
      <div class="text-muted small">{{ $site->title ?: __('Untitled site') }} &middot; {{ $site->site_number }}</div>
    </div>
    <a href="{{ route('archaeology.contexts', $site->id) }}" class="btn btn-outline-secondary btn-sm">&larr; {{ __('Stratigraphy') }}</a>
  </div>

  @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
  @if($errors->any())
    <div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  {{-- Result of a preview or a completed import --}}
  @if($summary)
    @php
      $isPreview = $summary['preview'] ?? false;
      $hasErrors = ! empty($summary['errors']);
    @endphp
    <div class="card mb-3 border-{{ $hasErrors ? 'danger' : ($isPreview ? 'info' : 'success') }}">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ $isPreview ? __('Preview (nothing saved yet)') : __('Import result') }}</span>
        <span class="text-muted small">{{ $summary['rows'] }} {{ __('data rows read') }}</span>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-4 mb-2">
          <div><span class="h5 mb-0 text-success">{{ $summary['created'] }}</span> <span class="text-muted small">{{ __('contexts to create') }}</span></div>
          <div><span class="h5 mb-0 text-primary">{{ $summary['updated'] }}</span> <span class="text-muted small">{{ __('contexts to update') }}</span></div>
          <div><span class="h5 mb-0 text-info">{{ $summary['relationships_added'] }}</span> <span class="text-muted small">{{ __('relationships') }}</span></div>
        </div>

        @if($hasErrors)
          <div class="alert alert-danger py-2 mb-2"><strong>{{ __('Errors:') }}</strong>
            <ul class="mb-0">@foreach($summary['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif

        @if(! empty($summary['warnings']))
          <details class="mb-0" open>
            <summary class="small text-warning-emphasis">{{ count($summary['warnings']) }} {{ __('warning(s)') }}</summary>
            <ul class="small mb-0 mt-1">@foreach($summary['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
          </details>
        @elseif(! $hasErrors)
          <p class="text-muted small mb-0">{{ __('No warnings.') }}</p>
        @endif

        @if($isPreview && ! $hasErrors && ($summary['created'] + $summary['updated']) > 0)
          <hr>
          <p class="small mb-2">{{ __('This looks right? Re-select the same file below and tick "Save changes" to commit.') }}</p>
        @endif
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header">{{ __('Upload CSV') }}</div>
    <div class="card-body">
      <form method="post" action="{{ route('archaeology.contexts.import.run', $site->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">{{ __('CSV file') }} <span class="text-danger">*</span></label>
          <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
          <div class="form-text">{{ __('One row per context. Must include a "context_number" column.') }}</div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="commit" value="1" id="commit">
          <label class="form-check-label" for="commit">
            {{ __('Save changes') }} <span class="text-muted small">{{ __('(leave unticked to preview first)') }}</span>
          </label>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
        <a href="{{ route('archaeology.contexts.import.template', $site->id) }}" class="btn btn-outline-secondary ms-2">{{ __('Download template') }}</a>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">{{ __('CSV format') }}</div>
    <div class="card-body">
      <p class="small mb-2">{{ __('The first row is the header. Contexts are matched to existing ones by number within this site, so a re-import updates in place. Relationship columns take one or more other context numbers (comma or semicolon separated); the reciprocal edge is created automatically and stratigraphic loops are refused.') }}</p>
      <div class="row">
        <div class="col-md-6">
          <div class="fw-semibold small mb-1">{{ __('Context columns') }}</div>
          <ul class="small mb-3">
            @foreach($fields as $f)
              <li><code>{{ $f }}</code>@if($f === 'context_number') <span class="text-danger">{{ __('(required)') }}</span>@endif</li>
            @endforeach
          </ul>
        </div>
        <div class="col-md-6">
          <div class="fw-semibold small mb-1">{{ __('Relationship columns (values = context numbers)') }}</div>
          <ul class="small mb-0">
            @foreach($relFields as $f)<li><code>{{ $f }}</code></li>@endforeach
          </ul>
        </div>
      </div>
      <p class="small text-muted mt-2 mb-0">{{ __('context_type and phase are matched by name against the site\'s vocabularies; an unknown value is left blank and reported as a warning.') }}</p>
    </div>
  </div>

</div>
@endsection
