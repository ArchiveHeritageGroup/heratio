{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_ratingStars.php --}}
@php
    $r = (float) ($rating ?? 0);
    $c = (int) ($count ?? 0);
    $fullStars = (int) floor($r);
    $halfStar = ($r - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
@endphp
<span class="text-warning" title="{{ number_format($r, 1) }}/5">
  @for ($i = 0; $i < $fullStars; $i++)
    <i class="fas fa-star"></i>
  @endfor
  @if ($halfStar)
    <i class="fas fa-star-half-alt"></i>
  @endif
  @for ($i = 0; $i < $emptyStars; $i++)
    <i class="far fa-star"></i>
  @endfor
</span>
@if ($c > 0)
  <small class="text-muted">({{ number_format($c) }})</small>
@endif
