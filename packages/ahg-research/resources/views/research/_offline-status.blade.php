@php
  $map = ['complete' => 'success', 'failed' => 'danger', 'running' => 'info', 'pending' => 'secondary'];
  $badge = $map[$p->status] ?? 'secondary';
  $label = $p->status === 'running' ? ('Building '.((int) $p->progress).'%') : $p->status;
@endphp
<span class="badge bg-{{ $badge }} text-capitalize">{{ $label }}</span>
