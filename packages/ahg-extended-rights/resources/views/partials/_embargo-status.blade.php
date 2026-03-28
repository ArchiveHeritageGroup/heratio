{{-- Embargo status badge for show pages --}}
{{-- Usage: @include('ahg-extended-rights::partials._embargo-status', ['embargo' => $activeEmbargo]) --}}
@if(!empty($embargo))
<div class="alert alert-{{ $embargo->embargo_type === 'full' ? 'danger' : 'warning' }} d-flex align-items-center p-2 mb-2">
  <i class="fas fa-{{ $embargo->embargo_type === 'full' ? 'lock' : 'exclamation-triangle' }} me-2"></i>
  <div>
    <strong>{{ ucfirst(str_replace('_', ' ', $embargo->embargo_type ?? 'Embargo')) }}</strong>
    @if(!empty($embargo->end_date))
      — Until {{ $embargo->end_date }}
    @elseif($embargo->is_perpetual ?? false)
      — Perpetual
    @endif
    @if(!empty($embargo->public_message))
      <br><small>{{ e($embargo->public_message) }}</small>
    @endif
  </div>
</div>
@endif
