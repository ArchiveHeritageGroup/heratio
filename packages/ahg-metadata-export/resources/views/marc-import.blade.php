{{--
  MARCXML import - upload, preview, commit (#663 Phase 2).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', __('MARCXML Import'))

@section('content')
<div class="d-flex align-items-center mb-3">
  <h1 class="mb-0"><i class="bi bi-file-earmark-arrow-up"></i> {{ __('MARCXML Import') }}</h1>
  <a href="{{ route('ahgmetadataexport.index') }}" class="btn btn-outline-secondary btn-sm ms-auto">
    <i class="bi bi-arrow-left"></i> {{ __('Back to Metadata Export') }}
  </a>
</div>

<div class="alert alert-info">
  <i class="bi bi-info-circle"></i>
  {{ __('Upload a MARC21 XML file (one or more <record> elements). Heratio validates against the LoC MARC21slim schema, shows a preview, then commits to the archival catalogue.') }}
</div>

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $message)
        <li>{{ $message }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if ($stage === 'upload')
  <div class="card">
    <div class="card-header">
      <h2 class="card-title h5 mb-0">{{ __('Step 1 - Upload MARCXML file') }}</h2>
    </div>
    <div class="card-body">
      <form method="post" action="{{ route('ahgmetadataexport.marc.import.preview') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label for="marcxml" class="form-label">{{ __('MARCXML file') }}</label>
          <input type="file" name="marcxml" id="marcxml" class="form-control" required accept=".xml,.marcxml,text/xml,application/xml">
          <div class="form-text">{{ __('Up to 50 MB. Must conform to http://www.loc.gov/MARC21/slim.') }}</div>
        </div>
        <div class="mb-3">
          <label for="culture" class="form-label">{{ __('Culture / language code') }}</label>
          <input type="text" name="culture" id="culture" class="form-control" value="{{ $culture }}" maxlength="16">
          <div class="form-text">{{ __('Two-letter culture code (en, fr, af, ...) used to write the i18n side of the import.') }}</div>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-eye"></i> {{ __('Validate & Preview') }}
        </button>
      </form>
    </div>
  </div>
@endif

@if ($stage === 'preview')
  @if (!empty($errors) && is_array($errors))
    <div class="alert alert-warning">
      <h2 class="h6"><i class="bi bi-exclamation-triangle"></i> {{ __('Schema validation issues') }}</h2>
      <ul class="mb-0 small">
        @foreach ($errors as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (empty($records))
    <div class="alert alert-secondary">{{ __('No <record> elements were parsed from the upload.') }}</div>
    <a href="{{ route('ahgmetadataexport.marc.import') }}" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> {{ __('Start over') }}
    </a>
  @else
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h2 class="card-title h5 mb-0">{{ __('Step 2 - Preview') }}</h2>
        <span class="badge bg-secondary ms-2">{{ count($records) }} {{ __('record(s)') }}</span>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ __('001 control') }}</th>
              <th>{{ __('245 title') }}</th>
              <th>{{ __('Action') }}</th>
              <th>{{ __('Warnings') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($records as $i => $rec)
              <tr>
                <td>{{ $i + 1 }}</td>
                <td><code>{{ $rec['control_number'] ?? '-' }}</code></td>
                <td>{{ $rec['title'] ?? '-' }}</td>
                <td>
                  @if (!empty($rec['will_create']))
                    <span class="badge bg-success">{{ __('CREATE') }}</span>
                  @else
                    <span class="badge bg-warning text-dark">{{ __('UPDATE') }} #{{ $rec['matched_io_id'] }}</span>
                  @endif
                </td>
                <td>
                  @foreach ($rec['warnings'] ?? [] as $w)
                    <div class="small text-muted">{{ $w }}</div>
                  @endforeach
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    @if ($valid ?? false)
      <form method="post" action="{{ route('ahgmetadataexport.marc.import.commit') }}">
        @csrf
        <input type="hidden" name="marcxml_payload" value="{{ session('marcxml_payload') }}">
        <input type="hidden" name="culture" value="{{ session('marcxml_culture', 'en') }}">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check2-circle"></i> {{ __('Commit Import') }}
        </button>
        <a href="{{ route('ahgmetadataexport.marc.import') }}" class="btn btn-outline-secondary">
          <i class="bi bi-x"></i> {{ __('Cancel') }}
        </a>
      </form>
    @else
      <div class="alert alert-danger">
        <i class="bi bi-shield-exclamation"></i> {{ __('Fix the schema issues above before committing.') }}
        <a href="{{ route('ahgmetadataexport.marc.import') }}" class="btn btn-sm btn-outline-secondary ms-2">
          {{ __('Start over') }}
        </a>
      </div>
    @endif
  @endif
@endif

@if ($stage === 'committed')
  <div class="card">
    <div class="card-header">
      <h2 class="card-title h5 mb-0">{{ __('Import results') }}</h2>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('001 control') }}</th>
            <th>{{ __('245 title') }}</th>
            <th>{{ __('IO id') }}</th>
            <th>{{ __('Action') }}</th>
            <th>{{ __('Audit') }}</th>
            <th>{{ __('Errors') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($records as $i => $rec)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><code>{{ $rec['control_number'] ?? '-' }}</code></td>
              <td>{{ $rec['title'] ?? '-' }}</td>
              <td>{{ $rec['io_id'] ?? '-' }}</td>
              <td><span class="badge bg-secondary">{{ $rec['action'] ?? 'skipped' }}</span></td>
              <td>{{ $rec['audit_id'] ?? '-' }}</td>
              <td>
                @if (!empty($rec['error']))
                  <div class="small text-danger">{{ $rec['error'] }}</div>
                @endif
                @foreach ($rec['warnings'] ?? [] as $w)
                  <div class="small text-muted">{{ $w }}</div>
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  <a href="{{ route('ahgmetadataexport.marc.import') }}" class="btn btn-outline-primary mt-3">
    <i class="bi bi-arrow-repeat"></i> {{ __('Import another file') }}
  </a>
@endif
@endsection
