{{--
  Heratio - public, catalogue-wide TRANSPARENCY REPORT (issue #1209).

  The PUBLIC, institution-wide transparency scorecard - the public counterpart to
  the operator-only admin trust console (/admin/trust-console) and to the
  per-record trust dossier (/trust-dossier). Five honest dimensions, each a big
  number + share + CSS progress bar (no charting library): content credentials,
  AI provenance, integrity (fixity), preservation events, accessibility (alt-text).
  Gaps are always shown as gaps. Reuses /trust, /verified-records, /open-data,
  /open-data/maturity and the per-record /trust-dossier for drill-down; it does
  not reimplement any verification. Empty-state safe: a fresh install renders a
  calm "nothing measured yet", never a 500.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Transparency report'))
@section('body-class', 'c2pa transparency-report')

@section('content')
@php
  $pubRecords = (int) ($report['published_records'] ?? 0);
  $pubMasters = (int) ($report['published_masters'] ?? 0);
  $any        = (bool) ($report['has_any_signal'] ?? false);

  $cc  = $report['content_credentials'] ?? [];
  $ai  = $report['ai_provenance'] ?? [];
  $int = $report['integrity'] ?? [];
  $pre = $report['preservation'] ?? [];
  $acc = $report['accessibility'] ?? [];

  // The five dimensions, in display order. Each is a self-describing card:
  // an icon, a title, the honest framing line, the headline count/total/share,
  // and an optional secondary stat line.
  $dimensions = [
    [
      'icon'    => 'fas fa-certificate',
      'title'   => __('Content credentials'),
      'data'    => $cc,
      'count'   => (int) ($cc['count'] ?? 0),
      'total'   => (int) ($cc['total'] ?? 0),
      'share'   => (float) ($cc['share_pct'] ?? 0.0),
      'unit'    => __('published records carry content credentials'),
      'framing' => __('A content credential records how a digitised file was captured and handled. It attests to the file\'s history, not to the truth of what the source itself depicts.'),
      'secondary' => ((int) ($cc['masters_total'] ?? 0)) > 0
        ? __(':signed of :total master files signed (:pct%)', [
            'signed' => number_format((int) ($cc['masters_signed'] ?? 0)),
            'total'  => number_format((int) ($cc['masters_total'] ?? 0)),
            'pct'    => number_format((float) ($cc['masters_pct'] ?? 0.0), 1),
          ])
        : null,
    ],
    [
      'icon'    => 'fas fa-robot',
      'title'   => __('AI provenance'),
      'data'    => $ai,
      'count'   => (int) ($ai['count'] ?? 0),
      'total'   => (int) ($ai['total'] ?? 0),
      'share'   => (float) ($ai['share_pct'] ?? 0.0),
      'unit'    => __('published records have a logged AI step'),
      'framing' => __('Where AI helped describe, transcribe, translate, or assess a record, we log it openly. A logged AI step is a disclosure of involvement, never a claim that the AI was correct - a person remains accountable.'),
      'secondary' => ((int) ($ai['inferences_total'] ?? 0)) > 0
        ? __(':reviewed% of :total AI steps reviewed by a person', [
            'reviewed' => number_format((float) ($ai['reviewed_pct'] ?? 0.0), 1),
            'total'    => number_format((int) ($ai['inferences_total'] ?? 0)),
          ])
        : null,
    ],
    [
      'icon'    => 'fas fa-fingerprint',
      'title'   => __('Integrity'),
      'data'    => $int,
      'count'   => (int) ($int['count'] ?? 0),
      'total'   => (int) ($int['total'] ?? 0),
      'share'   => (float) ($int['share_pct'] ?? 0.0),
      'unit'    => __('master files have a checksum (fixity) baseline'),
      'framing' => __('A fixity baseline is a recorded checksum we can re-check to confirm a file has not changed since it was captured. It guards against silent corruption; it does not certify the file\'s contents.'),
      'secondary' => ((int) ($int['count'] ?? 0)) > 0
        ? __(':pct% of those with a baseline have no failed check on record', [
            'pct' => number_format((float) ($int['verified_pct'] ?? 0.0), 1),
          ])
        : null,
    ],
    [
      'icon'    => 'fas fa-box-archive',
      'title'   => __('Preservation'),
      'data'    => $pre,
      'count'   => (int) ($pre['count'] ?? 0),
      'total'   => (int) ($pre['total'] ?? 0),
      'share'   => (float) ($pre['share_pct'] ?? 0.0),
      'unit'    => __('objects have a recorded preservation event'),
      'framing' => __('A preservation event is a recorded step in a file\'s digital-preservation lifecycle - ingest, format identification, migration, or a virus scan - following the PREMIS model.'),
      'secondary' => ((int) ($pre['events_total'] ?? 0)) > 0
        ? __(':total preservation events on record', [
            'total' => number_format((int) ($pre['events_total'] ?? 0)),
          ])
        : null,
    ],
    [
      'icon'    => 'fas fa-universal-access',
      'title'   => __('Accessibility'),
      'data'    => $acc,
      'count'   => (int) ($acc['count'] ?? 0),
      'total'   => (int) ($acc['total'] ?? 0),
      'share'   => (float) ($acc['share_pct'] ?? 0.0),
      'unit'    => __('published images have a human-written description'),
      'framing' => __('A human-authored text alternative lets people who use a screen reader perceive an image (WCAG 1.1.1). We count only genuinely curated descriptions here, not auto-generated captions.'),
      'secondary' => null,
    ],
  ];

  // Bar colour per share band: green (good) / amber (partial) / grey (thin).
  $barClass = function (float $pct, bool $installed) {
      if (! $installed) { return 'bg-secondary'; }
      if ($pct >= 67) { return 'bg-success'; }
      if ($pct >= 34) { return 'bg-warning'; }
      if ($pct > 0)   { return 'bg-info'; }
      return 'bg-secondary';
  };
@endphp

{{-- Hero. --}}
<div class="card mb-4 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-body p-4">
    <h1 class="mb-2"><i class="fas fa-clipboard-check me-2"></i>{{ __('Transparency report') }}</h1>
    <p class="lead mb-2">
      {{ __('An honest, catalogue-wide account of what we can - and cannot yet - attest about our collection.') }}
    </p>
    <p class="text-muted mb-0">
      {{ __('This is the institution-wide companion to the per-record trust dossier. It rolls our published collection up into five plain-language measures: content credentials, AI provenance, integrity, preservation, and accessibility. Each shows a real number and an honest share. Where a signal has not been captured, we show the gap rather than hide it. All figures cover published records only.') }}
    </p>
  </div>
</div>

{{-- Standing honest caveat - shown in every state, never hidden. --}}
<div class="alert alert-secondary d-flex align-items-start mb-4" role="note">
  <i class="fas fa-info-circle me-2 mt-1"></i>
  <div>{{ __($report['caveat'] ?? '') }}</div>
</div>

{{-- Scope line: the denominators everything below is measured against. --}}
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card h-100 text-center">
      <div class="card-body">
        <div class="display-6">{{ number_format($pubRecords) }}</div>
        <div class="text-muted small">{{ __('published records') }}</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 text-center">
      <div class="card-body">
        <div class="display-6">{{ number_format($pubMasters) }}</div>
        <div class="text-muted small">{{ __('published master files') }}</div>
      </div>
    </div>
  </div>
</div>

@unless($any)
  {{-- Honest empty state. --}}
  <div class="card mb-4">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-3"><i class="fas fa-seedling"></i></div>
      <h2 class="h4 mb-2">{{ __('Nothing measured yet') }}</h2>
      <p class="text-muted mb-0">
        {{ __('No transparency signals have been recorded for the published collection yet. As content credentials, AI-provenance logs, fixity baselines, preservation events, and image descriptions are captured, this report will fill in - showing exactly how much of the collection each measure covers.') }}
      </p>
    </div>
  </div>
@else

  {{-- The five-dimension scorecard. --}}
  <div class="row g-3 mb-4">
    @foreach($dimensions as $d)
      @php
        $installed = (bool) ($d['data']['installed'] ?? false);
        $share = (float) $d['share'];
        $barW  = max(0, min(100, $share));
      @endphp
      <div class="col-12 col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h5 mb-2"><i class="{{ $d['icon'] }} me-2" style="color:var(--ahg-primary)"></i>{{ $d['title'] }}</h2>

            <div class="d-flex align-items-baseline gap-2 mb-1">
              <span class="display-5" style="color:var(--ahg-primary)">{{ number_format($share, 1) }}%</span>
              <span class="text-muted">
                {{ number_format($d['count']) }} {{ __('of') }} {{ number_format($d['total']) }}
              </span>
            </div>
            <div class="text-muted small mb-3">{{ $d['unit'] }}</div>

            <div class="progress mb-2" role="img" style="height:0.9rem"
                 aria-label="{{ $d['title'] }}: {{ number_format($share, 1) }}%">
              <div class="progress-bar {{ $barClass($share, $installed) }}" style="width: {{ $barW }}%"></div>
            </div>

            @if($d['secondary'])
              <p class="small text-muted mb-2">{{ $d['secondary'] }}</p>
            @endif

            @unless($installed)
              <p class="small text-muted mb-2"><i class="fas fa-circle-info me-1"></i>{{ __('This measure is not active on this installation yet.') }}</p>
            @endunless

            <p class="small text-muted mb-0 fst-italic">{{ $d['framing'] }}</p>
          </div>
        </div>
      </div>
    @endforeach
  </div>

@endunless

{{-- Drill-down + related surfaces - reuse the existing pages, never reinvent. --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-compass me-2"></i>{{ __('Explore further') }}</div>
  <div class="card-body">
    <p class="mb-3">{{ __('These figures roll up the whole collection. To dig in, follow any of these:') }}</p>
    <ul class="list-unstyled mb-3">
      <li class="mb-2">
        <i class="fas fa-shield-alt me-2"></i>
        <a href="{{ url('/trust') }}">{{ __('Trust at a glance') }}</a>
        <span class="text-muted small">- {{ __('the verifiable-authenticity summary in detail') }}</span>
      </li>
      <li class="mb-2">
        <i class="fas fa-list-check me-2"></i>
        @if(\Route::has('c2pa.verified.records'))
          <a href="{{ route('c2pa.verified.records') }}">{{ __('Verified records') }}</a>
        @else
          <a href="{{ url('/verified-records') }}">{{ __('Verified records') }}</a>
        @endif
        <span class="text-muted small">- {{ __('browse the records that carry content credentials') }}</span>
      </li>
      <li class="mb-2">
        <i class="fas fa-database me-2"></i>
        @if(\Route::has('open-data.index'))
          <a href="{{ route('open-data.index') }}">{{ __('Open data and APIs') }}</a>
        @else
          <a href="{{ url('/open-data') }}">{{ __('Open data and APIs') }}</a>
        @endif
        <span class="text-muted small">- {{ __('the open endpoints behind these numbers') }}</span>
      </li>
      <li class="mb-2">
        <i class="fas fa-gauge-high me-2"></i>
        @if(\Route::has('open-data.maturity'))
          <a href="{{ route('open-data.maturity') }}">{{ __('Open-data maturity') }}</a>
          <span class="text-muted small">- {{ __('our open-data maturity scorecard') }}</span>
        @else
          <a href="{{ url('/open-data') }}">{{ __('Open-data maturity') }}</a>
          <span class="text-muted small">- {{ __('our open-data maturity scorecard') }}</span>
        @endif
      </li>
    </ul>

    <p class="mb-2">{{ __('Have a reference for one record? See its full trust dossier - what can and cannot be verified for that record alone.') }}</p>
    <form method="get" class="row g-2 align-items-end"
          onsubmit="var v=this.elements['ref'].value.trim(); if(v===''){return false;} window.location.href='{{ url('/trust-dossier') }}/'+encodeURI(v).replace(/%2F/gi,'/'); return false;">
      <div class="col-12 col-md-8">
        <label for="transparency-ref" class="form-label small text-muted mb-1">{{ __('Record permalink or reference') }}</label>
        <input type="text" id="transparency-ref" name="ref" class="form-control"
               placeholder="{{ __('e.g. fonds/series/item or a record id') }}">
      </div>
      <div class="col-12 col-md-4">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fas fa-folder-open me-1"></i>{{ __('View trust dossier') }}
        </button>
      </div>
    </form>

    <p class="small text-muted mb-0 mt-3">
      <i class="fas fa-code me-1"></i><a href="{{ url('/transparency.json') }}">{{ __('Machine-readable summary (JSON)') }}</a>
    </p>
  </div>
</div>

<p class="text-muted small text-end mb-0">
  {{ __('Generated') }}: {{ $report['generated_at'] ?? '' }}
</p>
@endsection
