@php
/**
 * Inline completeness badge for browse result rows.
 * Usage: @include('ahg-actor-manage::authority.partials._completeness-badge', ['actorId' => $actorId])
 */
$actorId = $actorId ?? 0;
if (!$actorId) return;

$comp = \Illuminate\Support\Facades\DB::table('ahg_actor_completeness')
    ->where('actor_id', $actorId)
    ->first();

if (!$comp) return;

$levelColors = ['stub' => 'danger', 'minimal' => 'warning', 'partial' => 'info', 'full' => 'success'];
$color = $levelColors[$comp->completeness_level] ?? 'secondary';
@endphp
<span class="badge bg-{{ $color }}" title="Completeness: {{ $comp->completeness_score }}%">
  {{ $comp->completeness_score }}%
</span>
