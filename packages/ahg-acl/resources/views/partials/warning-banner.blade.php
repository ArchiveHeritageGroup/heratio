{{-- Security Warning Banner - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/_warningBanner.php --}}
@php
if (!($classification ?? null)) return;

$bannerClass = 'alert-secondary';
$borderClass = '';
switch ($classification->classificationLevel ?? $classification->classification_level ?? 0) {
    case 1:
        $bannerClass = 'alert-info';
        break;
    case 2:
        $bannerClass = 'alert-warning';
        break;
    case 3:
        $bannerClass = 'alert-warning';
        $borderClass = 'security-classified';
        break;
    case 4:
        $bannerClass = 'alert-danger';
        $borderClass = 'security-secret';
        break;
    case 5:
        $bannerClass = 'alert-danger';
        $borderClass = 'security-top-secret';
        break;
}
@endphp

<div class="alert {{ $bannerClass }} {{ $borderClass }} d-flex align-items-center mb-3" role="alert">
  <i class="{{ $classification->classificationIcon ?? $classification->icon ?? 'fa-lock' }} fa-2x me-3"></i>
  <div>
    <strong>CLASSIFIED: {{ strtoupper($classification->classificationName ?? $classification->name ?? '') }}</strong>
    @if($classification->handlingInstructions ?? $classification->handling_instructions ?? null)
      <br><small>{{ $classification->handlingInstructions ?? $classification->handling_instructions }}</small>
    @endif
  </div>
  <div class="ms-auto">
    <span class="badge" style="background-color: {{ $classification->classificationColor ?? $classification->color ?? '#666' }}; font-size: 1rem;">
      Level {{ $classification->classificationLevel ?? $classification->classification_level ?? 0 }}
    </span>
  </div>
</div>
