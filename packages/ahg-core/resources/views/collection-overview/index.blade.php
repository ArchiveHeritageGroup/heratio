{{--
  Public "Collection at a glance" page. A positive, visitor-facing snapshot of how
  large and how rich the PUBLISHED collection is: a hero line, big stat cards, and
  simple inline CSS bar breakdowns (by level of description, by holding repository,
  by century, and digital coverage). No charting library and no CDN - the bars are
  plain divs with a percentage width.

  Every figure comes from CollectionOverviewService via CollectionOverviewController
  and is already null-safe. Breakdown rows carry an optional `url` (a deep-link into
  the public GLAM browse with the matching filter) only when that route exists; when
  it does not, the row renders as plain text. A zero total (or a service error)
  renders the calm "still being catalogued" empty-state - never a 500.

  Jurisdiction-neutral, internationalised copy. This is the welcoming, outward
  counterpart to the admin data-quality dashboard (which surfaces gaps).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.

  Extends the public 1-column layout.
--}}
@extends('theme::layouts.1col')
@section('title', __('This collection at a glance'))

@php
    // Defensive locals: the controller always passes these, but coalesce so a
    // partial/old render context can never trip an undefined-variable warning.
    $total = (int) ($total ?? 0);
    $byLevel = $byLevel ?? [];
    $byRepository = $byRepository ?? [];
    $byCentury = $byCentury ?? [];
    $digital = $digital ?? [];
    $entities = $entities ?? [];
    $isEmpty = ($total <= 0);

    // A neutral integer formatter (thousands separators in the active locale).
    $fmt = fn ($n) => number_format((int) ($n ?? 0));
@endphp

