{{-- Classification badge component --}}
{{-- Usage: @include('ahg-security-clearance::partials._badge', ['classification' => $classification]) --}}
@if(!empty($classification))
<span class="badge security-badge" style="background-color: {{ $classification->color ?? '#666' }}; {{ ($badgeSize ?? '') === 'lg' ? 'font-size: 1em; padding: 0.4em 0.8em;' : '' }}">
  <i class="fas fa-{{ ($classification->level ?? 0) >= 4 ? 'lock' : (($classification->level ?? 0) >= 2 ? 'shield-alt' : 'globe') }}"></i>
  {{ e($classification->name ?? 'Unknown') }}
</span>
@endif
