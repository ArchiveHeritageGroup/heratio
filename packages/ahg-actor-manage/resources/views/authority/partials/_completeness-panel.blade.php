@php
/**
 * Embeddable panel: Completeness score gauge for actor view pages.
 * Usage: @include('ahg-actor-manage::authority.partials._completeness-panel', ['actorId' => $actorId])
 */
$actorId = $actorId ?? 0;
if (!$actorId) return;

$completenessService = new \AhgActorManage\Services\AuthorityCompletenessService();
$comp = $completenessService->getCompleteness($actorId);
if (!$comp) return;

$score = $comp->completeness_score ?? 0;
$level = $comp->completeness_level ?? 'stub';
$levelColors = ['stub' => 'danger', 'minimal' => 'warning', 'partial' => 'info', 'full' => 'success'];
$color = $levelColors[$level] ?? 'secondary';
@endphp

<div class="card mb-3 authority-completeness-panel">
  <div class="card-header py-2" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-chart-bar me-1"></i>{{ __('Completeness') }}
  </div>
  <div class="card-body py-2">
    <div class="d-flex align-items-center">
      <div class="flex-grow-1 me-2">
        <div class="progress" style="height:20px">
          <div class="progress-bar bg-{{ $color }}"
               role="progressbar"
               style="width:{{ $score }}%"
               aria-valuenow="{{ $score }}"
               aria-valuemin="0" aria-valuemax="100">
            {{ $score }}%
          </div>
        </div>
      </div>
      <span class="badge bg-{{ $color }}">{{ ucfirst($level) }}</span>
    </div>
    @if ($comp->scored_at)
      <small class="text-muted">Last scored: {{ $comp->scored_at }}</small>
    @endif
  </div>
</div>
