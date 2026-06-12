{{--
  Heratio - public per-record consolidated TRUST DOSSIER page (issues #1209 / #1201,
  next slice).

  The one-stop, print-friendly "defence dossier" for ONE published archival record.
  It UNIFIES the three per-record trust surfaces onto a single page:

    1. Authenticity / content credentials  (from AuthenticityReportService)
    2. AI inference provenance             (from InferenceProvenanceService)
    3. Preservation lifecycle              (from PreservationTimelineService)

  topped by an honest "what can and cannot be verified about this record"
  statement that NEVER overclaims. It reuses TrustDossierService (which composes
  the three existing services READ-ONLY) and reimplements no verification, no AI,
  and no preservation action. International copy (no jurisdiction assumptions).
  Bootstrap 5 + central theme. Each section has its own dignified empty state and a
  link out to its full per-record surface. Print-friendly (browser save-to-PDF);
  a machine companion is at /trust-dossier/{idOrSlug}.json.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Trust dossier'))
@section('body-class', 'c2pa trust-dossier')

@php
  $object   = $dossier['object'];
  $headline = $dossier['headline'];
  $sections = $dossier['sections'];
  $status   = $dossier['section_status'];
  $links    = $dossier['links'];

  $auth = $sections['authenticity'];   // always present (it resolved the record)
  $inf  = $sections['inference'];      // may be null (layer unavailable / faulted)
  $pres = $sections['preservation'];   // may be null (layer unavailable / faulted)

  // Headline confidence badge - derived from the authenticity layer's real
  // verdict, never assumed here.
  $confBadge = match($headline['verdict']) {
      'high'    => ['bg-success',                 'fa-shield-alt',           __('High confidence')],
      'partial' => ['bg-primary',                 'fa-shield-alt',           __('Partial confidence')],
      'low'     => ['bg-warning text-dark',       'fa-file-signature',       __('Recorded, unsigned')],
      'broken'  => ['bg-danger',                  'fa-exclamation-triangle', __('Verification failed')],
      default   => ['bg-light text-dark border',  'fa-minus-circle',         __('No signals yet')],
  };
@endphp

@push('styles')
<style>
  /* Print-friendly: drop the action bar + chrome, keep the evidence. */
  @media print {
    .dossier-no-print { display: none !important; }
    .card { break-inside: avoid; border-color: #999 !important; }
    a[href]::after { content: ""; }
    body { background: #fff !important; }
  }
</style>
@endpush

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-folder-tree me-2"></i>{{ __('Trust dossier') }}</h1>
  <p class="text-muted mb-0">
    {{ __('A single, honest, read-only view of everything this system can show about the trustworthiness of one published record: its content credentials and signed provenance, the AI processing recorded against its metadata, and the preservation lifecycle of its digital files. It consolidates three existing reports; it adds no new verification of its own and never claims more than each underlying report supports.') }}
  </p>
</div>

{{-- Identity of the record. --}}
<div class="card mb-3">
  <div class="card-header"><i class="fas fa-folder-open me-2"></i>{{ __('Record') }}</div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">{{ __('Title') }}</dt>
      <dd class="col-sm-9">{{ $object->title ?? __('(untitled record)') }}</dd>
      @if(!empty($object->identifier))
        <dt class="col-sm-3">{{ __('Reference code') }}</dt>
        <dd class="col-sm-9">{{ $object->identifier }}</dd>
      @endif
      @if(!empty($object->slug))
        <dt class="col-sm-3">{{ __('Permalink') }}</dt>
        <dd class="col-sm-9"><a href="{{ url($object->slug) }}">{{ url($object->slug) }}</a></dd>
      @endif
    </dl>
  </div>
</div>

{{-- The honest top-line: the dossier headline + the can/cannot-verify statement. --}}
<div class="card mb-3 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-scale-balanced me-2"></i>{{ __('What can and cannot be verified about this record') }}
  </div>
  <div class="card-body">
    <p class="mb-2">
      <span class="badge {{ $confBadge[0] }} fs-5"><i class="fas {{ $confBadge[1] }} me-1"></i>{{ $confBadge[2] }}</span>
    </p>
    <p class="mb-0">{{ $headline['statement'] }}</p>
  </div>
</div>

<div class="row g-3 mb-4">
  @if(!empty($dossier['can_verify']))
    <div class="col-md-6">
      <div class="card h-100 border-success">
        <div class="card-header bg-success text-white"><i class="fas fa-check me-2"></i>{{ __('What we can verify') }}</div>
        <div class="card-body"><ul class="mb-0">
          @foreach($dossier['can_verify'] as $line)
            <li class="mb-1">{{ $line }}</li>
          @endforeach
        </ul></div>
      </div>
    </div>
  @endif
  <div class="col-md-{{ empty($dossier['can_verify']) ? '12' : '6' }}">
    <div class="card h-100 border-secondary">
      <div class="card-header bg-secondary text-white"><i class="fas fa-ban me-2"></i>{{ __('What we cannot verify') }}</div>
      <div class="card-body"><ul class="mb-0">
        @foreach($dossier['cannot_verify'] as $line)
          <li class="mb-1">{{ $line }}</li>
        @endforeach
      </ul></div>
    </div>
  </div>
</div>

{{-- =============================================================== --}}
{{-- SECTION 1 - Authenticity / content credentials (C2PA layer).     --}}
{{-- =============================================================== --}}
<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="fas fa-certificate me-2"></i>{{ __('1. Authenticity and content credentials') }}</span>
    <a class="btn btn-outline-primary btn-sm dossier-no-print" href="{{ $links['authenticity'] }}">
      <i class="fas fa-up-right-from-square me-1"></i>{{ __('Open full authenticity report') }}
    </a>
  </div>
  <div class="card-body">
    @if($auth === null)
      <p class="text-muted mb-0 text-center py-4">
        <i class="fas fa-minus-circle me-1"></i>{{ __('The authenticity layer is not available for this record.') }}
      </p>
    @else
      @php $cc = $auth['signals']['content_credentials']; $pv = $auth['signals']['provenance']; $ai = $auth['signals']['ai_inference']; @endphp
      <p class="mb-3">{{ $auth['summary'] }}</p>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted text-uppercase mb-1">{{ __('Content credentials') }}</div>
            @if($cc['signed'] > 0)
              <div class="fw-semibold">{{ $cc['verified'] }}/{{ $cc['signed'] }} {{ __('verify live') }}</div>
              @if($cc['invalid'] > 0)
                <div class="small text-danger mt-1"><i class="fas fa-exclamation-triangle me-1"></i>{{ trans_choice('{1}:n failed verification|[2,*]:n failed verification', $cc['invalid'], ['n' => $cc['invalid']]) }}</div>
              @endif
            @else
              <div class="text-muted">{{ __('None signed') }}</div>
            @endif
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted text-uppercase mb-1">{{ __('Provenance') }}</div>
            <div class="fw-semibold">{{ $auth['confidence_label'] }}</div>
            <div class="small text-muted mt-1">
              {{ trans_choice('{0}no provenance records|{1}:n provenance record|[2,*]:n provenance records', $pv['records'], ['n' => $pv['records']]) }}
              &middot; {{ trans_choice('{0}no digital files|{1}:n digital file|[2,*]:n digital files', $pv['digital_objects'], ['n' => $pv['digital_objects']]) }}
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted text-uppercase mb-1">{{ __('AI processing') }}</div>
            <div class="fw-semibold">{{ $ai['present'] ? __('Recorded') : __('None recorded') }}</div>
            @if($ai['present'])
              <div class="small text-muted mt-1">{{ trans_choice('{1}:n automated step|[2,*]:n automated steps', $ai['count'], ['n' => $ai['count']]) }}</div>
            @endif
          </div>
        </div>
      </div>
      <div class="mt-3 dossier-no-print">
        <a class="btn btn-outline-secondary btn-sm" href="{{ $auth['trace_url'] }}"><i class="fas fa-stream me-1"></i>{{ __('Full provenance trace') }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ $auth['trace_json_url'] }}" rel="noopener"><i class="fas fa-database me-1"></i>{{ __('Trace JSON') }}</a>
      </div>
    @endif
  </div>
</div>

{{-- =============================================================== --}}
{{-- SECTION 2 - AI inference provenance.                             --}}
{{-- =============================================================== --}}
<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="fas fa-robot me-2"></i>{{ __('2. AI inference provenance') }}</span>
    <a class="btn btn-outline-primary btn-sm dossier-no-print" href="{{ $links['inference'] }}">
      <i class="fas fa-up-right-from-square me-1"></i>{{ __('Open full inference explorer') }}
    </a>
  </div>
  <div class="card-body">
    @php
      $infHasAny = $inf !== null && ($inf['available'] ?? false) && (int) ($inf['counts']['total'] ?? 0) > 0;
    @endphp
    @if($inf === null)
      <p class="text-muted mb-0 text-center py-4">
        <i class="fas fa-minus-circle me-1"></i>{{ __('The AI inference provenance layer is not available for this record.') }}
      </p>
    @elseif(!$infHasAny)
      <div class="text-center text-muted py-4">
        <i class="fas fa-robot fa-2x mb-2 d-block" aria-hidden="true"></i>
        <p class="mb-1">{{ __('No AI inference recorded for this record.') }}</p>
        <p class="small mb-0">
          @if(!($inf['available'] ?? false))
            {{ __('This system has no AI inference provenance store configured, so no automated steps can be shown.') }}
          @else
            {{ __('Its metadata was either entered by hand or pre-dates inference logging. Absence of a step is shown as absence, never invented.') }}
          @endif
        </p>
      </div>
    @else
      <p class="mb-3">{{ $inf['summary'] }}</p>
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold">{{ $inf['counts']['total'] }}</div><div class="small text-muted">{{ trans_choice('{1}AI inference|[2,*]AI inferences', $inf['counts']['total']) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold">{{ $inf['counts']['models'] }}</div><div class="small text-muted">{{ trans_choice('{1}model|[2,*]models', $inf['counts']['models']) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold text-success">{{ $inf['counts']['reviewed'] }}</div><div class="small text-muted">{{ __('human-reviewed') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold">{{ $inf['counts']['pending'] }}</div><div class="small text-muted">{{ __('awaiting review') }}</div></div></div>
      </div>
      @if(!empty($inf['by_service']))
        <div class="d-flex flex-wrap gap-2">
          @foreach($inf['by_service'] as $service => $n)
            <span class="badge bg-light text-dark border">{{ $service }} <span class="badge bg-secondary ms-1">{{ $n }}</span></span>
          @endforeach
        </div>
      @endif
    @endif
  </div>
</div>

{{-- =============================================================== --}}
{{-- SECTION 3 - Preservation lifecycle (PREMIS).                     --}}
{{-- =============================================================== --}}
<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="fas fa-clock-rotate-left me-2"></i>{{ __('3. Preservation lifecycle') }}</span>
    <a class="btn btn-outline-primary btn-sm dossier-no-print" href="{{ $links['preservation'] }}">
      <i class="fas fa-up-right-from-square me-1"></i>{{ __('Open full preservation timeline') }}
    </a>
  </div>
  <div class="card-body">
    @php $presHasAny = $pres !== null && (int) ($pres['counts']['total'] ?? 0) > 0; @endphp
    @if($pres === null)
      <p class="text-muted mb-0 text-center py-4">
        <i class="fas fa-minus-circle me-1"></i>{{ __('The preservation lifecycle layer is not available for this record.') }}
      </p>
    @elseif(!$presHasAny)
      <div class="text-center text-muted py-4">
        <i class="fas fa-clock-rotate-left fa-2x mb-2 d-block" aria-hidden="true"></i>
        <p class="mb-1">{{ __('No preservation events recorded yet.') }}</p>
        <p class="small mb-0">{{ __('No ingest, fixity, format-identification, migration, or virus-scan step is on file for this record\'s digital objects. Absence of an event is shown as absence, never invented.') }}</p>
      </div>
    @else
      <p class="mb-3">{{ $pres['summary'] }}</p>
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold">{{ $pres['counts']['total'] }}</div><div class="small text-muted">{{ trans_choice('{1}event|[2,*]events', $pres['counts']['total']) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold">{{ $pres['counts']['stages'] }}</div><div class="small text-muted">{{ trans_choice('{1}stage|[2,*]stages', $pres['counts']['stages']) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold text-success">{{ $pres['counts']['success'] }}</div><div class="small text-muted">{{ __('recorded success') }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2 text-center"><div class="fs-4 fw-bold {{ $pres['counts']['failure'] > 0 ? 'text-danger' : '' }}">{{ $pres['counts']['failure'] }}</div><div class="small text-muted">{{ __('recorded failure') }}</div></div></div>
      </div>
      @if(!empty($pres['by_stage']))
        <div class="d-flex flex-wrap gap-2">
          @foreach($pres['by_stage'] as $stage => $n)
            <span class="badge bg-light text-dark border">{{ $stage }} <span class="badge bg-secondary ms-1">{{ $n }}</span></span>
          @endforeach
        </div>
      @endif
    @endif
  </div>
</div>

{{-- Action bar: print + JSON + record. Hidden from the printed dossier. --}}
<div class="card mb-3 dossier-no-print">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
        <i class="fas fa-print me-1"></i>{{ __('Print or save as PDF') }}
      </button>
      <a class="btn btn-outline-secondary btn-sm" href="{{ $links['dossier_json'] }}" rel="noopener">
        <i class="fas fa-database me-1"></i>{{ __('This dossier as JSON') }}
      </a>
      @if(!empty($object->slug))
        <a class="btn btn-outline-secondary btn-sm" href="{{ url($object->slug) }}">
          <i class="fas fa-file-alt me-1"></i>{{ __('View the record') }}
        </a>
      @endif
    </div>
  </div>
</div>

<p class="text-muted small">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('This dossier consolidates three independent, read-only reports - content credentials and signed provenance (the C2PA standard), AI inference provenance, and the PREMIS preservation lifecycle. It describes what was recorded and can be re-checked; it does not, and cannot, attest that what the source itself depicts is true. Each section links to its full report for the underlying detail.') }}
  @if(!empty($dossier['generated_at'])) <span class="ms-1">{{ __('Generated') }} {{ $dossier['generated_at'] }}.</span>@endif
</p>
@endsection