@section('content')
<div class="container py-4" style="max-width:1040px">

  <header class="mb-4 text-center">
    <h1 class="mb-2">
      <i class="fas fa-layer-group me-2 text-muted"></i>{{ __('This collection at a glance') }}
    </h1>
    <p class="lead text-muted mb-0" style="max-width:760px;margin:0 auto">
      {{ __('A living snapshot of what this collection holds - its scale, its shape, and how much of it you can explore online.') }}
    </p>
  </header>

  @if($isEmpty)
    {{-- Empty-state: zero published records, or a service-level error. Calm and positive. --}}
    <div class="text-center py-5">
      <div class="mb-3">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light"
              style="width:5rem;height:5rem">
          <i class="fas fa-seedling fs-1 text-success"></i>
        </span>
      </div>
      <h2 class="h4">{{ __('The collection is still being catalogued') }}</h2>
      <p class="text-muted" style="max-width:560px;margin:0 auto">
        {{ __('There is nothing to show here just yet. As records are described and published, this page will fill with the shape and scale of the collection. Please check back soon.') }}
      </p>
    </div>
  @else

    {{-- ----------------------------------------------------------------- --}}
    {{-- Headline stat cards: the big numbers.                             --}}
    {{-- ----------------------------------------------------------------- --}}
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-4 text-center">
      @php
        $stats = [
          ['icon' => 'fas fa-folder-open', 'value' => $total,                         'label' => __('Descriptions')],
          ['icon' => 'fas fa-images',      'value' => $digital['any'] ?? 0,            'label' => __('Digitised')],
          ['icon' => 'fas fa-cube',        'value' => $digital['three_d'] ?? 0,        'label' => __('In 3D')],
          ['icon' => 'fas fa-users',       'value' => $entities['actors'] ?? 0,        'label' => __('People & bodies')],
          ['icon' => 'fas fa-tags',        'value' => $entities['subjects'] ?? 0,      'label' => __('Subjects')],
          ['icon' => 'fas fa-map-marker-alt','value' => $entities['places'] ?? 0,      'label' => __('Places')],
        ];
      @endphp
      @foreach($stats as $stat)
        <div class="col">
          <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body py-3">
              <i class="{{ $stat['icon'] }} fs-4 text-primary mb-2 d-block"></i>
              <div class="fw-bold" style="font-size:1.5rem;line-height:1.1">{{ $fmt($stat['value']) }}</div>
              <div class="small text-muted">{{ $stat['label'] }}</div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="row g-4">

      {{-- ----------------------------------------------------------- --}}
      {{-- Shape of the collection: by level of description.           --}}
      {{-- ----------------------------------------------------------- --}}
      @if(!empty($byLevel))
        <div class="col-12 col-lg-6">
          @include('ahg-core::collection-overview._breakdown', [
            'title' => __('By level of description'),
            'icon'  => 'fas fa-sitemap',
            'rows'  => $byLevel,
            'total' => $total,
            'barClass' => 'bg-primary',
          ])
        </div>
      @endif

      {{-- ----------------------------------------------------------- --}}
      {{-- Holdings: top repositories.                                 --}}
      {{-- ----------------------------------------------------------- --}}
      @if(!empty($byRepository))
        <div class="col-12 col-lg-6">
          @include('ahg-core::collection-overview._breakdown', [
            'title' => __('Largest holdings'),
            'icon'  => 'fas fa-archive',
            'rows'  => $byRepository,
            'total' => $total,
            'barClass' => 'bg-info',
          ])
        </div>
      @endif

      {{-- ----------------------------------------------------------- --}}
      {{-- Time: by century.                                           --}}
      {{-- ----------------------------------------------------------- --}}
      @if(!empty($byCentury))
        <div class="col-12 col-lg-6">
          @include('ahg-core::collection-overview._breakdown', [
            'title' => __('Across the centuries'),
            'icon'  => 'fas fa-hourglass-half',
            'rows'  => $byCentury,
            'total' => $total,
            'barClass' => 'bg-secondary',
            'note'  => __('By the earliest date recorded on each described record.'),
          ])
        </div>
      @endif

      {{-- ----------------------------------------------------------- --}}
      {{-- Digital coverage: how much you can explore online.          --}}
      {{-- ----------------------------------------------------------- --}}
      <div class="col-12 col-lg-6">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h2 class="h5 card-title mb-3">
              <i class="fas fa-images me-2 text-muted"></i>{{ __('Explore online') }}
            </h2>

            @php
              $coverage = [
                ['label' => __('With a digital object'), 'count' => $digital['any'] ?? 0,     'pct' => $digital['any_pct'] ?? 0,     'bar' => 'bg-success', 'url' => $digitalUrl ?? null],
                ['label' => __('Deep-zoom (IIIF) images'),'count' => $digital['iiif'] ?? 0,    'pct' => $digital['iiif_pct'] ?? 0,    'bar' => 'bg-success', 'url' => null],
                ['label' => __('3D models'),              'count' => $digital['three_d'] ?? 0, 'pct' => $digital['three_d_pct'] ?? 0, 'bar' => 'bg-success', 'url' => null],
              ];
            @endphp

            @foreach($coverage as $row)
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-baseline mb-1">
                  <span class="small">
                    @if(!empty($row['url']))
                      <a href="{{ $row['url'] }}" class="text-decoration-none">{{ $row['label'] }}</a>
                    @else
                      {{ $row['label'] }}
                    @endif
                  </span>
                  <span class="small text-muted">
                    {{ $fmt($row['count']) }}
                    <span class="text-nowrap">({{ rtrim(rtrim(number_format((float) $row['pct'], 1), '0'), '.') }}%)</span>
                  </span>
                </div>
                <div class="progress" role="presentation" style="height:.5rem;background:#eee">
                  <div class="progress-bar {{ $row['bar'] }}"
                       style="width:{{ max(0, min(100, (float) $row['pct'])) }}%"></div>
                </div>
              </div>
            @endforeach

            <p class="small text-muted mb-0 mt-3">
              {{ __('Online access grows as the collection is digitised. Percentages are of all published descriptions.') }}
            </p>
          </div>
        </div>
      </div>

    </div>{{-- /.row --}}

    {{-- ----------------------------------------------------------------- --}}
    {{-- Footer: provenance line + an invitation to dive in.               --}}
    {{-- ----------------------------------------------------------------- --}}
    <div class="text-center mt-4">
      @if($hasBrowse ?? false)
        @if(Route::has('glam.browse'))
          <a href="{{ route('glam.browse') }}" class="btn btn-primary">
            <i class="fas fa-compass me-1"></i>{{ __('Start exploring the collection') }}
          </a>
        @endif
      @endif
      @if(!empty($generatedAt))
        <p class="small text-muted mt-3 mb-0">
          {{ __('Snapshot taken :when.', ['when' => $generatedAt]) }}
        </p>
      @endif
    </div>

  @endif

</div>
@endsection
