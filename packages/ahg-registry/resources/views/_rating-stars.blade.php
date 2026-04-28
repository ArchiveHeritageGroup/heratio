{{--
  Rating stars (FontAwesome 5).
  Vars: $rating (0..5 float), $count (int votes — optional).

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $r = (float) ($rating ?? 0);
    $c = (int) ($count ?? 0);
    $full = (int) floor($r);
    $half = ($r - $full) >= 0.5;
    $empty = 5 - $full - ($half ? 1 : 0);
@endphp
<span class="text-warning" title="{{ number_format($r, 1) }}/5">
    @for ($i = 0; $i < $full; $i++)<i class="fas fa-star"></i>@endfor
    @if ($half)<i class="fas fa-star-half-alt"></i>@endif
    @for ($i = 0; $i < $empty; $i++)<i class="far fa-star"></i>@endfor
</span>
@if ($c > 0)
    <small class="text-muted">({{ number_format($c) }})</small>
@endif
