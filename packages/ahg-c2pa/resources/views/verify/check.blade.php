{{--
  Heratio - public "check content credentials" tool (deepens #1209 / #1201).

  A visitor drops or browses for ANY image - including files that did NOT come
  from this repository - and gets its C2PA content-credentials verdict in plain
  language. Read-only, no login, no DB writes, the upload is never persisted.

  Reuses the package verifier (C2paService::verify) via PublicCheckController; it
  does not reimplement signing/verification or shell out to c2patool. The result
  panel reuses the same plain-language chain styling as the per-object verify
  page (verify/object.blade.php). International copy (no jurisdiction
  assumptions).

  Three states (see PublicCheckController):
    verified -> green   : a manifest was found AND its hashes + Ed25519 claim
                          signature check out
    invalid  -> red     : a manifest was found but a hash/signature failed
                          (tampered / unknown signer)
    absent   -> neutral : no content credentials could be read from the file
                          (NOT an error - the common answer for an ordinary photo)

  Progressive enhancement: the drop zone is a normal multipart form with a file
  input, so it works with JavaScript disabled. The nonce'd vanilla JS only adds
  drag-and-drop, click-to-browse and a chosen-file label on top.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Check content credentials'))
@section('body-class', 'c2pa verify verify-check')

@php
  $cspNonce = function_exists('csp_nonce') ? csp_nonce() : '';
  $validationError = $errorMsg ?? session('c2pa_check_error') ?? ($errors->first('file') ?: null);
@endphp

@section('content')
<div class="mb-3">
  <h1><i class="fas fa-shield-alt me-2"></i>{{ __('Check content credentials') }}</h1>
  <p class="text-muted mb-0">
    {{ __('Drop in any image to check whether it carries signed content credentials, and what they say. Authenticity is checked against the file\'s own cryptographic signature using the open C2PA standard, so the result is independently verifiable. This works for files from anywhere - not only material held here.') }}
  </p>
</div>

@if(!empty($validationError))
  <div class="alert alert-warning" role="alert">
    <i class="fas fa-exclamation-triangle me-1"></i>{{ $validationError }}
  </div>
@endif

{{-- Upload / drop zone. A plain multipart form so it works without JS. --}}
<div class="card mb-3">
  <div class="card-header"><i class="fas fa-upload me-2"></i>{{ __('Choose an image') }}</div>
  <div class="card-body">
    <form id="c2pa-check-form" action="{{ url('/verify/check') }}" method="post"
          enctype="multipart/form-data">
      @csrf
      <div id="c2pa-dropzone"
           class="border border-2 border-secondary-subtle rounded text-center p-4 mb-3"
           style="border-style:dashed !important;cursor:pointer;background:var(--bs-light, #f8f9fa)">
        <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted" aria-hidden="true"></i>
        <p class="mb-2 fw-semibold">{{ __('Drag an image here, or click to browse') }}</p>
        <p class="text-muted small mb-2" id="c2pa-chosen" aria-live="polite"></p>
        <input type="file" id="c2pa-file" name="file"
               accept="image/jpeg,image/png,image/tiff,image/webp,image/gif,image/avif,image/jp2,.jpg,.jpeg,.png,.tif,.tiff,.webp,.gif,.avif,.jp2"
               class="form-control" style="max-width:24rem;margin:0 auto">
      </div>
      <p class="text-muted small mb-3">
        <i class="fas fa-lock me-1"></i>{{ __('Your file is checked in memory and deleted immediately afterwards. Nothing is stored and no account is needed. JPEG, PNG, TIFF or WebP, up to 25 MB.') }}
      </p>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-shield-alt me-1"></i>{{ __('Check this image') }}
      </button>
    </form>
  </div>
</div>

{{-- Result panel: only shown after a POST. --}}
@if(!empty($state))
  @php
    $stateBadge = match($state) {
        'verified' => ['bg-success', 'fa-check-circle', __('Content credentials verified')],
        'invalid'  => ['bg-danger',  'fa-times-circle', __('Could not be verified')],
        default    => ['bg-secondary','fa-minus-circle', __('No content credentials')],
    };
    $assertions = $result['assertions'] ?? [];
    $resultErrors = $result['errors'] ?? [];
  @endphp

  {{-- Overall verdict. --}}
  <div class="card mb-3 border-2" style="border-color:var(--ahg-primary)">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-certificate me-2"></i>{{ __('Verdict') }}
    </div>
    <div class="card-body">
      @if(!empty($fileName))
        <p class="text-muted small mb-2"><i class="fas fa-file-image me-1"></i>{{ $fileName }}</p>
      @endif
      <p class="mb-2">
        <span class="badge {{ $stateBadge[0] }} fs-5"><i class="fas {{ $stateBadge[1] }} me-1"></i>{{ $stateBadge[2] }}</span>
      </p>

      @if($state === 'verified')
        <p class="text-success mb-0"><i class="fas fa-lock me-1"></i>
          {{ __('This image carries signed content credentials, and they have not been altered since they were created. Every step below re-computes to the value pinned in the signed claim, and the Ed25519 signature checks out.') }}
        </p>
        @if(!empty($result['signer']))
          <p class="text-muted small mt-2 mb-0">{{ __('Signed under key') }} <code>{{ $result['signer'] }}</code>.</p>
        @endif
      @elseif($state === 'invalid')
        <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>
          {{ __('This image has content credentials, but they could not be verified. The signature does not match the recorded content, or it was signed by a key this service does not recognise. Treat the steps below with caution - the credentials may have been tampered with.') }}
        </p>
        @if(!empty($resultErrors))
          <ul class="text-danger small mt-2 mb-0">
            @foreach($resultErrors as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        @endif
      @else
        <p class="text-muted mb-0">
          {{ __('This image has no content credentials we can read. That does not mean it is fake - only that it carries no signed provenance that this checker can verify. Some files embed credentials in a form that needs the native C2PA tool to read; if you have a sidecar manifest for this file, upload that instead.') }}
        </p>
      @endif
    </div>
  </div>

  {{-- The plain-language chain: what the manifest says happened, in order.
       Same styling as the per-object verify page. --}}
  @if(!empty($assertions))
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-list-ol me-2"></i>{{ __('What the credentials say') }}</div>
      <div class="card-body">
        <p class="text-muted small">{{ __('The actions and declarations recorded in the signed manifest, in order.') }}</p>
        <ol class="mb-0">
          @foreach($assertions as $step)
            @php
              $icon = match($step['kind'] ?? 'action') {
                  'ai'              => 'fa-robot',
                  'training-mining' => 'fa-balance-scale',
                  'metadata'        => 'fa-tags',
                  default           => 'fa-pen',
              };
            @endphp
            <li class="mb-2">
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
      </div>
    </div>
  @endif

  <p class="mb-3">
    <a href="{{ url('/verify/check') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-redo me-1"></i>{{ __('Check another image') }}
    </a>
  </p>
@endif

<p class="text-muted small">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('Content credentials follow the C2PA open standard. Signatures are Ed25519 and checked live, every time - nothing here is cached or stored.') }}
</p>

@push('js')
<script nonce="{{ $cspNonce }}">
  (function () {
    'use strict';
    var dz = document.getElementById('c2pa-dropzone');
    var input = document.getElementById('c2pa-file');
    var chosen = document.getElementById('c2pa-chosen');
    if (!dz || !input) {
      return;
    }

    function setChosen() {
      if (chosen) {
        chosen.textContent = (input.files && input.files.length)
          ? input.files[0].name
          : '';
      }
    }

    // Click anywhere in the zone (but not on the input itself) to browse.
    dz.addEventListener('click', function (e) {
      if (e.target !== input) {
        input.click();
      }
    });

    input.addEventListener('change', setChosen);

    ['dragenter', 'dragover'].forEach(function (name) {
      dz.addEventListener(name, function (e) {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.add('border-primary');
      });
    });

    ['dragleave', 'drop'].forEach(function (name) {
      dz.addEventListener(name, function (e) {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.remove('border-primary');
      });
    });

    dz.addEventListener('drop', function (e) {
      var dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        // DataTransfer -> file input works in modern browsers; the plain
        // input remains the source of truth for the normal form submit.
        try {
          input.files = dt.files;
        } catch (err) {
          // Older browsers: fall back to asking the user to click-browse.
        }
        setChosen();
      }
    });
  })();
</script>
@endpush
@endsection
