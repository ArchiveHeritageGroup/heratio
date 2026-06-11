{{--
  Heratio - public "Content Credentials" explainer / trust page
  (deepens #1209 / #1201).

  A plain-language, jurisdiction-neutral, non-technical page that tells the
  public WHAT content credentials are, WHY this institution uses them, and HOW
  to verify - linking the EXISTING verify tools rather than reimplementing any
  of them. It does not sign, verify, write to the database, or call AI.

  Sections:
    1. What are content credentials?   (C2PA, tamper-evident provenance)
    2. Why we use them                 (trust in primary sources, truth anchor)
    3. How to check                    (3 cards -> the existing verify tools)
    4. What a verdict means            (green verified / red tampered / neutral)

  Every link to a verify tool is gated with Route::has so the page renders
  cleanly even if a route is absent on a given install. A couple of headline
  numbers ($headline) are passed in defensively by the controller and shown
  only when present.

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Content Credentials'))
@section('body-class', 'c2pa content-credentials')

@section('content')
@php
  // Resolve the verify-tool links once, each gated with Route::has so a missing
  // route never throws. Fall back to a plain url() path only when the named
  // route exists; otherwise the corresponding card/link is suppressed.
  $verifyUrl      = \Route::has('c2pa.authenticity')  ? route('c2pa.authenticity')  : null;
  $checkUrl       = \Route::has('c2pa.verify.check')  ? route('c2pa.verify.check')  : null;
  // The full per-record provenance trace is keyed by a record id; from this
  // public explainer we have no specific record, so we point at the verify
  // landing (which leads into per-record verification and the trace) when the
  // landing exists. The trace route itself is referenced by name in the copy.
  $traceAvailable = \Route::has('c2pa.verify.record.trace');
  $headline       = $headline ?? null;
@endphp

{{-- Hero: WHAT, in one breath. --}}
<div class="card mb-4 border-2" style="border-color:var(--ahg-primary)">
  <div class="card-body p-4">
    <h1 class="mb-2"><i class="fas fa-certificate me-2"></i>{{ __('Content Credentials') }}</h1>
    <p class="lead mb-0">
      {{ __('A content credential is a tamper-evident record of where a digital file came from and how it was made. It lets anyone confirm, on any device, that what they are looking at has not been altered since it was created.') }}
    </p>
  </div>
</div>

{{-- Optional social proof: only when the controller passed real numbers. --}}
@if(is_array($headline))
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6" style="color:var(--ahg-primary)">{{ number_format($headline['records_with_credentials'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('records carry content credentials') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-4">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($headline['covered_masters'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('signed master files') }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6">{{ number_format($headline['coverage_pct'] ?? 0, 1) }}%</div>
          <div class="text-muted small">{{ __('of master files are verifiable') }}</div>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- 1. What are content credentials? --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-info-circle me-2"></i>{{ __('What are content credentials?') }}</div>
  <div class="card-body">
    <p>
      {{ __('When we digitise a primary source - a document, a photograph, an object - we capture more than the image. We also record how it was made: when it was captured, with what device and software, and by which institution. That information is bundled with the file and sealed with a digital signature.') }}
    </p>
    <p>
      {{ __('Because the seal is calculated from the exact content of the file, any later change - even a single altered pixel - breaks it. That is what "tamper-evident" means: you cannot quietly edit a credentialled file without the change being detectable.') }}
    </p>
    <p class="mb-0">
      {{ __('This follows C2PA, an open, international standard for content provenance and authenticity. It is the same approach used across journalism and media to tell genuine material from manipulated or synthetic copies. It is open and vendor-neutral, so anyone can verify a credential with public tooling - they do not have to take our word for it.') }}
    </p>
  </div>
</div>

{{-- 2. Why we use them. --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-shield-alt me-2"></i>{{ __('Why we use them') }}</div>
  <div class="card-body">
    <p>
      {{ __('An archive, library, gallery or museum exists to be trusted. Researchers, courts, journalists and the public rely on what we hold being a faithful record of the original. In a world where images and documents can be convincingly altered or generated, that trust can no longer be assumed - it has to be demonstrable.') }}
    </p>
    <p>
      {{ __('Content credentials let us demonstrate it. Every digitised item can be checked back to the moment of capture, independently and without contacting us. This turns our collection into a verifiable anchor for truth: a fixed point that other people can measure a suspect copy against.') }}
    </p>
    <p class="mb-0">
      {{ __('It also protects you. If a file claiming to come from us has been altered, the credential will reveal it. If a file genuinely came from us, the credential will confirm it. Either way you are not left guessing.') }}
    </p>
  </div>
</div>

{{-- 3. How to check - three cards, each linking an existing verify tool. --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-search me-2"></i>{{ __('How to check') }}</div>
  <div class="card-body">
    <p class="mb-3">{{ __('There are three ways to verify, depending on what you have in front of you. You never need an account, and we never see or keep anything you check.') }}</p>
    <div class="row g-3">

      {{-- 3a. Verify a specific record in this repository. --}}
      @if($verifyUrl)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h2 class="h6"><i class="fas fa-fingerprint me-1" style="color:var(--ahg-primary)"></i>{{ __('Verify a record') }}</h2>
              <p class="text-muted small flex-grow-1">{{ __('Have a reference or permalink for one of our records? Look it up and confirm its content credentials directly.') }}</p>
              <a href="{{ $verifyUrl }}" class="btn btn-outline-primary btn-sm mt-auto">
                <i class="fas fa-arrow-right me-1"></i>{{ __('Verify a record') }}
              </a>
            </div>
          </div>
        </div>
      @endif

      {{-- 3b. Check any file you already hold. --}}
      @if($checkUrl)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h2 class="h6"><i class="fas fa-upload me-1" style="color:var(--ahg-primary)"></i>{{ __('Check a file you have') }}</h2>
              <p class="text-muted small flex-grow-1">{{ __('Have a file from anywhere - even one that did not come from us? Upload it and read its content-credentials verdict in plain language. The upload is checked and then discarded.') }}</p>
              <a href="{{ $checkUrl }}" class="btn btn-outline-primary btn-sm mt-auto">
                <i class="fas fa-arrow-right me-1"></i>{{ __('Check a file') }}
              </a>
            </div>
          </div>
        </div>
      @endif

      {{-- 3c. See a record's full provenance trace. --}}
      @if($traceAvailable && $verifyUrl)
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h2 class="h6"><i class="fas fa-stream me-1" style="color:var(--ahg-primary)"></i>{{ __('See a full provenance trace') }}</h2>
              <p class="text-muted small flex-grow-1">{{ __('Want the whole story behind a record? Open the record from the verify page to follow its complete provenance trace - every captured file and every signing event, in order.') }}</p>
              <a href="{{ $verifyUrl }}" class="btn btn-outline-primary btn-sm mt-auto">
                <i class="fas fa-arrow-right me-1"></i>{{ __('Start from a record') }}
              </a>
            </div>
          </div>
        </div>
      @endif

    </div>

    @unless($verifyUrl || $checkUrl)
      <p class="text-muted small mb-0 mt-2">
        <i class="fas fa-info-circle me-1"></i>{{ __('The verification tools are not enabled on this installation yet.') }}
      </p>
    @endunless
  </div>
</div>

{{-- 4. What a verdict means. --}}
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-balance-scale me-2"></i>{{ __('What a verdict means') }}</div>
  <div class="card-body">
    <p class="mb-3">{{ __('Whichever tool you use, the answer comes back as one of three plain results.') }}</p>
    <div class="row g-3">

      <div class="col-md-4">
        <div class="card h-100 border-success">
          <div class="card-body">
            <h2 class="h6 text-success"><i class="fas fa-check-circle me-1"></i>{{ __('Verified') }}</h2>
            <p class="text-muted small mb-0">{{ __('A content credential was found and its seal checks out. The file is exactly as it was when it was created, and it carries our signature. You can rely on it.') }}</p>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 border-danger">
          <div class="card-body">
            <h2 class="h6 text-danger"><i class="fas fa-times-circle me-1"></i>{{ __('Tampered or untrusted') }}</h2>
            <p class="text-muted small mb-0">{{ __('A credential was found but its seal does not check out. The file has been changed since it was credentialled, or it was signed by someone we do not recognise. Treat it with caution.') }}</p>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h6 text-muted"><i class="fas fa-minus-circle me-1"></i>{{ __('No content credentials') }}</h2>
            <p class="text-muted small mb-0">{{ __('No credential could be read from the file. This is not an error - it simply means the file carries no provenance to check. It is the normal answer for an ordinary photo or an older scan.') }}</p>
          </div>
        </div>
      </div>

    </div>
    <p class="text-muted small mb-0 mt-3">
      <i class="fas fa-lock me-1"></i>{{ __('Because this uses the open C2PA standard, you are never limited to our tools - anyone can independently verify one of our content credentials with public C2PA software.') }}
    </p>
  </div>
</div>
@endsection
