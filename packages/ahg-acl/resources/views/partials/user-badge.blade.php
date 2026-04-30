{{-- User Clearance Badge Component - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/_userBadge.php --}}
@if(!($clearance ?? null))
  <span class="badge bg-secondary security-badge" title="{{ __('No clearance') }}">
    <i class="fa fa-user"></i> {{ __('No Clearance') }}
  </span>
@else
  @php
    $badgeClass = 'bg-secondary';
    switch ($clearance->classificationLevel ?? $clearance->classification_level ?? $clearance->level ?? 0) {
        case 0: $badgeClass = 'bg-success'; break;
        case 1: $badgeClass = 'bg-info'; break;
        case 2: $badgeClass = 'bg-warning text-dark'; break;
        case 3: $badgeClass = 'bg-orange'; break;
        case 4: $badgeClass = 'bg-danger'; break;
        case 5: $badgeClass = 'bg-purple'; break;
    }

    $expired = ($clearance->expiresAt ?? $clearance->expires_at ?? null) && strtotime($clearance->expiresAt ?? $clearance->expires_at) < time();
  @endphp
  <span class="badge {{ $expired ? 'bg-secondary' : $badgeClass }} security-badge"
        title="{{ $clearance->classificationName ?? $clearance->name ?? '' }}{{ $expired ? ' (Expired)' : '' }}">
    <i class="{{ $clearance->classificationIcon ?? $clearance->icon ?? 'fa-user-shield' }}"></i>
    {{ $clearance->classificationName ?? $clearance->name ?? '' }}
    @if($expired)
      <small>(Expired)</small>
    @endif
  </span>
@endif
