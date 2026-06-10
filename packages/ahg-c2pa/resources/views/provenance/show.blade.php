{{--
  Heratio - verify + display a C2PA provenance record's content credentials (#1201).

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Content Credentials'))
@section('body-class', 'admin c2pa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-shield-alt me-2"></i>{{ __('Content Credentials') }}</h1>
  <a href="{{ route('c2pa.provenance.index', ['informationObjectId' => $informationObjectId]) }}" class="btn btn-sm atom-btn-white">
    <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
  </a>
</div>

@if(!$record)
  <div class="alert alert-danger">{{ __('Provenance record not found.') }}</div>
@else
  {{-- Verification verdict --}}
  @php
    $v = $verification;
    $badge = match($v['status']) {
        'verified' => ['bg-success', 'fa-check-circle', __('Verified')],
        'unsigned' => ['bg-secondary', 'fa-minus-circle', __('Unsigned')],
        default    => ['bg-danger', 'fa-times-circle', __('Verification failed')],
    };
  @endphp
  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-certificate me-2"></i>{{ __('Authenticity') }}
    </div>
    <div class="card-body">
      <p class="mb-2">
        <span class="badge {{ $badge[0] }} fs-6"><i class="fas {{ $badge[1] }} me-1"></i>{{ $badge[2] }}</span>
        @if($v['kid'])
          <span class="text-muted ms-2">{{ __('Signed under key') }} <code>{{ $v['kid'] }}</code> (Ed25519)</span>
        @endif
      </p>
      @if($v['ok'])
        <p class="text-success mb-0"><i class="fas fa-lock me-1"></i>
          {{ __('Every assertion re-hashes to the value pinned in the signed claim, and the Ed25519 claim signature verifies. This content credential has not been tampered with.') }}
        </p>
      @elseif($v['status'] === 'unsigned')
        <p class="text-muted mb-0">{{ __('This record has no signed manifest. It documents the digitisation but carries no cryptographic authenticity proof.') }}</p>
      @else
        <ul class="text-danger mb-0">
          @foreach($v['errors'] as $err)<li>{{ $err }}</li>@endforeach
        </ul>
      @endif
      <p class="text-muted small mt-2 mb-0">
        <i class="fas fa-info-circle me-1"></i>{{ $capability['summary'] }}
      </p>
    </div>
  </div>

  {{-- Capture provenance --}}
  <div class="card mb-3">
    <div class="card-header"><i class="fas fa-camera me-2"></i>{{ __('Digitisation provenance') }}</div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('Captured by') }}</dt><dd class="col-sm-9">{{ $record->captured_by ?? '-' }}</dd>
        <dt class="col-sm-3">{{ __('Captured at') }}</dt><dd class="col-sm-9">{{ $record->captured_at ?? '-' }}</dd>
        <dt class="col-sm-3">{{ __('Capture device') }}</dt><dd class="col-sm-9">{{ $record->capture_device ?? '-' }}</dd>
        <dt class="col-sm-3">{{ __('Capture software') }}</dt><dd class="col-sm-9">{{ $record->capture_software ?? '-' }}</dd>
        <dt class="col-sm-3">{{ __('Digital object') }}</dt><dd class="col-sm-9">{{ $record->digital_object_id ? ('#' . $record->digital_object_id) : __('whole record') }}</dd>
        <dt class="col-sm-3">{{ __('Asset SHA-256') }}</dt><dd class="col-sm-9"><code class="small">{{ $record->asset_sha256 ?? '-' }}</code></dd>
        <dt class="col-sm-3">{{ __('Notes') }}</dt><dd class="col-sm-9">{{ $record->notes ?? '-' }}</dd>
        <dt class="col-sm-3">{{ __('Recorded') }}</dt><dd class="col-sm-9">{{ $record->created_at }}</dd>
      </dl>
    </div>
  </div>

  {{-- AI inference chain (#61) --}}
  @if(!empty($inferenceSteps))
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-robot me-2"></i>{{ __('AI inference steps') }}</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <thead><tr><th>{{ __('Step') }}</th><th>{{ __('Model') }}</th><th>{{ __('Version') }}</th><th>{{ __('Output SHA-256') }}</th></tr></thead>
          <tbody>
            @foreach($inferenceSteps as $s)
              <tr>
                <td>{{ $s['step'] ?? '-' }}</td>
                <td>{{ $s['model_id'] ?? '-' }}</td>
                <td>{{ $s['model_version'] ?? '-' }}</td>
                <td><code class="small">{{ $s['output_sha256'] ?? '-' }}</code></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  {{-- Raw manifest --}}
  @if($record->manifest_id)
    <div class="mb-3">
      <a href="{{ route('c2pa.provenance.manifest', ['informationObjectId' => $informationObjectId, 'provenanceId' => $record->id]) }}"
         class="btn btn-sm atom-btn-white" target="_blank" rel="noopener">
        <i class="fas fa-file-code me-1"></i>{{ __('View signed manifest JSON') }}
      </a>
    </div>
  @endif
@endif
@endsection
