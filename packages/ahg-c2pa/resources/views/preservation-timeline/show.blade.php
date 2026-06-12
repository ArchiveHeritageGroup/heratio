{{--
  Heratio - public per-record PRESERVATION-TIMELINE explorer page (issue #1244,
  building on the #1201 provenance epic).

  The honest, read-only view of the PREMIS-style digital-preservation lifecycle of
  ONE published archival record's digital objects - ingest, fixity checks, format
  identification, migrations / normalisations, and virus scans - in chronological
  order, each with its recorded outcome and the responsible agent or tool. It
  reuses PreservationTimelineService (read-only over the locked ahg-preservation
  stores) and reimplements no preservation action or verification. International
  copy (no jurisdiction assumptions). Bootstrap 5 + central theme. Never
  overclaims: this is the RECORDED preservation history, not a verdict on the
  source itself. Absence of events is shown as absence, never invented.

  Distinct from /inference-provenance (AI inference) and /authenticity (C2PA
  signing); links to both for the full trust picture.

  Outcome styling:
    success -> green   warning -> amber   failure -> red   unknown -> grey

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Preservation timeline'))
@section('body-class', 'c2pa preservation-timeline')

@php
  $object   = $report['object'];
  $counts   = $report['counts'];
  $events   = $report['events'];
  $byStage  = $report['by_stage'];
  $hasAny   = $counts['total'] > 0;

  $outcomePill = function (string $outcome) {
      return match ($outcome) {
          'success' => ['bg-success', 'fa-check-circle'],
          'warning' => ['bg-warning text-dark', 'fa-exclamation-triangle'],
          'failure' => ['bg-danger',  'fa-times-circle'],
          default   => ['bg-secondary', 'fa-circle-info'],
      };
  };

  $stageIcon = function (string $stage) {
      return match ($stage) {
          'ingest'    => 'fa-box-open',
          'fixity'    => 'fa-fingerprint',
          'format'    => 'fa-file-code',
          'migration' => 'fa-right-left',
          'virus'     => 'fa-shield-virus',
          default     => 'fa-circle-dot',
      };
  };
@endphp

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-clock-rotate-left me-2"></i>{{ __('Preservation timeline') }}</h1>
  <p class="text-muted mb-0">
    {{ __('An honest, read-only record of the digital-preservation lifecycle of this published record\'s digital objects - ingest, fixity checks, format identification, migrations or normalisations, and virus scans - in the order they happened, each with its recorded outcome and the responsible agent or tool. This page describes what was recorded; it is not a verdict on the source itself.') }}
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

{{-- The honest headline summary. --}}
<div class="card mb-3 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-stream me-2"></i>{{ __('This record\'s preservation history') }}
  </div>
  <div class="card-body">
    <p class="mb-0">{{ $report['summary'] }}</p>
  </div>
</div>

@if(!$hasAny)
  {{-- Dignified empty state: not an error, just nothing recorded yet. --}}
  <div class="card mb-3">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-clock-rotate-left fa-2x mb-3 d-block" aria-hidden="true"></i>
      <p class="mb-1">{{ __('No preservation events recorded yet.') }}</p>
      <p class="small mb-0">
        {{ __('No ingest, fixity, format-identification, migration, or virus-scan step is on file for this record\'s digital objects. That does not mean anything is wrong - only that no automated preservation step has been recorded for it.') }}
      </p>
    </div>
  </div>
@else
  {{-- At-a-glance counts. --}}
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold">{{ $counts['total'] }}</div>
        <div class="small text-muted">{{ trans_choice('{1}preservation event|[2,*]preservation events', $counts['total']) }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold">{{ $counts['stages'] }}</div>
        <div class="small text-muted">{{ trans_choice('{1}lifecycle stage|[2,*]lifecycle stages', $counts['stages']) }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold text-success">{{ $counts['success'] }}</div>
        <div class="small text-muted">{{ __('recorded success') }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold {{ $counts['failure'] > 0 ? 'text-danger' : '' }}">{{ $counts['failure'] }}</div>
        <div class="small text-muted">{{ __('recorded failure') }}</div>
      </div></div>
    </div>
  </div>

  {{-- By-stage breakdown. --}}
  @if(!empty($byStage))
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-layer-group me-2"></i>{{ __('By lifecycle stage') }}</div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          @foreach($byStage as $stage => $n)
            <span class="badge bg-light text-dark border">{{ $stage }} <span class="badge bg-secondary ms-1">{{ $n }}</span></span>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- The honest "what we can / cannot say" statement. Always present. --}}
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="card h-100 border-success">
        <div class="card-header bg-success text-white"><i class="fas fa-check me-2"></i>{{ __('What this page can show') }}</div>
        <div class="card-body"><ul class="mb-0">
          <li class="mb-1">{{ __('Each recorded preservation step - ingest, fixity, format identification, migration, virus scan - in chronological order.') }}</li>
          <li class="mb-1">{{ __('The recorded outcome of each step, and when it ran.') }}</li>
          <li class="mb-1">{{ __('The agent or tool responsible for each step.') }}</li>
        </ul></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 border-secondary">
        <div class="card-header bg-secondary text-white"><i class="fas fa-ban me-2"></i>{{ __('What this page does not claim') }}</div>
        <div class="card-body"><ul class="mb-0">
          <li class="mb-1">{{ __('That the source itself is authentic, complete, or true - only that these preservation steps were recorded.') }}</li>
          <li class="mb-1">{{ __('Any step not on file. Absence of an event is shown as absence, never inferred or invented.') }}</li>
          <li class="mb-1">{{ __('Anything about C2PA signing or AI inference - those are separate, linked below.') }}</li>
        </ul></div>
      </div>
    </div>
  </div>

  {{-- The chronological preservation timeline. --}}
  <div class="card mb-3">
    <div class="card-header"><i class="fas fa-list-ol me-2"></i>{{ __('Recorded preservation events') }} <span class="text-muted">({{ __('oldest first') }})</span></div>
    <div class="list-group list-group-flush">
      @foreach($events as $ev)
        @php [$pillCls, $pillIcon] = $outcomePill($ev['outcome']); @endphp
        <div class="list-group-item">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
            <div>
              <i class="fas {{ $stageIcon($ev['stage']) }} me-1 text-muted" aria-hidden="true"></i>
              <span class="fw-semibold">{{ $ev['label'] }}</span>
              <span class="text-muted">&middot; {{ $ev['stage_label'] }}</span>
            </div>
            <span class="badge {{ $pillCls }}"><i class="fas {{ $pillIcon }} me-1"></i>{{ $ev['outcome_label'] }}</span>
          </div>
          <div class="small text-muted">
            @if($ev['when_display'])<span class="me-3"><i class="far fa-clock me-1"></i>{{ $ev['when_display'] }}</span>@endif
            @if($ev['agent'])<span class="me-3"><i class="fas fa-user-gear me-1"></i>{{ __('by') }} {{ $ev['agent'] }}</span>@endif
            @if($ev['object_id'])<span class="me-3"><i class="fas fa-file me-1"></i>{{ __('digital object #:id', ['id' => $ev['object_id']]) }}</span>@endif
            <span class="me-3"><i class="fas fa-database me-1"></i>{{ $ev['source'] }}</span>
          </div>
          @if(!empty($ev['detail']))
            <div class="small mt-1">{{ $ev['detail'] }}</div>
          @endif
        </div>
      @endforeach
    </div>
    @if($report['truncated'])
      <div class="card-footer small text-muted">
        <i class="fas fa-info-circle me-1"></i>{{ __('Only the earliest :n events are shown. More preservation events exist for this record.', ['n' => $counts['shown']]) }}
      </div>
    @endif
  </div>
@endif

{{-- Where to go deeper + the machine-readable companion. --}}
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <a class="btn btn-outline-primary btn-sm" href="{{ $report['authenticity_url'] }}">
        <i class="fas fa-certificate me-1"></i>{{ __('See the authenticity report') }}
      </a>
      <a class="btn btn-outline-primary btn-sm" href="{{ $report['inference_url'] }}">
        <i class="fas fa-robot me-1"></i>{{ __('See the AI inference provenance') }}
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ $report['timeline_json_url'] }}" rel="noopener">
        <i class="fas fa-database me-1"></i>{{ __('This page as JSON') }}
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
  {{ __('A preservation timeline follows the PREMIS discipline that every step in a digital object\'s life - ingest, fixity verification, format identification, migration or normalisation, virus scan - is recorded with its outcome, its timing, and the responsible agent. This page is a read-only view of that record; it is the recorded preservation history, not an attestation that the source itself is authentic.') }}
  @if(!empty($report['generated_at'])) <span class="ms-1">{{ __('Generated') }} {{ $report['generated_at'] }}.</span>@endif
</p>
@endsection
