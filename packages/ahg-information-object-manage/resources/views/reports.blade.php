@extends('theme::layout')

@section('title', 'Reports - ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container-fluid py-3">
  <div class="row">

    {{-- Sidebar --}}
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-info-circle me-1"></i> Context
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('informationobject.show', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-arrow-left me-1"></i> Back to description
          </a>
        </div>
      </div>
    </div>

    {{-- Main content --}}
    <div class="col-md-9">

      <div class="multiline-header d-flex flex-column mb-3">
        <h1 class="mb-0">{{ __('Reports') }}</h1>
        <span class="small text-muted">{{ $io->title ?? 'Untitled' }}</span>
      </div>

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="reports-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#reports-collapse" aria-expanded="true" aria-controls="reports-collapse">
              {{ __('Reports') }}
            </button>
          </h2>
          <div id="reports-collapse" class="accordion-collapse collapse show" aria-labelledby="reports-heading">
            <div class="accordion-body">

              @if(!empty($existingReports))
                <p>Existing reports:</p>
                <ul class="job-report-list">
                  @foreach($existingReports as $report)
                    <li>
                      <a href="{{ $report['path'] }}">{{ $report['type'] }} ({{ $report['format'] }})</a>
                    </li>
                  @endforeach
                </ul>
              @endif

              @if($reportsAvailable)
                <form action="{{ route('informationobject.reports', $io->slug) }}" method="POST">
                  @csrf
                  <div class="mb-3">
                    <label for="report" class="form-label">Select new report to generate: <span class="badge bg-secondary ms-1">Optional</span></label>
                    <select name="report" id="report" class="form-select">
                      @foreach($reportTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>

                  <ul class="actions mb-3 nav gap-2">
                    <li>
                      <a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
                    </li>
                    <li>
                      <input class="btn atom-btn-outline-success" type="submit" value="Continue">
                    </li>
                  </ul>
                </form>
              @else
                <p>There are no relevant reports for this item.</p>
                <ul class="actions mb-3 nav gap-2">
                  <li>
                    <a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
                  </li>
                </ul>
              @endif

            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
