{{--
  Open-format project export - per-project export page (heratio#1237, Research OS #15)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Founding principle: "no lock-in / the exit door is always open." A one-click,
  full-fidelity export of the whole project to open, non-proprietary formats:
  a single ZIP (Markdown + JSON + BibTeX + RIS + CSL-JSON), or any one format.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Export project')
@section('title-block')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="h2 mb-0"><i class="fas fa-box-open text-primary me-2"></i>{{ __('Export project') }}</h1>
  <a href="{{ route('research.export.zip', $project->id) }}" class="btn btn-primary">
    <i class="fas fa-file-archive me-1"></i>{{ __('Download everything (ZIP)') }}
  </a>
</div>
@endsection

@section('content')
@php
  $manifest = $manifest ?? ['included' => [], 'omitted' => []];
  $included = $manifest['included'] ?? [];
  $omitted  = $manifest['omitted'] ?? [];
  $recent   = $recent ?? [];

  $sectionLabels = [
    'project'         => __('Project overview'),
    'question_brief'  => __('Research Design Brief (versions)'),
    'claims'          => __('Claims, evidence and Claim Ledger'),
    'decision_log'    => __('Decision Log'),
    'argument'        => __('Argument scaffold'),
    'method_protocol' => __('Method protocol'),
    'research_memory' => __('Research Memory'),
    'bibliography'    => __('Sources / bibliography entries'),
  ];
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Export') }}</li>
  </ol>
</nav>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
  </div>
@endif

<div class="alert alert-light border d-flex align-items-start gap-2" role="note">
  <i class="fas fa-door-open text-primary mt-1"></i>
  <div>
    <strong>{{ __('No lock-in. The exit door is always open.') }}</strong>
    <div class="text-muted">
      {{ __('This is a faithful, full-fidelity copy of your project in open, non-proprietary formats. Take it anywhere - no part of your work is trapped here.') }}
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- What is included --}}
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header bg-white">
        <h2 class="h5 mb-0"><i class="fas fa-list-check text-primary me-2"></i>{{ __('What this export includes') }}</h2>
      </div>
      <div class="card-body">
        @if(empty($included) && empty($omitted))
          <p class="text-muted mb-0">{{ __('No exportable content was found for this project yet.') }}</p>
        @else
          <ul class="list-group list-group-flush">
            @foreach($sectionLabels as $key => $label)
              @if(array_key_exists($key, $included))
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <span><i class="fas fa-check-circle text-success me-2"></i>{{ $label }}</span>
                  <span class="badge bg-secondary rounded-pill">{{ (int) $included[$key] }}</span>
                </li>
              @elseif(array_key_exists($key, $omitted))
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 text-muted">
                  <span><i class="fas fa-minus-circle me-2"></i>{{ $label }}</span>
                  <span class="badge bg-light text-muted border">{{ __('not available') }}</span>
                </li>
              @endif
            @endforeach
          </ul>
          @if(!empty($omitted))
            <p class="small text-muted mt-3 mb-0">
              <i class="fas fa-info-circle me-1"></i>{{ __('Sections marked "not available" are not present in this installation and are simply omitted - the rest of the export is complete. This is recorded in the bundle manifest.') }}
            </p>
          @endif
        @endif
      </div>
    </div>
  </div>

  {{-- Format buttons --}}
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header bg-white">
        <h2 class="h5 mb-0"><i class="fas fa-download text-primary me-2"></i>{{ __('Download') }}</h2>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2 mb-3">
          <a href="{{ route('research.export.zip', $project->id) }}" class="btn btn-primary">
            <i class="fas fa-file-archive me-1"></i>{{ __('Everything as one ZIP') }}
          </a>
        </div>
        <p class="small text-muted mb-2">{{ __('The ZIP bundles all of the formats below plus a README and manifest. Or take any single format:') }}</p>
        <div class="d-grid gap-2">
          <a href="{{ route('research.export.markdown', $project->id) }}" class="btn btn-outline-secondary text-start">
            <i class="fab fa-markdown me-2"></i>{{ __('Markdown') }} <span class="text-muted small">{{ __('- the whole project, human-readable (.md)') }}</span>
          </a>
          <a href="{{ route('research.export.json', $project->id) }}" class="btn btn-outline-secondary text-start">
            <i class="fas fa-code me-2"></i>{{ __('JSON') }} <span class="text-muted small">{{ __('- the same data, machine-readable (.json)') }}</span>
          </a>
          <a href="{{ route('research.export.bibtex', $project->id) }}" class="btn btn-outline-secondary text-start">
            <i class="fas fa-quote-right me-2"></i>{{ __('BibTeX') }} <span class="text-muted small">{{ __('- sources (.bib)') }}</span>
          </a>
          <a href="{{ route('research.export.ris', $project->id) }}" class="btn btn-outline-secondary text-start">
            <i class="fas fa-quote-right me-2"></i>{{ __('RIS') }} <span class="text-muted small">{{ __('- sources (.ris)') }}</span>
          </a>
          <a href="{{ route('research.export.csl', $project->id) }}" class="btn btn-outline-secondary text-start">
            <i class="fas fa-quote-right me-2"></i>{{ __('CSL-JSON') }} <span class="text-muted small">{{ __('- sources (.json)') }}</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Recent exports (only if the optional log table exists and has rows) --}}
@if(!empty($recent))
<div class="card mt-4">
  <div class="card-header bg-white">
    <h2 class="h6 mb-0"><i class="fas fa-clock-rotate-left text-muted me-2"></i>{{ __('Recent exports') }}</h2>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead>
          <tr>
            <th scope="col">{{ __('When') }}</th>
            <th scope="col">{{ __('Format') }}</th>
            <th scope="col">{{ __('By') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recent as $row)
            <tr>
              <td class="text-muted">{{ $row->exported_at ?? '' }}</td>
              <td><span class="badge bg-light text-dark border text-uppercase">{{ $row->format ?? '' }}</span></td>
              <td>{{ $row->exported_by ?? '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif

@endsection
