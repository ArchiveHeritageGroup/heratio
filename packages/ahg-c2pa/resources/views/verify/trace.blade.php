{{--
  Heratio - record-level provenance trace page (provenance roadmap
  trace-endpoint slice, building on issues #1201 / #1209).

  "Show me everything that ever happened to this record." Aggregates the
  content-credentials provenance of every digital object on one archival record
  into a single chronological timeline - capture / digitisation, edits,
  AI-inference steps, and signature / verification status - plus one
  record-level authenticity summary. Read-only, public. Reuses
  ProvenanceTraceService (which reuses ProvenanceRecordService) - it does not
  reimplement signing/verification. International copy (no jurisdiction
  assumptions).

  Record-level summary states (see ProvenanceTraceService):
    verified  -> green    : every signed entry verifies, and all are signed
    partially -> info      : the signed parts verify; some entries are unsigned
    unsigned  -> secondary : provenance exists but nothing is signed
    invalid   -> red       : a signed entry failed verification (tampered)
    none      -> neutral    : no provenance recorded yet (dignified empty state)

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Provenance trace'))
@section('body-class', 'c2pa verify verify-record-trace')

@php
  $summaryBadge = match($summary) {
      'verified'  => ['bg-success',   'fa-check-circle',  __('Verified')],
      'partially' => ['bg-info',      'fa-check-circle',  __('Partially verified')],
      'unsigned'  => ['bg-secondary', 'fa-file-signature',__('Recorded, unsigned')],
      'invalid'   => ['bg-danger',    'fa-times-circle',  __('Could not be verified')],
      default     => ['bg-light text-dark border', 'fa-minus-circle', __('No provenance yet')],
  };

  $eventMeta = function (string $type): array {
      return match($type) {
          'capture'      => ['fa-camera',         __('Capture')],
          'edit'         => ['fa-pen',            __('Edit')],
          'ai-inference' => ['fa-robot',          __('AI inference')],
          'signature'    => ['fa-file-signature', __('Signature')],
          default        => ['fa-circle',         __('Event')],
      };
  };

  $verifyDot = function (?string $state): array {
      return match($state) {
          'verified' => ['#1a7f37', __('Verified')],
          'invalid'  => ['#cf222e', __('Could not be verified')],
          default    => ['#6c757d', __('Unsigned')],
      };
  };
@endphp

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-stream me-2"></i>{{ __('Provenance trace') }}</h1>
  <p class="text-muted mb-0">
    {{ __('Everything recorded about how this record was digitised and handled: capture, edits, automated AI steps, and the signature status of each, gathered across every digital file on the record and shown in time order. Signatures are re-checked live every time you load this page.') }}
  </p>
</div>

{{-- Identity of the record being traced. --}}
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
      <dt class="col-sm-3">{{ __('Digital files') }}</dt>
      <dd class="col-sm-9">{{ $counts['digital_objects'] }}</dd>
    </dl>
  </div>
</div>

{{-- Record-level authenticity verdict. --}}
<div class="card mb-3 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-certificate me-2"></i>{{ __('Record authenticity') }}
  </div>
  <div class="card-body">
    <p class="mb-2">
      <span class="badge {{ $summaryBadge[0] }} fs-5"><i class="fas {{ $summaryBadge[1] }} me-1"></i>{{ $summaryBadge[2] }}</span>
    </p>
    <p class="mb-2">{{ $summaryReason }}</p>
    @if(($counts['records'] ?? 0) > 0)
      <p class="text-muted small mb-0">
        {{ trans_choice('{1}:count provenance record|[2,*]:count provenance records', $counts['records'], ['count' => $counts['records']]) }}
        @if(($counts['signed'] ?? 0) > 0) - {{ $counts['verified'] }}/{{ $counts['signed'] }} {{ __('signed verify') }}@endif.
        @if(($counts['ai'] ?? 0) > 0) {{ trans_choice('{1}:count AI step|[2,*]:count AI steps', $counts['ai'], ['count' => $counts['ai']]) }}.@endif
      </p>
    @endif
    <p class="small mt-2 mb-0">
      <a href="{{ $jsonUrl }}" rel="noopener"><i class="fas fa-database me-1"></i>{{ __('Machine-readable trace (JSON)') }}</a>
      <span class="text-muted">- {{ __('CORS-open and read-only, for programmatic checks.') }}</span>
    </p>
  </div>
</div>

@if(empty($events))
  {{-- Dignified empty state: not an error, just nothing recorded yet. --}}
  <div class="card mb-3">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-stream fa-2x mb-3 d-block" aria-hidden="true"></i>
      <p class="mb-1">{{ __('No provenance has been recorded for this record yet.') }}</p>
      <p class="small mb-0">{{ __('That does not mean anything is wrong - only that no signed provenance has been captured for its files so far.') }}</p>
    </div>
  </div>
@else
  {{-- The unified timeline, grouped by digital object. --}}
  @foreach($groups as $group)
    @php
      $groupBadge = match($group['state']) {
          'verified' => ['bg-success',   'fa-check-circle',  __('Verified')],
          'invalid'  => ['bg-danger',    'fa-times-circle',  __('Could not be verified')],
          default    => ['bg-secondary', 'fa-minus-circle',  __('Unsigned')],
      };
    @endphp
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
          <i class="fas fa-file-alt me-2"></i>{{ $group['name'] ?? __('(unnamed file)') }}
          @if(!empty($group['mime_type']))<code class="small ms-1">{{ $group['mime_type'] }}</code>@endif
        </span>
        <span>
          <span class="badge {{ $groupBadge[0] }}"><i class="fas {{ $groupBadge[1] }} me-1"></i>{{ $groupBadge[2] }}</span>
          <a class="btn btn-sm btn-outline-secondary ms-1" href="{{ $group['verify_url'] }}">
            <i class="fas fa-shield-alt me-1"></i>{{ __('Verify this file') }}
          </a>
        </span>
      </div>
      <div class="card-body">
        @if(empty($group['events']))
          <p class="text-muted mb-0"><i class="fas fa-minus-circle me-1"></i>{{ __('No provenance recorded for this file yet.') }}</p>
        @else
          {{-- Vertical timeline for this digital object. --}}
          <ul class="list-unstyled mb-0 c2pa-timeline" style="position:relative;margin-left:.5rem;padding-left:1.5rem;border-left:2px solid #dee2e6">
            @foreach($group['events'] as $event)
              @php
                [$icon, $typeLabel] = $eventMeta($event['type'] ?? '');
                [$dotColour, $dotLabel] = $verifyDot($event['verification_status'] ?? null);
              @endphp
              <li class="mb-3" style="position:relative">
                <span aria-hidden="true"
                      style="position:absolute;left:-1.95rem;top:.15rem;width:.85rem;height:.85rem;border-radius:50%;background:{{ $dotColour }};border:2px solid #fff;box-shadow:0 0 0 1px {{ $dotColour }}"></span>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                  <span class="fw-semibold">
                    <i class="fas {{ $icon }} me-1 text-muted" aria-hidden="true"></i>{{ $event['summary'] }}
                  </span>
                  <span class="badge bg-light text-dark border">{{ $typeLabel }}</span>
                </div>
                <div class="small text-muted">
                  @if(!empty($event['when']))<span class="me-2"><i class="far fa-clock me-1"></i>{{ $event['when'] }}</span>@endif
                  @if(!empty($event['actor']))<span class="me-2"><i class="far fa-user me-1"></i>{{ $event['actor'] }}</span>@endif
                  @if(!empty($event['tool']))<span class="me-2"><i class="fas fa-tools me-1"></i>{{ $event['tool'] }}</span>@endif
                  <span class="me-2" style="color:{{ $dotColour }}"><i class="fas fa-shield-alt me-1"></i>{{ $dotLabel }}</span>
                </div>
                @if(!empty($event['detail']))
                  <ul class="small text-muted mb-0 mt-1">
                    @foreach($event['detail'] as $k => $val)
                      <li><span class="text-uppercase" style="font-size:.7rem">{{ $k }}</span>: <code class="small">{{ $val }}</code></li>
                    @endforeach
                  </ul>
                @endif
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  @endforeach
@endif

<p class="text-muted small">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('Content credentials follow the C2PA open standard. Signatures are Ed25519 and re-checked live on every page load - nothing here is cached.') }}
  @if(!empty($generatedAt)) <span class="ms-1">{{ __('Generated') }} {{ $generatedAt }}.</span>@endif
</p>
@endsection
