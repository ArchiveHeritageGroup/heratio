{{-- Security Classification Badge for indexSuccess - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/_securityBadge.php --}}
@php
if (!($classification ?? null)) return;

$badgeClass = 'bg-success';
$alertClass = 'alert-info';

switch ($classification->classificationLevel ?? $classification->classification_level ?? 0) {
    case 1: $badgeClass = 'bg-info'; $alertClass = 'alert-info'; break;
    case 2: $badgeClass = 'bg-warning text-dark'; $alertClass = 'alert-warning'; break;
    case 3: $badgeClass = 'bg-orange'; $alertClass = 'alert-warning'; break;
    case 4: $badgeClass = 'bg-danger'; $alertClass = 'alert-danger'; break;
    case 5: $badgeClass = 'bg-purple'; $alertClass = 'alert-danger'; break;
}

$classificationName = $classification->classificationName ?? $classification->classification_name ?? $classification->name ?? 'Classified';
$handlingInstructions = $classification->handling_instructions ?? $classification->handlingInstructions ?? null;
$classificationIcon = $classification->classificationIcon ?? $classification->icon ?? 'fa-lock';
@endphp
<div class="alert {{ $alertClass }} d-flex align-items-center mb-3 py-2" role="alert">
  <div class="flex-grow-1">
    <strong>Security Classification:</strong>
    <span class="badge {{ $badgeClass }} ms-2">
      <i class="fas {{ $classificationIcon }} me-1"></i>
      {{ $classificationName }}
    </span>
    @if($handlingInstructions)
      <br><small class="text-muted">{{ $handlingInstructions }}</small>
    @endif
  </div>
</div>
<style>
.bg-orange { background-color: #fd7e14 !important; color: white; }
.bg-purple { background-color: #6f42c1 !important; color: white; }
</style>
