{{--
  Exhibition catalogue (the classic museum deliverable).

  A clean, self-contained, print- / PDF-ready catalogue of a show: a cover block
  (space name, intro, object count, date), an optional table of contents by room
  or zone, then one entry per object (image + title + creator/date + wall text +
  record link). A dedicated @media print stylesheet strips the site chrome
  (nav, buttons, header, footer) and lays the page out as black-on-white serif
  body text with each entry kept whole across page breaks; a "Print / Save as
  PDF" button calls window.print(). Read-only and public, like the walkthrough
  and wayfinding pages.

  International / jurisdiction-neutral copy. Self-hosted only (no CDN). The single
  inline script carries the CSP nonce.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Catalogue') . ' - ' . $space->name)
@section('body-class', 'exhibition-space exhibition-catalogue')

@php
  // Cover meta drawn only from columns guaranteed on every space row, so a fresh
  // install with no extra metadata still renders a dignified cover.
  $coverIntro = trim((string) ($space->notes ?? ''));
  $coverLocation = trim(implode(', ', array_filter([
      trim((string) ($space->building ?? '')),
      trim((string) ($space->floor ?? '')),
  ])));
  $objectCount = is_array($entries ?? null) ? count($entries) : 0;
  $printedOn = now()->format('j F Y');
@endphp

@section('content')
  {{-- Screen-only action bar + nav. Hidden in print via the stylesheet below. --}}
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2 cat-chrome">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-book-open me-2"></i>{{ __('Catalogue') }} <small class="text-muted">{{ $space->name }}</small></h1>
    @include('ahg-exhibition::exhibition-space._nav-actions', ['space' => $space, 'current' => 'catalogue'])
  </div>
  <div class="d-flex flex-wrap gap-2 mb-4 cat-chrome">
    <button type="button" id="cat-print" class="btn btn-primary">
      <i class="fas fa-print me-1"></i>{{ __('Print / Save as PDF') }}
    </button>
    <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to exhibition') }}
    </a>
  </div>

  {{-- The catalogue document itself. Everything inside .cat-doc is print-styled. --}}
  <article class="cat-doc" aria-label="{{ __('Exhibition catalogue') }}">

    {{-- ---- Cover ---- --}}
    <header class="cat-cover">
      <p class="cat-kicker">{{ __('Exhibition catalogue') }}</p>
      <h1 class="cat-title">{{ $space->name }}</h1>
      @if($coverLocation !== '')
        <p class="cat-subtitle">{{ $coverLocation }}</p>
      @endif
      @if($coverIntro !== '')
        <div class="cat-intro">{!! nl2br(e($coverIntro)) !!}</div>
      @endif
      <dl class="cat-meta">
        <div>
          <dt>{{ __('Objects') }}</dt>
          <dd>{{ trans_choice('{0}No objects|{1}:count object|[2,*]:count objects', $objectCount, ['count' => $objectCount]) }}</dd>
        </div>
        <div>
          <dt>{{ __('Prepared') }}</dt>
          <dd>{{ $printedOn }}</dd>
        </div>
      </dl>
    </header>

    @if($objectCount === 0)
      {{-- Dignified empty catalogue: a full cover, then a quiet note. --}}
      <section class="cat-empty">
        <p>{{ __('No objects have been placed in this exhibition yet.') }}</p>
        <p class="cat-empty-hint">{{ __('Once objects are placed using the Builder or Building Plan, they will appear here as catalogue entries ready to print or save as a PDF.') }}</p>
      </section>
    @else
      {{-- ---- Table of contents (only when the show splits across named zones) ---- --}}
      @if($hasSections)
        <nav class="cat-toc" aria-label="{{ __('Contents') }}">
          <h2 class="cat-section-heading">{{ __('Contents') }}</h2>
          <ol class="cat-toc-list">
            @foreach($sections as $sec)
              <li>
                <a href="#cat-section-{{ $sec['key'] }}">{{ $sec['label'] }}</a>
                <span class="cat-toc-count">{{ count($sec['entries']) }}</span>
              </li>
            @endforeach
          </ol>
        </nav>
      @endif

      {{-- ---- Entries ---- --}}
      @if($hasSections)
        @foreach($sections as $sec)
          <section class="cat-section" id="cat-section-{{ $sec['key'] }}">
            <h2 class="cat-section-heading">{{ $sec['label'] }}</h2>
            @foreach($sec['entries'] as $entry)
              @include('ahg-exhibition::exhibition-space._catalogue-entry', ['entry' => $entry])
            @endforeach
          </section>
        @endforeach
      @else
        <section class="cat-section">
          @foreach($entries as $entry)
            @include('ahg-exhibition::exhibition-space._catalogue-entry', ['entry' => $entry])
          @endforeach
        </section>
      @endif
    @endif

    <footer class="cat-colophon">
      <p>{{ __('Catalogue generated by Heratio') }} &middot; {{ $printedOn }}</p>
    </footer>
  </article>

  <style nonce="{{ $cspNonce ?? '' }}">
    /* ---- Screen presentation: a paper-like sheet centred on the page ---- */
    .cat-doc {
      max-width: 52rem;
      margin: 0 auto;
      background: #ffffff;
      color: #1a1a1a;
      font-family: Georgia, "Times New Roman", serif;
      line-height: 1.55;
      padding: 2rem 2.25rem;
      border: 1px solid #e3e6ea;
      border-radius: .375rem;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    }
    .cat-cover { text-align: center; padding: 1.5rem 0 2rem; border-bottom: 2px solid #1a1a1a; margin-bottom: 2rem; }
    .cat-kicker { text-transform: uppercase; letter-spacing: .18em; font-size: .8rem; color: #555; margin: 0 0 .75rem; }
    .cat-title { font-size: 2.4rem; line-height: 1.15; margin: 0 0 .5rem; font-weight: 700; }
    .cat-subtitle { font-size: 1.1rem; color: #444; font-style: italic; margin: 0 0 1rem; }
    .cat-intro { text-align: left; max-width: 40rem; margin: 1.25rem auto 0; }
    .cat-meta { display: flex; justify-content: center; gap: 2.5rem; margin: 1.75rem 0 0; }
    .cat-meta dt { text-transform: uppercase; letter-spacing: .1em; font-size: .7rem; color: #666; }
    .cat-meta dd { margin: .15rem 0 0; font-size: 1rem; font-weight: 600; }

    .cat-section-heading { font-size: 1.4rem; border-bottom: 1px solid #c7ccd1; padding-bottom: .35rem; margin: 2rem 0 1.25rem; }
    .cat-toc { margin-bottom: 2rem; }
    .cat-toc-list { list-style: none; padding: 0; margin: 0; }
    .cat-toc-list li { display: flex; align-items: baseline; gap: .5rem; padding: .25rem 0; border-bottom: 1px dotted #cfd4d9; }
    .cat-toc-list a { color: #1a1a1a; text-decoration: none; flex-grow: 1; }
    .cat-toc-list a:hover { text-decoration: underline; }
    .cat-toc-count { color: #777; font-size: .85rem; }

    .cat-entry { display: flex; gap: 1.25rem; padding: 1.25rem 0; border-bottom: 1px solid #ececec; }
    .cat-entry:last-child { border-bottom: 0; }
    .cat-entry-figure { flex: 0 0 11rem; }
    .cat-entry-figure img { width: 100%; height: auto; border: 1px solid #d7dbe0; border-radius: .25rem; background: #f4f5f7; }
    .cat-entry-noimg {
      flex: 0 0 11rem; min-height: 7rem; border: 1px dashed #c7ccd1; border-radius: .25rem;
      display: flex; align-items: center; justify-content: center; color: #9aa1a8;
      font-size: .8rem; text-align: center; padding: .5rem; background: #fafbfc;
    }
    .cat-entry-body { flex: 1 1 auto; min-width: 0; }
    .cat-entry-no { color: #888; font-size: .8rem; letter-spacing: .05em; }
    .cat-entry-title { font-size: 1.2rem; margin: .1rem 0 .35rem; font-weight: 700; }
    .cat-entry-attrib { color: #444; font-style: italic; margin: 0 0 .6rem; }
    .cat-entry-caption { margin: 0 0 .6rem; }
    .cat-entry-record a { color: #0b5cad; font-size: .9rem; text-decoration: none; }
    .cat-entry-record a:hover { text-decoration: underline; }

    .cat-empty { text-align: center; color: #555; padding: 2.5rem 1rem; }
    .cat-empty-hint { color: #888; font-size: .95rem; max-width: 34rem; margin: .75rem auto 0; }
    .cat-colophon { margin-top: 2.5rem; padding-top: 1rem; border-top: 1px solid #ddd; text-align: center; color: #888; font-size: .8rem; }

    /* ---- Print / PDF: strip all site chrome, go black-on-white, keep entries whole ---- */
    @media print {
      /* Hide everything outside the catalogue, plus the catalogue's own screen controls. */
      body * { visibility: hidden; }
      .cat-doc, .cat-doc * { visibility: visible; }
      .cat-doc { position: absolute; left: 0; top: 0; width: 100%; max-width: none;
                 margin: 0; padding: 0; border: 0; box-shadow: none; background: #fff; color: #000; }
      .cat-chrome, .exhibition-nav-actions, #cat-print { display: none !important; }

      a { color: #000 !important; text-decoration: none; }
      .cat-entry-record a::after { content: " (" attr(href) ")"; font-size: .75rem; color: #333; }

      .cat-cover { page-break-after: always; padding-top: 4rem; border-bottom: 3px solid #000; }
      .cat-section-heading { page-break-after: avoid; }
      .cat-toc { page-break-after: always; }
      .cat-entry { page-break-inside: avoid; }
      .cat-entry-figure img, .cat-entry-noimg { max-height: 9cm; object-fit: contain; }
      .cat-colophon { page-break-before: avoid; }
    }

    @media (max-width: 575.98px) {
      .cat-entry { flex-direction: column; }
      .cat-entry-figure, .cat-entry-noimg { flex-basis: auto; }
      .cat-meta { flex-direction: column; gap: 1rem; }
    }
  </style>
  <script nonce="{{ $cspNonce ?? '' }}">
    (function () {
      var btn = document.getElementById('cat-print');
      if (btn) {
        btn.addEventListener('click', function () { window.print(); });
      }
    })();
  </script>
@endsection
