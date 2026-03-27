{{-- Security Badge Component - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/_badge.php --}}
@if(!($classification ?? null))
  <span class="badge bg-success security-badge" title="Public">
    <i class="fa fa-globe"></i> Public
  </span>
@else
  @php
    $badgeClass = 'bg-secondary';
    switch ($classification->classificationLevel ?? $classification->classification_level ?? 0) {
        case 0: $badgeClass = 'bg-success'; break;
        case 1: $badgeClass = 'bg-info'; break;
        case 2: $badgeClass = 'bg-warning text-dark'; break;
        case 3: $badgeClass = 'bg-orange'; break;
        case 4: $badgeClass = 'bg-danger'; break;
        case 5: $badgeClass = 'bg-purple'; break;
    }
  @endphp
  <span class="badge {{ $badgeClass }} security-badge"
        title="{{ $classification->classificationName ?? $classification->name ?? '' }}">
    <i class="{{ $classification->classificationIcon ?? $classification->icon ?? 'fa-lock' }}"></i>
    {{ $classification->classificationName ?? $classification->name ?? '' }}
  </span>
@endif
