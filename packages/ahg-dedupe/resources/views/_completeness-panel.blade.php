{{-- Partial: Completeness panel --}}
@props(['actor' => null])
<div class="card mb-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-tasks me-2"></i>{{ __('Completeness') }}</div><div class="card-body">
  <div class="progress mb-2" style="height:24px"><div class="progress-bar bg-{{ ($actor->completeness_score ?? 0) > 70 ? 'success' : 'warning' }}" style="width:{{ $actor->completeness_score ?? 0 }}%">{{ $actor->completeness_score ?? 0 }}%</div></div>
  <small class="text-muted">Level: {{ ucfirst($actor->completeness_level ?? 'unknown') }}</small>
</div></div>
