{{--
  Heratio - public per-digital-object content-credentials detail page
  (issue #1209 truth anchor / #1201 provenance-authenticity layer).

  Renders one digital object's content-credentials chain in plain language and
  offers an "embed this" verify badge so authenticity travels with the object.
  Read-only. Reuses ProvenanceRecordService verification - it does not
  reimplement signing/verify. International copy (no jurisdiction assumptions).

  Three states (see VerifyObjectController):
    verified -> green  : signed AND the Ed25519 signature + hashes check out
    invalid  -> red    : signed but a signature/hash failed (treat with caution)
    absent   -> neutral: no content credentials recorded/signed for this object

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Content credentials'))
@section('body-class', 'c2pa verify verify-object')

@php
  $stateBadge = match($state) {
      'verified' => ['bg-success', 'fa-check-circle', __('Content credentials verified')],
      'invalid'  => ['bg-danger',  'fa-times-circle', __('Could not be verified')],
      default    => ['bg-secondary','fa-minus-circle', __('No content credentials')],
  };
@endphp

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-shield-alt me-2"></i>{{ __('Content credentials') }}</h1>
  <p class="text-muted mb-0">
    {{ __('This page checks one file\'s content credentials against its cryptographic signature, live, every time you load it. It follows the C2PA open standard so the result is independently verifiable.') }}
  </p>
</div>

{{-- Identity of the file being verified. --}}
<div class="card mb-3">
  <div class="card-header"><i class="fas fa-file-alt me-2"></i>{{ __('File') }}</div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">{{ __('File') }}</dt>
      <dd class="col-sm-9">{{ $object->name ?? __('(unnamed file)') }}</dd>
      @if(!empty($object->mime_type))
        <dt class="col-sm-3">{{ __('Format') }}</dt>
        <dd class="col-sm-9"><code>{{ $object->mime_type }}</code></dd>
      @endif
      @if(!empty($object->io_title))
        <dt class="col-sm-3">{{ __('Record') }}</dt>
        <dd class="col-sm-9">{{ $object->io_title }}</dd>
      @endif
      @if(!empty($object->io_ref))
        <dt class="col-sm-3">{{ __('Reference code') }}</dt>
        <dd class="col-sm-9">{{ $object->io_ref }}</dd>
      @endif
      @if(!empty($object->io_slug))
        <dt class="col-sm-3">{{ __('Permalink') }}</dt>
        <dd class="col-sm-9"><a href="{{ url($object->io_slug) }}">{{ url($object->io_slug) }}</a></dd>
      @endif
    </dl>
  </div>
</div>

{{-- Overall verdict. --}}
<div class="card mb-3 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-certificate me-2"></i>{{ __('Verdict') }}
  </div>
  <div class="card-body">
    <p class="mb-2">
      <span class="badge {{ $stateBadge[0] }} fs-5"><i class="fas {{ $stateBadge[1] }} me-1"></i>{{ $stateBadge[2] }}</span>
    </p>
    @if($state === 'verified')
      <p class="text-success mb-0"><i class="fas fa-lock me-1"></i>
        {{ __('This file carries signed content credentials, and they have not been altered since they were created. Every step below re-computes to the value pinned in the signed claim, and the Ed25519 signature checks out.') }}
      </p>
      @if(!empty($signer))
        <p class="text-muted small mt-2 mb-0">{{ __('Signed under key') }} <code>{{ $signer }}</code>@if(!empty($signedAt)) - {{ __('most recently on') }} {{ $signedAt }}@endif.</p>
      @endif
    @elseif($state === 'invalid')
      <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>
        {{ __('This file has content credentials, but one or more of them could not be verified. The signature does not match the recorded content - treat the affected steps below with caution.') }}
      </p>
    @else
      <p class="text-muted mb-0">
        {{ __('This file has no content credentials on record. That does not mean it is fake - only that no signed provenance has been recorded for it yet.') }}
      </p>
    @endif

    @if(($counts['records'] ?? 0) > 0)
      <p class="text-muted small mt-2 mb-0">
        {{ trans_choice('{1}:count provenance record|[2,*]:count provenance records', $counts['records'], ['count' => $counts['records']]) }}
        @if(($counts['signed'] ?? 0) > 0) - {{ $counts['verified'] }}/{{ $counts['signed'] }} {{ __('signed verify') }}@endif.
      </p>
    @endif
  </div>
</div>

{{-- Download with content credentials (issue #1201): the file leaves Heratio
     carrying its provenance - embedded in the bytes where the format allows,
     otherwise alongside a signed sidecar manifest. Only shown when a master is
     actually on disk. --}}
@if(!empty($downloadable) && !empty($downloadUrl))
  <div class="card mb-3">
    <div class="card-header"><i class="fas fa-download me-2"></i>{{ __('Take the credentials with you') }}</div>
    <div class="card-body">
      <p class="text-muted small mb-2">
        {{ __('Download a copy of this file with its content credentials attached, so its provenance can be verified anywhere - not just here. Where the file format supports it, the credentials are embedded into the file itself; otherwise they travel alongside it as a signed manifest.') }}
      </p>
      <a href="{{ $downloadUrl }}" class="btn btn-primary btn-sm" rel="nofollow">
        <i class="fas fa-shield-alt me-1"></i>{{ __('Download with content credentials') }}
      </a>
    </div>
  </div>
@endif

{{-- The chain itself: one card per provenance record, with its signed steps. --}}
@foreach($chain as $link)
  @php
    $record = $link['record'];
    $entryBadge = match($link['entry_state'] ?? 'absent') {
        'verified' => ['bg-success', 'fa-check-circle', __('Verified')],
        'invalid'  => ['bg-danger',  'fa-times-circle', __('Could not be verified')],
        default    => ['bg-secondary','fa-minus-circle', __('Unsigned')],
    };
    $v = $link['verification'] ?? [];
  @endphp
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-camera me-2"></i>{{ __('Provenance entry') }} #{{ $record->id }}</span>
      <span class="badge {{ $entryBadge[0] }}"><i class="fas {{ $entryBadge[1] }} me-1"></i>{{ $entryBadge[2] }}</span>
    </div>
    <div class="card-body">
      @if(($link['entry_state'] ?? '') === 'invalid' && !empty($v['errors']))
        <p class="text-danger small mb-3"><i class="fas fa-exclamation-triangle me-1"></i>
          {{ __('Verification failed:') }} {{ implode('; ', $v['errors']) }}.
        </p>
      @endif

      <dl class="row mb-3">
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

      @if(!empty($link['assertions']))
        <h2 class="h6"><i class="fas fa-list-ol me-2"></i>{{ __('Signed steps') }}</h2>
        <p class="text-muted small">{{ __('The actions and declarations recorded in the signed manifest, in order.') }}</p>
        <ol class="mb-0">
          @foreach($link['assertions'] as $step)
            <li class="mb-2">
              @php
                $icon = match($step['kind'] ?? 'action') {
                    'ai'              => 'fa-robot',
                    'training-mining' => 'fa-balance-scale',
                    'metadata'        => 'fa-tags',
                    default           => 'fa-pen',
                };
              @endphp
              <i class="fas {{ $icon }} me-1 text-muted" aria-hidden="true"></i>
              <span class="fw-semibold">{{ $step['summary'] ?? ($step['label'] ?? '-') }}</span>
              @if(!empty($step['software'])) <span class="text-muted">- {{ __('via') }} {{ $step['software'] }}</span>@endif
              @if(!empty($step['when'])) <span class="text-muted small">({{ $step['when'] }})</span>@endif
              @if(!empty($step['params']))
                <ul class="small text-muted mb-0">
                  @foreach($step['params'] as $k => $val)
                    <li><span class="text-uppercase" style="font-size:.7rem">{{ $k }}</span>: <code>{{ $val }}</code></li>
                  @endforeach
                </ul>
              @endif
            </li>
          @endforeach
        </ol>
      @endif

      @if(!empty($link['inference_steps']))
        <hr>
        <h2 class="h6"><i class="fas fa-robot me-2"></i>{{ __('AI processing steps') }}</h2>
        <p class="text-muted small">{{ __('Automated steps that have touched this file. Each is part of the signed chain.') }}</p>
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

{{-- Embed this verify badge. --}}
@php
  $imgTag = '<a href="' . e($verifyUrl) . '"><img src="' . e($badgeSvg) . '" alt="Content Credentials" /></a>';
@endphp
<div class="card mb-3">
  <div class="card-header"><i class="fas fa-code me-2"></i>{{ __('Embed this badge') }}</div>
  <div class="card-body">
    <p class="text-muted small">{{ __('Add this badge to any page so the authenticity of this file can be checked from there. The badge reflects the live status and links back here.') }}</p>
    <p class="mb-2">{!! $imgTag !!}</p>
    <label class="form-label small mb-1">{{ __('HTML') }}</label>
    <textarea class="form-control form-control-sm font-monospace mb-3" rows="2" readonly onclick="this.select()">{{ $imgTag }}</textarea>
    <p class="small mb-1">
      <a href="{{ $badgeSvg }}" rel="noopener"><i class="fas fa-image me-1"></i>{{ __('SVG badge') }}</a>
      &middot;
      <a href="{{ $badgeJson }}" rel="noopener"><i class="fas fa-database me-1"></i>{{ __('JSON status') }}</a>
    </p>
    <p class="text-muted small mb-0">{{ __('The JSON endpoint is CORS-open and read-only, for programmatic checks.') }}</p>
  </div>
</div>

<p class="text-muted small">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('Content credentials follow the C2PA open standard. Signatures are Ed25519 and re-checked live on every page load - nothing here is cached.') }}
</p>
@endsection
