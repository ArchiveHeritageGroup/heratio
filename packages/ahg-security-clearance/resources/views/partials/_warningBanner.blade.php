{{-- Classification warning banner --}}
{{-- Usage: @include('ahg-security-clearance::partials._warningBanner', ['classification' => $classification]) --}}
@if(!empty($classification) && ($classification->level ?? 0) >= 1)
<div class="alert alert-{{ ($classification->level ?? 0) >= 4 ? 'danger' : (($classification->level ?? 0) >= 2 ? 'warning' : 'info') }} d-flex align-items-center mb-3"
     role="alert">
  <i class="fas fa-{{ ($classification->level ?? 0) >= 4 ? 'exclamation-triangle' : 'info-circle' }} me-2 fa-lg"></i>
  <div>
    <strong>{{ e($classification->name ?? 'Classified') }}</strong>
    — This record is classified.
    @if(!empty($classification->handling_instructions))
      {{ e($classification->handling_instructions) }}
    @else
      Handle according to security policy.
    @endif
  </div>
</div>
@endif
