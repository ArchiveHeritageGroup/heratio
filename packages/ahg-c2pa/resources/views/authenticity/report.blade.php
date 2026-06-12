{{--
  Heratio - public per-record AUTHENTICITY REPORT page (issue #1209, north star).

  The truth anchor's per-record front door. CONSOLIDATES the verification
  signals that already exist for one published archival record - content
  credentials / C2PA signing, the whole-record provenance verdict, and
  AI-inference provenance - into a single, honest, plain-language report with a
  "what we can and cannot verify" statement and a confidence tier that is never
  overclaimed. Read-only, public. Reuses AuthenticityReportService (which reuses
  ProvenanceTraceService -> ProvenanceRecordService); it reimplements no
  verification. International copy (no jurisdiction assumptions).

  Confidence tiers (see AuthenticityReportService):
    high    -> green  : signed content credentials that verify live
    partial -> blue   : some signed + verified, some unsigned (no failures)
    low     -> amber  : provenance recorded but nothing is signed here
    broken  -> red    : a signed entry failed verification (possible tampering)
    none    -> neutral : no authenticity signals recorded yet (dignified empty)

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Authenticity report'))
@section('body-class', 'c2pa authenticity authenticity-report')

@php
  $object  = $report['object'];
  $signals = $report['signals'];
  $conf    = $report['confidence'];

  // Confidence badge styling - derived from the real verdict, never assumed.
  $confBadge = match($conf) {
      'high'    => ['bg-success',                 'fa-shield-alt',    __('High confidence')],
      'partial' => ['bg-primary',                 'fa-shield-alt',    __('Partial confidence')],
      'low'     => ['bg-warning text-dark',       'fa-file-signature',__('Recorded, unsigned')],
      'broken'  => ['bg-danger',                  'fa-exclamation-triangle', __('Verification failed')],
      default   => ['bg-light text-dark border',  'fa-minus-circle',  __('No signals yet')],
  };

  // Per-signal state pill.
  $signalPill = function (bool $ok, ?bool $warn = false) {
      if ($warn) return ['bg-danger', 'fa-times-circle'];
      return $ok ? ['bg-success', 'fa-check-circle'] : ['bg-secondary', 'fa-minus-circle'];
  };

  $cc = $signals['content_credentials'];
  $pv = $signals['provenance'];
  $ai = $signals['ai_inference'];

  $ccPill = match($cc['state']) {
      'verified' => ['bg-success', 'fa-check-circle', __('Signed and verified')],
      'invalid'  => ['bg-danger',  'fa-times-circle', __('Signature failed')],
      default    => ['bg-secondary','fa-minus-circle', __('None signed')],
  };
@endphp

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-certificate me-2"></i>{{ __('Authenticity report') }}</h1>
  <p class="text-muted mb-0">
    {{ __('An honest summary of what can and cannot be verified about this primary source, drawn from the content credentials and signed provenance recorded for it. Signatures are re-checked live every time you load this page - nothing here is cached.') }}
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
      <dt class="col-sm-3">{{ __('Digital files') }}</dt>
      <dd class="col-sm-9">{{ $pv['digital_objects'] }}</dd>
    </dl>
  </div>
</div>

{{-- The headline verdict + confidence. The honest framing core. --}}
<div class="card mb-3 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-balance-scale me-2"></i>{{ __('Overall authenticity confidence') }}
  </div>
  <div class="card-body">
    <p class="mb-2">
      <span class="badge {{ $confBadge[0] }} fs-5"><i class="fas {{ $confBadge[1] }} me-1"></i>{{ $confBadge[2] }}</span>
    </p>
    <p class="mb-0">{{ $report['summary'] }}</p>
  </div>
</div>

@if($conf === 'none')
  {{-- Dignified empty state: not an error, just nothing recorded yet. --}}
  <div class="card mb-3">
    <div class="card-body text-center text-muted py-5">
      <i class="fas fa-certificate fa-2x mb-3 d-block" aria-hidden="true"></i>
      <p class="mb-1">{{ __('No authenticity signals have been recorded for this record yet.') }}</p>
      <p class="small mb-0">{{ __('That does not mean anything is wrong - only that no content credentials or signed provenance have been captured for it so far.') }}</p>
    </div>
  </div>
@endif

{{-- The three consolidated signals, each honest about its own state. --}}
<div class="row g-3 mb-3">
  {{-- 1. Content credentials / C2PA signing. --}}
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-stamp me-2"></i>{{ __('Content credentials') }}</div>
      <div class="card-body">
        <p class="mb-2">
          <span class="badge {{ $ccPill[0] }}"><i class="fas {{ $ccPill[1] }} me-1"></i>{{ $ccPill[2] }}</span>
        </p>
        <p class="small text-muted mb-0">
          @if($cc['signed'] > 0)
            {{ trans_choice('{1}:count signed credential|[2,*]:count signed credentials', $cc['signed'], ['count' => $cc['signed']]) }},
            {{ $cc['verified'] }}/{{ $cc['signed'] }} {{ __('verify live') }}.
            @if($cc['invalid'] > 0)
              <span class="text-danger d-block mt-1"><i class="fas fa-exclamation-triangle me-1"></i>{{ trans_choice('{1}:count failed verification|[2,*]:count failed verification', $cc['invalid'], ['count' => $cc['invalid']]) }}.</span>
            @endif
          @else
            {{ __('No cryptographically signed content credentials on this record.') }}
          @endif
        </p>
      </div>
    </div>
  </div>

  {{-- 2. Provenance verification verdict. --}}
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-stream me-2"></i>{{ __('Provenance') }}</div>
      <div class="card-body">
        @php
          [$pvCls, $pvIcon] = $signalPill($pv['records'] > 0, $conf === 'broken');
        @endphp
        <p class="mb-2">
          <span class="badge {{ $pvCls }}"><i class="fas {{ $pvIcon }} me-1"></i>{{ $report['confidence_label'] }}</span>
        </p>
        <p class="small text-muted mb-0">
          @if($pv['records'] > 0)
            {{ trans_choice('{1}:count provenance record|[2,*]:count provenance records', $pv['records'], ['count' => $pv['records']]) }}
            {{ __('across') }} {{ trans_choice('{1}:count digital file|[2,*]:count digital files', $pv['digital_objects'], ['count' => $pv['digital_objects']]) }}.
          @else
            {{ __('No provenance has been recorded for this record yet.') }}
          @endif
        </p>
      </div>
    </div>
  </div>

  {{-- 3. AI-inference provenance. --}}
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-robot me-2"></i>{{ __('AI processing') }}</div>
      <div class="card-body">
        @php [$aiCls, $aiIcon] = $signalPill($ai['present']); @endphp
        <p class="mb-2">
          <span class="badge {{ $aiCls }}"><i class="fas {{ $aiIcon }} me-1"></i>{{ $ai['present'] ? __('Recorded') : __('None recorded') }}</span>
        </p>
        <p class="small text-muted mb-0">
          @if($ai['present'])
            {{ trans_choice('{1}:count automated AI step is recorded in this record\'s provenance.|[2,*]:count automated AI steps are recorded in this record\'s provenance.', $ai['count'], ['count' => $ai['count']]) }}
          @else
            {{ __('No automated AI processing steps are recorded for this record.') }}
          @endif
        </p>
      </div>
    </div>
  </div>
</div>

{{-- The honest "what we can / cannot verify" statement. Always present. --}}
<div class="row g-3 mb-3">
  @if(!empty($report['can_verify']))
    <div class="col-md-6">
      <div class="card h-100 border-success">
        <div class="card-header bg-success text-white">
          <i class="fas fa-check me-2"></i>{{ __('What we can verify') }}
        </div>
        <div class="card-body">
          <ul class="mb-0">
            @foreach($report['can_verify'] as $line)
              <li class="mb-1">{{ $line }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  @endif

  <div class="col-md-{{ empty($report['can_verify']) ? '12' : '6' }}">
    <div class="card h-100 border-secondary">
      <div class="card-header bg-secondary text-white">
        <i class="fas fa-ban me-2"></i>{{ __('What we cannot verify') }}
      </div>
      <div class="card-body">
        <ul class="mb-0">
          @foreach($report['cannot_verify'] as $line)
            <li class="mb-1">{{ $line }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</div>

{{-- Where to go deeper + the machine-readable companion. --}}
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <a class="btn btn-primary btn-sm" href="{{ url('/trust-dossier/' . (($object->slug ?? null) ?: $object->id)) }}">
        <i class="fas fa-folder-tree me-1"></i>{{ __('See the full trust dossier') }}
      </a>
      <a class="btn btn-outline-primary btn-sm" href="{{ $report['trace_url'] }}">
        <i class="fas fa-stream me-1"></i>{{ __('See the full provenance trace') }}
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="{{ $report['trace_json_url'] }}" rel="noopener">
        <i class="fas fa-database me-1"></i>{{ __('Trace as JSON') }}
      </a>
      @if(!empty($object->slug))
        <a class="btn btn-outline-secondary btn-sm" href="{{ url($object->slug) }}">
          <i class="fas fa-file-alt me-1"></i>{{ __('View the record') }}
        </a>
      @endif
    </div>
  </div>
</div>

{{-- Embeddable trust badge (this record can display it). --}}
@if(!empty($report['badge_url']))
  <div class="card mb-3">
    <div class="card-header"><i class="fas fa-code me-2"></i>{{ __('Embeddable trust badge') }}</div>
    <div class="card-body">
      <p class="mb-2">
        <img src="{{ $report['badge_url'] }}" alt="{{ __('Authenticity badge') }}" height="20">
      </p>
      <p class="small text-muted mb-1">{{ __('Embed this live badge on another page; it links readers back here:') }}</p>
      <pre class="small bg-light border rounded p-2 mb-0"><code>&lt;a href="{{ url()->current() }}"&gt;&lt;img src="{{ $report['badge_url'] }}" alt="Authenticity"&gt;&lt;/a&gt;</code></pre>
    </div>
  </div>
@endif

<p class="text-muted small">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('Content credentials follow the C2PA open standard. Signatures are Ed25519 and re-checked live on every page load. This report describes the verifiable history of the digital files - it does not, and cannot, attest to whether what the source depicts is itself true.') }}
  @if(!empty($report['generated_at'])) <span class="ms-1">{{ __('Generated') }} {{ $report['generated_at'] }}.</span>@endif
</p>
@endsection
