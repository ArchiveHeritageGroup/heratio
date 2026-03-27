@extends('theme::layouts.1col')

@section('title')
  <h1>{{ __('Accessibility Statement') }}</h1>
@endsection

@section('content')
<div class="container py-4">
  <div class="card">
    <div class="card-body">

      <h2>{{ __('Our Commitment') }}</h2>
      <p>{{ __('This site is committed to ensuring digital accessibility for people with disabilities. We continually improve the user experience for everyone and apply the relevant accessibility standards.') }}</p>

      <h2>{{ __('Conformance Status') }}</h2>
      <p>{{ __('We aim to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA. These guidelines explain how to make web content more accessible to people with a wide range of disabilities.') }}</p>

      <h2>{{ __('Accessibility Features') }}</h2>
      <ul>
        <li>{{ __('Skip navigation link to bypass repetitive content') }}</li>
        <li>{{ __('ARIA landmarks for screen reader navigation (banner, main, navigation, complementary, contentinfo)') }}</li>
        <li>{{ __('Keyboard navigable — all interactive elements reachable via Tab') }}</li>
        <li>{{ __('Visible focus indicators on interactive elements') }}</li>
        <li>{{ __('ARIA live regions for dynamic content announcements') }}</li>
        <li>{{ __('Collapsible facets with aria-expanded state') }}</li>
        <li>{{ __('Form validation linked to error messages via aria-describedby') }}</li>
        <li>{{ __('Table headers with scope attributes') }}</li>
        <li>{{ __('Respects prefers-reduced-motion for users sensitive to animation') }}</li>
        <li>{{ __('High contrast mode support (forced-colors media query)') }}</li>
        <li>{{ __('Language and text direction set on the html element') }}</li>
        <li>{{ __('Alternative text on images') }}</li>
        <li>{{ __('Voice command support (optional)') }}</li>
        <li>{{ __('Text-to-speech support (optional)') }}</li>
      </ul>

      <h2>{{ __('Known Limitations') }}</h2>
      <ul>
        <li>{{ __('Some legacy pages rendered by the base system may not fully meet all AA criteria.') }}</li>
        <li>{{ __('Third-party embedded content (e.g. IIIF viewers) may have their own accessibility limitations.') }}</li>
        <li>{{ __('PDF documents may not be fully accessible; alternative formats are available on request.') }}</li>
      </ul>

      <h2>{{ __('Feedback') }}</h2>
      <p>{{ __('We welcome your feedback on the accessibility of this site. If you encounter accessibility barriers, please contact us:') }}</p>
      <ul>
        <li>{{ __('Email') }}: <a href="mailto:{{ config('app.admin_email', 'admin@example.com') }}">{{ config('app.admin_email', 'admin@example.com') }}</a></li>
      </ul>

      <h2>{{ __('Technical Specifications') }}</h2>
      <p>{{ __('Accessibility of this site relies on the following technologies: HTML, CSS, JavaScript, WAI-ARIA, Bootstrap 5.') }}</p>

      <p class="text-muted small mt-4">{{ __('This statement was last updated on :date.', ['date' => now()->format('j F Y')]) }}</p>

    </div>
  </div>
</div>
@endsection
