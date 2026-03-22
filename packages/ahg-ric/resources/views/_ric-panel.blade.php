{{-- Partial: RiC explorer panel --}}
@props(['entity' => null])
<div class="card mb-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-project-diagram me-2"></i>RiC Explorer</div><div class="card-body"><p class="text-muted mb-0">RiC entity explorer for: {{ $entity->name ?? 'Record' }}</p></div></div>
