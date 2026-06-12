{{--
  Public ACCESSIBILITY STATEMENT page (GET /accessibility-statement).

  The standard, outward, human-readable conformance statement that every public
  digital service is expected to publish. It follows the W3C model accessibility
  statement structure and is jurisdiction-NEUTRAL: WCAG 2.2 is the primary standard
  and EN 301 549 is named as ONE recognised harmonised standard (an example, not the
  sole or legal regime). This is international; it never frames any one country's law
  as the governing rule.

  This page is DISTINCT from the internal /admin/accessibility coverage report (which
  measures metadata coverage) and /admin/alt-text (which curates image descriptions).
  This is the public commitment, the conformance claim, and the channel to report a
  barrier.

  All configurable text (institution name, contact email, conformance level,
  preparation date) is supplied by AccessibilityStatementService from the existing
  ahg_settings table, with neutral international defaults when unset. No new table.

  Links use url() / route() so no internal host is ever hardcoded. The page never
  500s: the controller falls back to an all-defaults statement on any failure.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Accessibility statement'))

@section('content')
@php
    // Defensive locals so a missing key can never throw in the view.
    $institution = $s['institution'] ?? __('This institution');
    $contactEmail = $s['contact_email'] ?? 'accessibility@your-site.example';
    $contactUrl = $s['contact_url'] ?? '';
    $level = $s['conformance_level'] ?? __('Partially conformant, level AA targeted');
    $wcag = $s['wcag_version'] ?? '2.2';
    $preparedOn = $s['prepared_on'] ?? '';
    $responseDays = (int) ($s['response_days'] ?? 10);
    $features = is_array($s['features'] ?? null) ? $s['features'] : [];
    $limitations = is_array($s['limitations'] ?? null) ? $s['limitations'] : [];
@endphp

<div class="container py-4" style="max-width:880px">

  <header class="mb-4">
    <p class="text-uppercase small text-muted mb-1" style="letter-spacing:.08em">
      <i class="fas fa-universal-access me-1" aria-hidden="true"></i>{{ __('Accessibility') }}
    </p>
    <h1 class="mb-2">{{ __('Accessibility statement') }}</h1>
    <p class="lead text-muted mb-0">
      {{ __(':institution is committed to making this digital collection accessible to as many people as possible, regardless of ability or technology.', ['institution' => $institution]) }}
    </p>
  </header>

  {{-- 1. Commitment ----------------------------------------------------- --}}
  <section class="mb-4" aria-labelledby="acc-commitment">
    <h2 id="acc-commitment" class="h4">{{ __('Our commitment') }}</h2>
    <p>
      {{ __('We believe everyone has the right to access our heritage. We work to make this platform usable for people who rely on assistive technology, who navigate by keyboard, who need captions or text alternatives, who read in another language, or who need to enlarge or restyle the page.') }}
    </p>
    <p class="mb-0">
      {{ __('Accessibility is an ongoing effort, not a one-time task. We test and improve the platform over time, and we welcome feedback that helps us do better.') }}
    </p>
  </section>

  {{-- 2. Conformance status -------------------------------------------- --}}
  <section class="mb-4" aria-labelledby="acc-conformance">
    <h2 id="acc-conformance" class="h4">{{ __('Conformance status') }}</h2>
    <p>
      {{ __('The Web Content Accessibility Guidelines (WCAG) define requirements for designers and developers to improve accessibility for people with disabilities. This platform is assessed against') }}
      <strong>{{ __('WCAG :version', ['version' => $wcag]) }}</strong>.
    </p>
    <p class="mb-2">
      <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-3 py-2">
        <i class="fas fa-circle-half-stroke me-1" aria-hidden="true"></i>{{ $level }}
      </span>
    </p>
    <p class="text-muted small mb-3">
      {{ __('"Partially conformant" means that some parts of the content do not yet fully meet the standard. The known gaps are listed below under Known limitations.') }}
    </p>
    <p class="mb-0">
      {{ __('WCAG is the internationally recognised baseline for digital accessibility. It is also the technical foundation of recognised harmonised standards adopted in many regions - for example the European standard EN 301 549, which references WCAG for web content. We name EN 301 549 here as one such recognised standard, not as the sole or governing legal regime: requirements differ by country, and this statement is offered on an international basis.') }}
    </p>
  </section>

  {{-- 3. What is accessible --------------------------------------------- --}}
  <section class="mb-4" aria-labelledby="acc-accessible">
    <h2 id="acc-accessible" class="h4">{{ __('What is accessible') }}</h2>
    @if (count($features))
      <p>{{ __('The following accessibility features are in place across the platform:') }}</p>
      <ul class="list-group list-group-flush mb-0">
        @foreach ($features as $f)
          <li class="list-group-item px-0">
            <span class="fw-semibold">
              <i class="fas fa-check text-success me-2" aria-hidden="true"></i>{{ $f['label'] ?? '' }}
            </span>
            <div class="text-muted small ms-4">{{ $f['detail'] ?? '' }}</div>
          </li>
        @endforeach
      </ul>
    @else
      <p class="text-muted mb-0">{{ __('Accessibility features are being documented. Please check back, or contact us using the details below.') }}</p>
    @endif
  </section>

  {{-- 4. Known limitations ---------------------------------------------- --}}
  <section class="mb-4" aria-labelledby="acc-limitations">
    <h2 id="acc-limitations" class="h4">{{ __('Known limitations') }}</h2>
    <p>
      {{ __('We want to be honest about where the platform falls short today. Despite our efforts, some content and features may not yet be fully accessible:') }}
    </p>
    @if (count($limitations))
      <ul class="mb-0">
        @foreach ($limitations as $limitation)
          <li class="mb-2">{{ $limitation }}</li>
        @endforeach
      </ul>
    @else
      <p class="text-muted mb-0">{{ __('No specific limitations are recorded at this time.') }}</p>
    @endif
  </section>

  {{-- 5. Report a barrier ----------------------------------------------- --}}
  <section class="mb-4" aria-labelledby="acc-feedback">
    <h2 id="acc-feedback" class="h4">{{ __('Report an accessibility barrier') }}</h2>
    <p>
      {{ __('If you find something on this platform that you cannot access, or you need content in an alternative format (for example a transcript, a described image, or an accessible document), please tell us. Your feedback helps us identify problems and fix them.') }}
    </p>
    <div class="card border-0 bg-body-tertiary">
      <div class="card-body">
        <p class="mb-2">
          <i class="fas fa-envelope me-2 text-muted" aria-hidden="true"></i>
          <strong>{{ __('Email') }}:</strong>
          <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
        </p>
        @if (!empty($contactUrl))
          <p class="mb-2">
            <i class="fas fa-comment-dots me-2 text-muted" aria-hidden="true"></i>
            <strong>{{ __('Feedback form') }}:</strong>
            <a href="{{ $contactUrl }}">{{ __('Send us a message') }}</a>
          </p>
        @endif
        <p class="mb-0 text-muted small">
          <i class="fas fa-clock me-2" aria-hidden="true"></i>{{ __('We aim to respond within :days working days.', ['days' => $responseDays]) }}
          {{ __('Please include the page address and a short description of the problem so we can reproduce it.') }}
        </p>
      </div>
    </div>
  </section>

  {{-- 6. Preparation / review date -------------------------------------- --}}
  <section class="mb-2" aria-labelledby="acc-prepared">
    <h2 id="acc-prepared" class="h4">{{ __('Preparation of this statement') }}</h2>
    <p class="mb-0 text-muted">
      {{ __('This statement was prepared and last reviewed on :date.', ['date' => $preparedOn]) }}
      {{ __('It is reviewed periodically as the platform changes.') }}
    </p>
  </section>

</div>
@endsection
