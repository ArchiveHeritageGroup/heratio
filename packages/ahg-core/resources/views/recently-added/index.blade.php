{{--
  Public "Recently added" page. The newest PUBLISHED records, most-recent first,
  as a Bootstrap-5 card grid on the central theme: each card shows a thumbnail
  (when a digital object has one), the title, the date it was added, and a short
  snippet, linking to the record. Simple offset paging (Newer / Older).

  Every item comes from RecentlyAddedService via RecentlyAddedController and is
  already null-safe and bounded. A zero-result / missing-table state renders the
  calm "nothing published yet" empty-state - never a 500. When the real creation
  signal (object.created_at) is unavailable the page is ordered by id and says so
  honestly.

  Jurisdiction-neutral, internationalised copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('Recently added'))

@php
    // Defensive locals: the controller always passes these.
    $items            = $items ?? [];
    $page             = (int) ($page ?? 1);
    $hasMore          = (bool) ($hasMore ?? false);
    $orderedByCreated = (bool) ($orderedByCreated ?? true);
    $isEmpty          = empty($items);

    // A neutral, locale-aware date formatter for the "added" line. The value is a
    // "YYYY-MM-DD HH:MM:SS" string; parse defensively and fall back to the raw date.
    $fmtDate = function ($raw) {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($raw)->isoFormat('LL');
        } catch (\Throwable $e) {
            return $raw;
        }
    };
@endphp

@section('content')
<div class="container py-4" style="max-width:1100px">

  <header class="mb-4 text-center">
    <h1 class="mb-2">
      <i class="fas fa-clock me-2 text-muted"></i>{{ __('Recently added') }}
    </h1>
    <p class="lead text-muted mb-2" style="max-width:760px;margin:0 auto">
      {{ __('The newest published records in this collection - see what has just been added.') }}
    </p>
    <p class="small text-muted mb-0">
      @if($hasFeed ?? false)
        <a href="{{ url('/recent.atom') }}" class="text-decoration-none me-3">
          <i class="fas fa-rss me-1"></i>{{ __('Atom feed') }}
        </a>
      @endif
      @if($hasJson ?? false)
        <a href="{{ url('/recent.json') }}" class="text-decoration-none">
          <i class="fas fa-code me-1"></i>{{ __('JSON') }}
        </a>
      @endif
    </p>
  </header>

  @if($isEmpty)
    {{-- Empty-state: nothing published yet, or a service-level error. Calm and positive. --}}
    <div class="text-center py-5">
      <div class="mb-3">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light"
              style="width:5rem;height:5rem">
          <i class="fas fa-seedling fs-1 text-success"></i>
        </span>
      </div>
      <h2 class="h4">{{ __('Nothing published yet') }}</h2>
      <p class="text-muted" style="max-width:560px;margin:0 auto">
        {{ __('There is nothing to show here just yet. As records are described and published, the newest of them will appear here. Please check back soon.') }}
      </p>
    </div>
  @else

    @unless($orderedByCreated)
      {{-- Honest note: the real creation timestamp was unavailable on this instance,
           so the list is ordered by record id (newest catalogued) rather than by
           the exact date added. --}}
      <div class="alert alert-light border small text-muted" role="note">
        <i class="fas fa-info-circle me-1"></i>
        {{ __('Ordered by the most recently catalogued record. An exact "date added" is not available on this instance.') }}
      </div>
    @endunless

    {{-- ----------------------------------------------------------------- --}}
    {{-- The card grid.                                                    --}}
    {{-- ----------------------------------------------------------------- --}}
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
      @foreach($items as $item)
        @php
          $url      = $item['url'] ?? null;
          $title    = (string) ($item['title'] ?? __('Untitled record'));
          $snippet  = (string) ($item['snippet'] ?? '');
          $thumb    = $item['thumbnail'] ?? null;
          $added    = $fmtDate($item['created_at'] ?? null);
        @endphp
        <div class="col">
          <div class="card h-100 shadow-sm border-0">
            @if($thumb)
              @if($url)
                <a href="{{ $url }}" class="d-block" aria-label="{{ $title }}">
                  <img src="{{ $thumb }}" class="card-img-top" alt="{{ $title }}"
                       loading="lazy" style="height:180px;object-fit:cover;background:#f1f1f1">
                </a>
              @else
                <img src="{{ $thumb }}" class="card-img-top" alt="{{ $title }}"
                     loading="lazy" style="height:180px;object-fit:cover;background:#f1f1f1">
              @endif
            @else
              <div class="card-img-top d-flex align-items-center justify-content-center bg-light"
                   style="height:180px" role="presentation">
                <i class="fas fa-file-alt fs-1 text-secondary opacity-50"></i>
              </div>
            @endif

            <div class="card-body d-flex flex-column">
              <h2 class="h6 card-title mb-1">
                @if($url)
                  <a href="{{ $url }}" class="text-decoration-none stretched-link">{{ $title }}</a>
                @else
                  {{ $title }}
                @endif
              </h2>

              @if($added)
                <div class="small text-muted mb-2">
                  <i class="far fa-calendar-plus me-1"></i>{{ __('Added :date', ['date' => $added]) }}
                </div>
              @endif

              @if($snippet !== '')
                <p class="small text-muted mb-0">{{ $snippet }}</p>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>{{-- /.row --}}

    {{-- ----------------------------------------------------------------- --}}
    {{-- Simple offset paging: Newer (lower page) / Older (higher page).   --}}
    {{-- ----------------------------------------------------------------- --}}
    @if($page > 1 || $hasMore)
      <nav class="d-flex justify-content-between align-items-center mt-4" aria-label="{{ __('Recently added pages') }}">
        <div>
          @if($page > 1)
            <a href="{{ url('/recent') }}{{ $page > 2 ? '?page='.($page - 1) : '' }}" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-1"></i>{{ __('Newer') }}
            </a>
          @endif
        </div>
        <div class="small text-muted">{{ __('Page :n', ['n' => $page]) }}</div>
        <div>
          @if($hasMore)
            <a href="{{ url('/recent') }}?page={{ $page + 1 }}" class="btn btn-outline-secondary">
              {{ __('Older') }}<i class="fas fa-arrow-right ms-1"></i>
            </a>
          @endif
        </div>
      </nav>
    @endif

    @if(!empty($generatedAt))
      <p class="small text-muted text-center mt-4 mb-0">
        {{ __('Snapshot taken :when.', ['when' => $generatedAt]) }}
      </p>
    @endif

  @endif

</div>
@endsection
