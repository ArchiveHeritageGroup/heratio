@extends('theme::layouts.1col')

@section('title', $dataset->title)
@section('body-class', 'rdm landing')

@section('content')
@php
  // Access status from the human-gate disposition / lifecycle status.
  $access = match (true) {
      $dataset->status === 'published' || $dataset->disposition === 'release' => ['Open access', '#198754', 'fa-lock-open'],
      $dataset->disposition === 'embargo'    => ['Embargoed', '#fd7e14', 'fa-hourglass-half'],
      in_array($dataset->disposition, ['restrict', 'de-identify'], true) => ['Restricted', '#dc3545', 'fa-lock'],
      default => ['Not yet released', '#6c757d', 'fa-clock'],
  };
  $isOpen = $access[0] === 'Open access';
  $publisher = 'The Archive and Heritage Group';
@endphp

<div class="card">
  <div class="card-body">
    <div class="d-flex align-items-start justify-content-between">
      <h1 class="h4 mb-1"><i class="fas fa-database me-2"></i>{{ $dataset->title }}</h1>
      <span class="badge" style="background:{{ $access[1] }}"><i class="fas {{ $access[2] }} me-1"></i>{{ $access[0] }}</span>
    </div>
    <p class="text-muted small mb-3">{{ __('Research dataset') }}@if ($dataset->project_title) · {{ $dataset->project_title }}@endif · {{ $fileCount }} {{ __('file(s)') }}</p>

    @if ($dataset->description)<p>{{ $dataset->description }}</p>@endif

    {{-- DataCite-style citation --}}
    <div class="border rounded p-3 bg-light small mb-3">
      <div class="fw-bold mb-1">{{ __('Cite this dataset') }}</div>
      {{ $publisher }} ({{ $year }}). <em>{{ $dataset->title }}</em>. {{ $publisher }}.
      @if ($dataset->doi)
        <a href="{{ $doiUrl }}" target="_blank">{{ $doiUrl }}</a>
      @else
        <span class="text-muted">{{ __('(DOI assigned on release)') }}</span>
      @endif
    </div>

    @if ($dataset->doi)
      <p class="small mb-2"><span class="text-muted">{{ __('DOI') }}:</span> <code>{{ $dataset->doi }}</code></p>
    @endif

    @if (! empty($dmp['linked']))
      <p class="small mb-2"><span class="text-muted"><i class="fas fa-clipboard-list me-1"></i>{{ __('Data management') }}:</span> {{ __('Governed by a Data Management Plan') }} <span class="badge bg-light text-dark border">{{ $dmp['linked']['status'] }}</span></p>
    @endif

    @if ($isOpen)
      <div class="alert alert-success py-2 small mb-0"><i class="fas fa-lock-open me-1"></i>{{ __('This dataset is openly available. Sign in to access the files.') }}</div>
    @elseif ($access[0] === 'Embargoed')
      <div class="alert alert-warning py-2 small mb-0"><i class="fas fa-hourglass-half me-1"></i>{{ __('This dataset is under embargo. Metadata is public; files are not yet available.') }}</div>
    @elseif ($access[0] === 'Restricted')
      <div class="alert alert-danger py-2 small mb-0"><i class="fas fa-lock me-1"></i>{{ __('Access to the files is restricted (POPIA / personal data). Contact the repository for mediated access.') }}</div>
    @else
      <div class="alert alert-secondary py-2 small mb-0">{{ __('This dataset has not been released yet.') }}</div>
    @endif
  </div>
</div>
@endsection
