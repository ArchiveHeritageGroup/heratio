{{-- Partial: External links --}}
@props(['links' => collect()])
<div class="card mb-3"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-external-link-alt me-2"></i>External Links</div>
<ul class="list-group list-group-flush">@forelse($links as $link)<li class="list-group-item"><a href="{{ $link->url ?? '#' }}" target="_blank">{{ $link->label ?? $link->url ?? '-' }} <i class="fas fa-external-link-alt ms-1 small"></i></a></li>@empty<li class="list-group-item text-muted">No external links</li>@endforelse</ul></div>
