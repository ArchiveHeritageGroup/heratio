{{--
  Collection timeline - public "browse the holdings by period" surface.

  The distribution of PUBLISHED records across time, rendered as CSS-only
  horizontal bars (no charting library) sized by record count. Records are
  bucketed by century, drilled to decade where the data is dense enough, derived
  from each record's earliest event start_date. Records with no usable date are
  reported honestly as an "undated" group, never dropped. Each dated period bar
  links into the canonical GLAM browse, filtered to that date range; the undated
  group and pre-year-0 periods carry no date-range link rather than a dead one.
  A calm empty-state when the catalogue has no dated published records yet.
  International, jurisdiction-neutral copy - the only calendar assumption is the
  Gregorian year already in the data.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Collection timeline'))

@php
    $centuries = $timeline['centuries'] ?? [];
    $undated = (int) ($timeline['undated']['count'] ?? 0);
    $datedTotal = (int) ($timeline['dated_total'] ?? 0);
    $maxCount = max(1, (int) ($timeline['max_count'] ?? 0));
    $minYear = $timeline['min_year'] ?? null;
    $maxYear = $timeline['max_year'] ?? null;
    $hasAny = ! empty($centuries) || $undated > 0;

    // CSS-only bar width as a percentage of the busiest bucket. A small floor so
    // a single-record period is still visible.
    $barPct = static function (int $count) use ($maxCount): float {
        if ($count <= 0) {
            return 0.0;
        }
        $pct = ($count / $maxCount) * 100.0;
        return max(2.0, min(100.0, $pct));
    };
@endphp

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-timeline fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Collection timeline') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('How the collection is spread across time.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Each bar below counts the published records whose earliest recorded date falls in that period. Pick a century - or a decade where the holdings are dense - to browse everything from that span. Records with no recorded date are shown honestly as their own group rather than hidden.') }}
        </p>
    </div>

    @if(! $hasAny)
        {{-- Calm empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-hourglass-half fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No dated records yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 40rem;">
                    {{ __('Once published records in this collection carry dates, this page will plot how the holdings are distributed across the centuries and decades - and let you step into any period to browse it. Check back as the catalogue grows.') }}
                </p>
            </div>
        </div>
    @else
        {{-- Summary line --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <p class="text-muted small mb-0">
                <i class="fas fa-circle-info me-1"></i>
                @if($datedTotal > 0 && $minYear !== null && $maxYear !== null)
                    {{ trans_choice(
                        '{1}:count dated published record, spanning :from to :to.|[2,*]:count dated published records, spanning :from to :to.',
                        $datedTotal,
                        ['count' => number_format($datedTotal), 'from' => $minYear, 'to' => $maxYear]
                    ) }}
                @elseif($datedTotal > 0)
                    {{ trans_choice('{1}:count dated published record.|[2,*]:count dated published records.', $datedTotal, ['count' => number_format($datedTotal)]) }}
                @else
                    {{ __('No records carry a usable date yet.') }}
                @endif
            </p>
            <a href="{{ url('/timeline.json') }}" class="badge text-bg-light border text-decoration-none"
               title="{{ __('The same buckets as machine-readable JSON') }}">
                <i class="fas fa-code me-1"></i>{{ __('JSON') }}
            </a>
        </div>

        {{-- CSS-only horizontal bars, one per century, with decade drill-downs --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                @foreach($centuries as $century)
                    @php
                        $cLabel = $century['label'] ?? '';
                        $cCount = (int) ($century['count'] ?? 0);
                        $cUrl = $century['browse_url'] ?? null;
                        $cDecades = $century['decades'] ?? [];
                        $cWidth = $barPct($cCount);
                    @endphp

                    <div class="mb-4">
                        {{-- Century bar --}}
                        @php
                            $centuryBarInner = '<div class="d-flex align-items-center justify-content-between">'
                                . '<span class="fw-semibold">' . e($cLabel) . '</span>'
                                . '<span class="badge text-bg-primary ms-2">' . number_format($cCount) . '</span>'
                                . '</div>';
                        @endphp

                        @if($cUrl)
                            <a href="{{ $cUrl }}" class="d-block text-decoration-none text-reset timeline-bar-link"
                               title="{{ __('Browse records from :period', ['period' => $cLabel]) }}">
                        @else
                            <div class="d-block text-reset" title="{{ __('No date-range browse link for this period') }}">
                        @endif
                            <div class="position-relative bg-light rounded overflow-hidden" style="height: 2.25rem;">
                                <div class="position-absolute top-0 start-0 h-100 bg-primary bg-opacity-25 rounded"
                                     style="width: {{ $cWidth }}%;"></div>
                                <div class="position-relative px-3 d-flex align-items-center justify-content-between h-100">
                                    <span class="fw-semibold">{{ $cLabel }}</span>
                                    <span class="badge text-bg-primary ms-2">{{ number_format($cCount) }}</span>
                                </div>
                            </div>
                        @if($cUrl)
                            </a>
                        @else
                            </div>
                        @endif

                        {{-- Decade drill-down (only where the century is dense enough) --}}
                        @if(! empty($cDecades))
                            <div class="ms-3 ms-md-4 mt-2">
                                @foreach($cDecades as $decade)
                                    @php
                                        $dLabel = $decade['label'] ?? '';
                                        $dCount = (int) ($decade['count'] ?? 0);
                                        $dUrl = $decade['browse_url'] ?? null;
                                        $dWidth = $barPct($dCount);
                                    @endphp
                                    <div class="mb-2">
                                        @if($dUrl)
                                            <a href="{{ $dUrl }}" class="d-block text-decoration-none text-reset timeline-bar-link"
                                               title="{{ __('Browse records from :period', ['period' => $dLabel]) }}">
                                        @else
                                            <div class="d-block text-reset">
                                        @endif
                                            <div class="position-relative bg-light rounded overflow-hidden" style="height: 1.6rem;">
                                                <div class="position-absolute top-0 start-0 h-100 bg-secondary bg-opacity-25 rounded"
                                                     style="width: {{ $dWidth }}%;"></div>
                                                <div class="position-relative px-3 d-flex align-items-center justify-content-between h-100 small">
                                                    <span>{{ $dLabel }}</span>
                                                    <span class="badge text-bg-secondary ms-2">{{ number_format($dCount) }}</span>
                                                </div>
                                            </div>
                                        @if($dUrl)
                                            </a>
                                        @else
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Honest "undated" group: reported, never dropped, no dead link --}}
        @if($undated > 0)
            @php $uWidth = $barPct($undated); @endphp
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 text-muted text-uppercase mb-3">
                        <i class="fas fa-circle-question me-1"></i>{{ __('Undated') }}
                    </h2>
                    <div class="position-relative bg-light rounded overflow-hidden" style="height: 2.25rem;">
                        <div class="position-absolute top-0 start-0 h-100 bg-secondary bg-opacity-25 rounded"
                             style="width: {{ $uWidth }}%;"></div>
                        <div class="position-relative px-3 d-flex align-items-center justify-content-between h-100">
                            <span class="fw-semibold">{{ __('No recorded date') }}</span>
                            <span class="badge text-bg-secondary ms-2">{{ number_format($undated) }}</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        {{ trans_choice(
                            '{1}:count published record has no recorded date, so it cannot be placed on the timeline. It is counted here rather than hidden.|[2,*]:count published records have no recorded date, so they cannot be placed on the timeline. They are counted here rather than hidden.',
                            $undated,
                            ['count' => number_format($undated)]
                        ) }}
                    </p>
                </div>
            </div>
        @endif
    @endif

</div>
@endsection
