{{-- User clearance badge --}}
{{-- Usage: @include('ahg-security-clearance::partials._userBadge', ['clearance' => $userClearance]) --}}
@if(!empty($clearance))
<span class="badge user-clearance-badge" style="background-color: {{ $clearance->color ?? '#666' }}"
      title="Clearance: {{ e($clearance->classification_name ?? '') }} — Expires: {{ $clearance->expires_at ?? 'Never' }}">
  <i class="fas fa-user-shield"></i> {{ e($clearance->classification_name ?? 'Cleared') }}
</span>
@endif
