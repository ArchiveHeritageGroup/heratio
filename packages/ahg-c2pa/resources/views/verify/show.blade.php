{{--
  Heratio - public "verify authenticity" trust-anchor page (issue #1209).

  Read-only, plain-language. Reuses ProvenanceRecordService verification - it
  does not reimplement signing/verify.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Verify authenticity'))
@section('body-class', 'c2pa verify')

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-shield-alt me-2"></i>{{ __('Verify authenticity') }}</h1>
  <p class="text-muted mb-0">
    {{ __('Heratio is the truth anchor for this record. This page checks the digitisation provenance against its cryptographic signature, live, every time you load it.') }}
  </p>
</div>

@if(!$object)
  {{-- Object / slug did not resolve. --}}
  <div class="card mb-3">
    <div class="card-body">
      <p class="mb-1">
        <span class="badge bg-secondary fs-6"><i class="fas fa-question-circle me-1"></i>{{ __('Not found') }}</span>
      </p>
      <p class="mb-0">{{ __('We could not find a record matching') }} <code>{{ $reference }}</code>.
        {{ __('Check the link and try again.') }}</p>
    </div>
  </div>
@else
  {{-- Identity of the record being verified. --}}
  <div class="card mb-3">
    <div class="card-header"><i class="fas fa-file-alt me-2"></i>{{ __('Record') }}</div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('Title') }}</dt>
        <dd class="col-sm-9">{{ $object->title ?? __('(untitled)') }}</dd>
        <dt class="col-sm-3">{{ __('Reference code') }}</dt>
        <dd class="col-sm-9">{{ $object->identifier ?? '-' }}</dd>
        @if(!empty($object->slug))
          <dt class="col-sm-3">{{ __('Permalink') }}</dt>
          <dd class="col-sm-9"><a href="{{ url($object->slug) }}">{{ url($object->slug) }}</a></dd>
        @endif
      </dl>
    </div>
  </div>

  {{-- Overall verdict in plain language. --}}
  @php
    $total    = $summary['total'] ?? 0;
    $verified = $summary['verified'] ?? 0;
    $tampered = $summary['tampered'] ?? 0;
    $signed   = $summary['signed'] ?? 0;

    if ($total === 0) {
        $overall = ['bg-secondary', 'fa-minus-circle', __('No provenance on record')];
    } elseif ($verified > 0 && $tampered === 0) {
        $overall = ['bg-success', 'fa-check-circle', __('Authenticity verified')];
    } elseif ($tampered > 0) {
        $overall = ['bg-danger', 'fa-times-circle', __('Could not be verified')];
    } else {
        $overall = ['bg-secondary', 'fa-minus-circle', __('Documented, not signed')];
    }
  @endphp

  <div class="card mb-3 border-2" style="border-color:var(--ahg-primary)">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-certificate me-2"></i>{{ __('Authenticity verdict') }}
    </div>
    <div class="card-body">
      <p class="mb-2">
        <span class="badge {{ $overall[0] }} fs-5"><i class="fas {{ $overall[1] }} me-1"></i>{{ $overall[2] }}</span>
      </p>
      @if($total === 0)
        <p class="mb-0">{{ __('This record has no digitisation provenance on file yet, so there is nothing to verify. That does not mean it is fake - only that no content credentials have been recorded for it.') }}</p>
      @elseif($verified > 0 && $tampered === 0)
        <p class="text-success mb-0"><i class="fas fa-lock me-1"></i>
          {{ __("This record's digitisation is cryptographically verified. The signed content credentials below have not been altered since they were created.") }}
        </p>
      @elseif($tampered > 0)
        <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>
          {{ __('One or more content credentials for this record could not be verified. The signature does not match the recorded content - treat the affected entries below with caution.') }}
        </p>
      @else
        <p class="text-muted mb-0">{{ __('This record has documented digitisation provenance, but it is not cryptographically signed on this install, so authenticity cannot be proven by signature.') }}</p>
      @endif

      @if($total > 0)
        <p class="text-muted small mt-2 mb-0">
          {{ trans_choice('{1}:count provenance record|[2,*]:count provenance records', $total, ['count' => $total]) }}
          @if($signed > 0) - {{ $verified }}/{{ $signed }} {{ __('signed credentials verify') }}@endif.
        </p>
      @endif
    </div>
  </div>

  {{-- The chain itself: one card per provenance record. --}}
  @foreach($chain as $link)
    @php
      $record = $link['record'];
      $v = $link['verification'];
      $badge = match($v['status'] ?? '') {
          'verified' => ['bg-success', 'fa-check-circle', __('Verified')],
          'unsigned' => ['bg-secondary', 'fa-minus-circle', __('Unsigned')],
          default    => ['bg-danger', 'fa-times-circle', __('Could not be verified')],
      };
    @endphp
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-camera me-2"></i>{{ __('Digitisation') }} #{{ $record->id }}</span>
        <span class="badge {{ $badge[0] }}"><i class="fas {{ $badge[1] }} me-1"></i>{{ $badge[2] }}</span>
      </div>
      <div class="card-body">
        @if(($v['status'] ?? '') === 'verified')
          <p class="text-success small mb-3"><i class="fas fa-lock me-1"></i>
            {{ __('Every detail below re-computes to the value pinned in the signed claim, and the Ed25519 signature checks out.') }}
            @if(!empty($v['kid']))<span class="text-muted">{{ __('Signed under key') }} <code>{{ $v['kid'] }}</code>.</span>@endif
          </p>
        @elseif(($v['status'] ?? '') === 'unsigned')
          <p class="text-muted small mb-3">{{ __('This entry documents the digitisation but carries no cryptographic signature.') }}</p>
        @else
          <p class="text-danger small mb-3"><i class="fas fa-exclamation-triangle me-1"></i>
            {{ __('Verification failed for this entry:') }}
            @if(!empty($v['errors'])) {{ implode('; ', $v['errors']) }}.@endif
          </p>
        @endif

        <dl class="row mb-0">
          <dt class="col-sm-3">{{ __('Digitised by') }}</dt>
          <dd class="col-sm-9">{{ $record->captured_by ?? '-' }}</dd>
          <dt class="col-sm-3">{{ __('Date') }}</dt>
          <dd class="col-sm-9">{{ $record->captured_at ?? '-' }}</dd>
          <dt class="col-sm-3">{{ __('Device') }}</dt>
          <dd class="col-sm-9">{{ $record->capture_device ?? '-' }}</dd>
          <dt class="col-sm-3">{{ __('Software') }}</dt>
          <dd class="col-sm-9">{{ $record->capture_software ?? '-' }}</dd>
          @if(!empty($record->asset_sha256))
            <dt class="col-sm-3">{{ __('Content fingerprint') }}</dt>
            <dd class="col-sm-9"><code class="small">{{ $record->asset_sha256 }}</code></dd>
          @endif
        </dl>

        @if(!empty($link['inference_steps']))
          <hr>
          <h2 class="h6"><i class="fas fa-robot me-2"></i>{{ __('AI processing steps') }}</h2>
          <p class="text-muted small">{{ __('Automated steps that have touched this asset. Each is part of the signed chain.') }}</p>
          <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Step') }}</th><th>{{ __('Model') }}</th><th>{{ __('Version') }}</th></tr></thead>
            <tbody>
              @foreach($link['inference_steps'] as $s)
                <tr>
                  <td>{{ $s['step'] ?? '-' }}</td>
                  <td>{{ $s['model_id'] ?? '-' }}</td>
                  <td>{{ $s['model_version'] ?? '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
    </div>
  @endforeach

  <p class="text-muted small">
    <i class="fas fa-info-circle me-1"></i>
    {{ __('Content credentials follow the C2PA open standard. Signatures are Ed25519 and re-checked live on every page load - nothing here is cached.') }}
  </p>
@endif
@endsection
