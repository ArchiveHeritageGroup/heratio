{{--
  Reusable inline bar-breakdown card for the public "Collection at a glance" page.

  Renders a titled card with a list of rows, each a label + count + percentage and a
  plain CSS-width bar (NO charting library). When a row carries a `url` (a deep-link
  into the public GLAM browse), the label is a link; otherwise it is plain text - so
  the breakdown degrades gracefully when the browse route is unavailable.

  Inputs:
    $title    string  card heading
    $icon     string  Font Awesome class for the heading icon
    $rows     array   each: ['label'=>string,'count'=>int,'pct'=>float,'url'=>?string]
    $total    int     grand total (used only for safety; rows carry their own pct)
    $barClass string  Bootstrap bg-* class for the bar fill (default bg-primary)
    $note     ?string optional small print under the list

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@php
    $rows = $rows ?? [];
    $barClass = $barClass ?? 'bg-primary';
    $fmtInt = fn ($n) => number_format((int) ($n ?? 0));
    // Trim trailing zero/decimal from a 1dp percentage for a tidy "12%" / "12.5%".
    $fmtPct = fn ($p) => rtrim(rtrim(number_format((float) ($p ?? 0), 1), '0'), '.');
@endphp

<div class="card h-100 shadow-sm">
  <div class="card-body">
    <h2 class="h5 card-title mb-3">
      <i class="{{ $icon ?? 'fas fa-chart-bar' }} me-2 text-muted"></i>{{ $title ?? '' }}
    </h2>

    @if(empty($rows))
      <p class="text-muted small mb-0">{{ __('Nothing recorded here yet.') }}</p>
    @else
      @foreach($rows as $row)
        @php
          $label = (string) ($row['label'] ?? __('Unspecified'));
          $count = (int) ($row['count'] ?? 0);
          $pct = max(0, min(100, (float) ($row['pct'] ?? 0)));
          $url = $row['url'] ?? null;
        @endphp
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="small text-truncate" style="max-width:70%" title="{{ $label }}">
              @if(!empty($url))
                <a href="{{ $url }}" class="text-decoration-none">{{ $label }}</a>
              @else
                {{ $label }}
              @endif
            </span>
            <span class="small text-muted text-nowrap">
              {{ $fmtInt($count) }} <span class="text-nowrap">({{ $fmtPct($pct) }}%)</span>
            </span>
          </div>
          <div class="progress" role="presentation" style="height:.5rem;background:#eee">
            <div class="progress-bar {{ $barClass }}" style="width:{{ $pct }}%"></div>
          </div>
        </div>
      @endforeach

      @if(!empty($note))
        <p class="small text-muted mb-0 mt-3"><i class="fas fa-info-circle me-1"></i>{{ $note }}</p>
      @endif
    @endif
  </div>
</div>
