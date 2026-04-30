@extends('ahg-theme-b5::layout')

@section('title', 'Export Rights Data')

@section('content')
<div class="container-fluid mt-3">
  @include('ahg-extended-rights::admin._sidebar')

  <h1><i class="fas fa-file-export"></i> {{ __('Export Rights Data') }}</h1>

  {{-- Single Object Export (cloned from PSIS exportSuccess) --}}
  <div class="row">
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Export Single Object') }}</h5></div>
        <div class="card-body">
          <form method="GET" action="{{ route('ext-rights-admin.export-csv') }}">
            <div class="mb-3">
              <label for="single_id" class="form-label">{{ __('Select object') }}</label>
              <select name="object_id" id="single_id" class="form-select">
                <option value="">{{ __('-- Select an object --') }}</option>
                @foreach($topLevelRecords ?? [] as $record)
                  <option value="{{ $record->id }}">
                    {{ $record->title ?? 'Untitled' }}
                    @if(!empty($record->identifier)) [{{ $record->identifier }}]@endif
                  </option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Format') }}</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="format" id="format_csv" value="csv" checked>
                <label class="form-check-label" for="format_csv">CSV</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="format" id="format_jsonld" value="json-ld">
                <label class="form-check-label" for="format_jsonld">JSON-LD</label>
              </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i>{{ __('Export') }}</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Bulk Export') }}</h5></div>
        <div class="card-body">
          <form method="GET" action="{{ route('ext-rights-admin.export-csv') }}">
            <input type="hidden" name="format" value="csv">
            <div class="mb-3">
              <label class="form-label">{{ __('Export Type') }}</label>
              <select name="type" class="form-select">
                <option value="all">{{ __('All Rights Records') }}</option>
                <option value="rights_statement">{{ __('Rights Statements') }}</option>
                <option value="cc_license">{{ __('CC Licenses') }}</option>
                <option value="tk_label">{{ __('TK Labels') }}</option>
                <option value="embargo">{{ __('Active Embargoes') }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Repository (optional)') }}</label>
              <select name="repository" class="form-select">
                <option value="">{{ __('All') }}</option>
                @foreach($repositories ?? [] as $repo)
                  <option value="{{ $repo->id }}">{{ e($repo->name ?? '') }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i>{{ __('Export as CSV') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Statistics card (cloned from PSIS exportSuccess) --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ __('Export Statistics') }}</h5></div>
    <div class="card-body">
      <p>{{ __('Total objects with extended rights') }}: <strong>{{ number_format($stats['total_with_rights'] ?? ($stats['total_rights'] ?? 0)) }}</strong></p>
      <p>{{ __('Objects with inherited rights') }}: <strong>{{ number_format($stats['inherited_rights'] ?? 0) }}</strong></p>
      <hr>
      <table class="table table-sm mb-0">
        <tr><td>{{ __('Rights Statements') }}</td><td>{{ number_format($stats['rights_statements'] ?? 0) }}</td></tr>
        <tr><td>{{ __('CC Licenses') }}</td><td>{{ number_format($stats['cc_licenses'] ?? 0) }}</td></tr>
        <tr><td>{{ __('TK Labels') }}</td><td>{{ number_format($stats['tk_labels'] ?? 0) }}</td></tr>
        <tr><td>{{ __('Active Embargoes') }}</td><td>{{ number_format($stats['active_embargoes'] ?? 0) }}</td></tr>
        <tr><td>{{ __('Orphan Works') }}</td><td>{{ number_format($stats['orphan_works'] ?? 0) }}</td></tr>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <a href="{{ route('ext-rights-admin.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}
    </a>
  </div>
</div>
@endsection
