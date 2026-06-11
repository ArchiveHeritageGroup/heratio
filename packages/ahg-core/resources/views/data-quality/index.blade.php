{{--
  Metadata completeness / data-quality dashboard (admin). Read-only audit that
  surfaces PUBLISHED archival descriptions missing key descriptive fields so
  cataloguers can close the gaps. Built from AhgCore\Services\DataQualityService.
  Jurisdiction-neutral. Distinct from the capture-priority register (which is
  about at-risk physical capture) - this is about METADATA quality.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', __('Metadata completeness'))

@section('content')
@php
  $report = $report ?? [];
  $total = (int) ($report['total'] ?? 0);
  $complete = (int) ($report['complete'] ?? 0);
  $completenessPct = (float) ($report['completeness_pct'] ?? 0);
  $issues = $report['issues'] ?? [];
  $sample = $report['sample'] ?? [];
  $generatedAt = $report['generated_at'] ?? null;
  $hasError = ! empty($report['error']);
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-clipboard-check me-2 text-primary"></i>{{ __('Metadata completeness') }}</h1>
    <span class="text-muted small">{{ __('Where published records are missing key descriptive fields') }}</span>
    <span class="ms-auto"></span>
    @if(Route::has('capture-priority.index'))
      <a href="{{ route('capture-priority.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-triangle-exclamation me-1"></i>{{ __('Capture priority') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-3" style="max-width:820px">
    {{ __('This dashboard audits the quality of metadata already recorded on published descriptions. It counts how many published records are missing each key descriptive field - title, scope or abstract, level of description, creation date, creator, subjects, and a digital surrogate - so cataloguers can see the gaps at a glance and work through them. It is read-only: nothing here changes a record.') }}
  </p>

  @if($hasError)
    <div class="alert alert-warning"><i class="fas fa-circle-exclamation me-1"></i>{{ __('The completeness report could not be built from the catalogue right now. Please try again later.') }}</div>
  @endif

  @if($total <= 0)
    {{-- Clean "no data yet" state - never a 500. --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <div class="display-6 text-muted mb-2">0%</div>
        <h2 class="h5">{{ __('No published records to audit yet') }}</h2>
        <p class="text-muted mb-0" style="max-width:560px;margin:0 auto">
          {{ __('Once descriptions are published, this dashboard will show how complete their metadata is and highlight the records that most need attention.') }}
        </p>
      </div>
    </div>
  @else
    @php
      // Headline colour band: green when most records are complete, amber mid, red low.
      $headlineClass = $completenessPct >= 80 ? 'text-success' : ($completenessPct >= 50 ? 'text-warning' : 'text-danger');
      $barClass = $completenessPct >= 80 ? 'bg-success' : ($completenessPct >= 50 ? 'bg-warning' : 'bg-danger');
      $pctWidth = max(0, min(100, $completenessPct));
    @endphp

    {{-- Headline completeness --}}
    <div class="row g-3 mb-3">
      <div class="col-12 col-lg-5">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small text-uppercase mb-1">{{ __('Overall completeness') }}</div>
            <div class="d-flex align-items-baseline gap-2">
              <div class="display-5 mb-0 {{ $headlineClass }}">{{ number_format($completenessPct, 1) }}%</div>
              <div class="text-muted small">
                {{ number_format($complete) }} {{ __('of') }} {{ number_format($total) }} {{ __('published records have every key field') }}
              </div>
            </div>
            <div class="progress mt-2" style="height:10px" role="progressbar"
                 aria-valuenow="{{ $pctWidth }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="{{ __('Overall metadata completeness') }}">
              <div class="progress-bar {{ $barClass }}" style="width: {{ $pctWidth }}%"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body py-2 d-flex align-items-center">
            <p class="text-muted small mb-0">
              {{ __('A record counts as complete only when it is missing none of the key fields below. The breakdown shows how much of the published collection is affected by each individual gap; the same record can appear in more than one row.') }}
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- Per-issue breakdown --}}
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">
        <i class="fas fa-list-ul me-1"></i>{{ __('Missing fields, by issue') }}
      </div>
      <div class="card-body">
        @if(empty($issues))
          <p class="text-muted small mb-0">{{ __('No issues to report.') }}</p>
        @else
          @foreach($issues as $key => $issue)
            @php
              $iCount = (int) ($issue['count'] ?? 0);
              $iPct = (float) ($issue['pct'] ?? 0);
              $iWidth = max(0, min(100, $iPct));
              $iLabel = $issue['label'] ?? $key;
              $iBar = $iPct >= 50 ? 'bg-danger' : ($iPct >= 20 ? 'bg-warning' : 'bg-info');
            @endphp
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-baseline">
                <span class="small">{{ __($iLabel) }}</span>
                <span class="small text-muted">
                  {{ number_format($iCount) }} {{ __('records') }}
                  <span class="ms-1">({{ number_format($iPct, 1) }}%)</span>
                </span>
              </div>
              <div class="progress" style="height:8px" role="progressbar"
                   aria-valuenow="{{ $iWidth }}" aria-valuemin="0" aria-valuemax="100"
                   aria-label="{{ __($iLabel) }}">
                <div class="progress-bar {{ $iBar }}" style="width: {{ $iWidth }}%"></div>
              </div>
            </div>
          @endforeach
        @endif
      </div>
    </div>

    {{-- Worst records sample --}}
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold"><i class="fas fa-triangle-exclamation me-1 text-warning"></i>{{ __('Records needing the most attention') }}</span>
        <span class="text-muted small">{{ __('Most missing fields first') }}</span>
        <span class="ms-auto text-muted small">{{ count($sample) }} {{ __('shown') }}</span>
      </div>
      <div class="card-body p-0">
        @if(empty($sample))
          <p class="text-muted small p-3 mb-0">
            <i class="fas fa-circle-check me-1 text-success"></i>{{ __('Every published record has all key fields. Nothing to fix here.') }}
          </p>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col" style="width:3rem" class="text-end">#</th>
                  <th scope="col">{{ __('Record') }}</th>
                  <th scope="col" class="text-center" style="width:6rem">{{ __('Missing') }}</th>
                  <th scope="col">{{ __('Missing fields') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($sample as $i => $r)
                  @php
                    $rTitle = $r['title'] ?? '';
                    $rSlug = $r['slug'] ?? null;
                    $rMissing = $r['missing'] ?? [];
                    $rMissingCount = (int) ($r['missing_count'] ?? count($rMissing));
                  @endphp
                  <tr>
                    <td class="text-end text-muted">{{ $i + 1 }}</td>
                    <td>
                      @if(!empty($rSlug))
                        <a href="{{ url('/'.$rSlug) }}" target="_blank" rel="noopener">{{ $rTitle }}</a>
                      @else
                        <span>{{ $rTitle }}</span>
                      @endif
                    </td>
                    <td class="text-center">
                      <span class="badge {{ $rMissingCount >= 4 ? 'bg-danger' : 'bg-warning text-dark' }}">{{ $rMissingCount }}</span>
                    </td>
                    <td>
                      @foreach($rMissing as $m)
                        <span class="badge bg-light text-dark border me-1 mb-1">{{ __($m) }}</span>
                      @endforeach
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    @if($generatedAt)
      <p class="text-muted small mb-0">{{ __('Generated') }}: {{ $generatedAt }}</p>
    @endif
  @endif

</div>
@endsection
