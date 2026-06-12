{{--
  Heratio - public per-record INFERENCE-PROVENANCE explorer page (issue #1201).

  The honest read surface over the AI-inference provenance foundation
  (ahg_ai_inference + ahg_ai_override). For ONE published archival record it
  shows which AI inferences contributed to its metadata - the model, the
  gateway, when - and that a human remained accountable for the result. It
  reuses InferenceProvenanceService (read-only) and reimplements no AI or
  verification. International copy (no jurisdiction assumptions). Bootstrap 5
  + central theme. Never overclaims: an unreviewed step is "AI-suggested, not
  yet reviewed", never "verified".

  Review states (see InferenceProvenanceService):
    accepted  -> green  : a curator reviewed and kept the AI output
    corrected -> blue   : a curator changed the AI output
    rejected  -> red    : a curator rejected the AI output
    pending   -> grey   : AI-suggested, not yet reviewed

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('AI inference provenance'))
@section('body-class', 'c2pa inference-provenance')

@php
  $object     = $report['object'];
  $counts     = $report['counts'];
  $inferences = $report['inferences'];
  $byService  = $report['by_service'];
  $hasAny     = $report['available'] && $counts['total'] > 0;

  $reviewPill = function (string $state) {
      return match ($state) {
          'accepted'  => ['bg-success',           'fa-check-circle'],
          'corrected' => ['bg-primary',           'fa-pen'],
          'rejected'  => ['bg-danger',            'fa-times-circle'],
          default     => ['bg-secondary',         'fa-hourglass-half'],
      };
  };
@endphp

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-robot me-2"></i>{{ __('AI inference provenance') }}</h1>
  <p class="text-muted mb-0">
    {{ __('An honest, read-only record of which automated AI steps contributed to this published record\'s metadata - the model, the gateway it ran through, when it ran, and whether a human curator reviewed the result. This page describes what was recorded; it does not claim any AI output is correct.') }}
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
    <i class="fas fa-stream me-2"></i>{{ __('What AI contributed to this record') }}
  </div>
  <div class="card-body">
    <p class="mb-0">{{ $report['summary'] }}</p>
  </div>
</div>

@if(!$hasAny)
  {{-- Dignified empty state: not an error, just nothing recorded. --}}
  <div class="card mb-3">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-robot fa-2x mb-3 d-block" aria-hidden="true"></i>
      <p class="mb-1">{{ __('No AI inference recorded for this record.') }}</p>
      <p class="small mb-0">
        @if(!$report['available'])
          {{ __('This system has no AI inference provenance store configured, so no automated steps can be shown.') }}
        @else
          {{ __('Its metadata was either entered by hand or pre-dates inference logging. That does not mean anything is wrong - only that no automated step is on file for it.') }}
        @endif
      </p>
    </div>
  </div>
@else
  {{-- At-a-glance counts. --}}
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold">{{ $counts['total'] }}</div>
        <div class="small text-muted">{{ trans_choice('{1}AI inference|[2,*]AI inferences', $counts['total']) }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold">{{ $counts['models'] }}</div>
        <div class="small text-muted">{{ trans_choice('{1}model used|[2,*]models used', $counts['models']) }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold text-success">{{ $counts['reviewed'] }}</div>
        <div class="small text-muted">{{ __('human-reviewed') }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center"><div class="card-body">
        <div class="fs-3 fw-bold {{ $counts['pending'] > 0 ? 'text-secondary' : '' }}">{{ $counts['pending'] }}</div>
        <div class="small text-muted">{{ __('awaiting review') }}</div>
      </div></div>
    </div>
  </div>

  {{-- By-service breakdown. --}}
  @if(!empty($byService))
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-layer-group me-2"></i>{{ __('By AI service') }}</div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          @foreach($byService as $service => $n)
            <span class="badge bg-light text-dark border">{{ $service }} <span class="badge bg-secondary ms-1">{{ $n }}</span></span>
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
          <li class="mb-1">{{ __('Which AI service and model produced each contribution, and the gateway it ran through.') }}</li>
          <li class="mb-1">{{ __('When each inference ran, and who triggered it.') }}</li>
          <li class="mb-1">{{ __('Whether a human curator kept, corrected, or rejected the AI output - keeping a person accountable.') }}</li>
        </ul></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 border-secondary">
        <div class="card-header bg-secondary text-white"><i class="fas fa-ban me-2"></i>{{ __('What this page does not claim') }}</div>
        <div class="card-body"><ul class="mb-0">
          <li class="mb-1">{{ __('That any AI output is correct, complete, or true - only that it was recorded.') }}</li>
          <li class="mb-1">{{ __('Anything about the underlying source itself, beyond the automated steps applied to its metadata.') }}</li>
          <li class="mb-1">{{ __('AI suggestions that are still awaiting human review are clearly marked as such, never as verified.') }}</li>
        </ul></div>
      </div>
    </div>
  </div>

  {{-- The per-inference timeline. --}}
  <div class="card mb-3">
    <div class="card-header"><i class="fas fa-list-ol me-2"></i>{{ __('Recorded AI inferences') }} <span class="text-muted">({{ __('newest first') }})</span></div>
    <div class="list-group list-group-flush">
      @foreach($inferences as $inf)
        @php [$pillCls, $pillIcon] = $reviewPill($inf['review_state']); @endphp
        <div class="list-group-item">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
            <div>
              <span class="fw-semibold">{{ $inf['service'] }}</span>
              <span class="text-muted">&middot; {{ $inf['field'] }}</span>
            </div>
            <span class="badge {{ $pillCls }}"><i class="fas {{ $pillIcon }} me-1"></i>{{ $inf['review_label'] }}</span>
          </div>
          <div class="small text-muted">
            <span class="me-3"><i class="fas fa-microchip me-1"></i>{{ $inf['model'] }}@if($inf['model_version']) <span class="text-body-secondary">{{ $inf['model_version'] }}</span>@endif</span>
            @if($inf['gateway'])<span class="me-3"><i class="fas fa-network-wired me-1"></i>{{ $inf['gateway'] }}</span>@endif
            @if($inf['confidence'] !== null)<span class="me-3"><i class="fas fa-percentage me-1"></i>{{ __('model confidence') }} {{ $inf['confidence'] }}%</span>@endif
            @if($inf['standard'])<span class="me-3"><i class="fas fa-book me-1"></i>{{ $inf['standard'] }}</span>@endif
            @if($inf['occurred_at'])<span class="me-3"><i class="far fa-clock me-1"></i>{{ $inf['occurred_at'] }}</span>@endif
            @if($inf['triggered_by'])<span class="me-3"><i class="fas fa-user me-1"></i>{{ __('triggered by') }} {{ $inf['triggered_by'] }}</span>@endif
          </div>
          @if($inf['review_state'] !== 'pending' && ($inf['reviewer'] || $inf['review_reason']))
            <div class="small mt-1">
              <i class="fas fa-user-check me-1 text-success"></i>
              @if($inf['reviewer']){{ __('Reviewed by') }} <span class="fw-semibold">{{ $inf['reviewer'] }}</span>@endif
              @if($inf['reviewed_at']) <span class="text-muted">({{ $inf['reviewed_at'] }})</span>@endif
              @if($inf['review_reason']) <span class="text-muted">&mdash; {{ $inf['review_reason'] }}</span>@endif
            </div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
@endif

{{-- Where to go deeper + the machine-readable companion. --}}
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <a class="btn btn-primary btn-sm" href="{{ url('/trust-dossier/' . (($object->slug ?? null) ?: $object->id)) }}">
        <i class="fas fa-folder-tree me-1"></i>{{ __('See the full trust dossier') }}
      </a>
      <a class="btn btn-outline-primary btn-sm" href="{{ $report['authenticity_url'] }}">
        <i class="fas fa-certificate me-1"></i>{{ __('See the full authenticity report') }}
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ $report['report_json_url'] }}" rel="noopener">
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
  {{ __('AI inference provenance follows the discipline that every automated metadata contribution is recorded with its model, gateway, and timing, and that a human curator remains accountable for accepting, correcting, or rejecting it. This page is a read-only view of that record - it does not, and cannot, attest that any AI output is itself correct.') }}
  @if(!empty($report['generated_at'])) <span class="ms-1">{{ __('Generated') }} {{ $report['generated_at'] }}.</span>@endif
</p>
@endsection
