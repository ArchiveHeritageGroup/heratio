{{-- Partial: Identifier panel --}}
@props(['identifiers' => collect()])
<div class="card mb-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-fingerprint me-2"></i>Identifiers</div>
<ul class="list-group list-group-flush">@forelse($identifiers as $id)<li class="list-group-item d-flex justify-content-between"><span>{{ $id->type ?? 'ID' }}</span><code>{{ $id->value ?? '-' }}</code></li>@empty<li class="list-group-item text-muted">No identifiers</li>@endforelse</ul></div>
