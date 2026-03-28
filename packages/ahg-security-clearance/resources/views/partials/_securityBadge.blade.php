{{-- Object security classification badge (for show pages) --}}
{{-- Usage: @include('ahg-security-clearance::partials._securityBadge', ['classification' => $objectClassification]) --}}
@if(!empty($classification) && ($classification->level ?? 0) >= 1)
<span class="badge security-object-badge" style="background-color: {{ $classification->color ?? '#666' }}; font-size: 0.85em;"
      title="{{ e($classification->description ?? '') }}">
  <i class="fas fa-lock"></i> {{ e($classification->name ?? 'Classified') }}
</span>
@endif
