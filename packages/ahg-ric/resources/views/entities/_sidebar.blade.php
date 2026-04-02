{{-- RiC Entity Right Sidebar — Actions + Metadata --}}
@php
    $ricOType = $ricOType ?? 'Entity';
    $browseRoute = $browseRoute ?? '#';
    $typeSlug = $typeSlug ?? 'activities';
@endphp

{{-- Actions --}}
<div class="card mb-3">
    <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-tools me-1"></i> Actions</h6></div>
    <div class="card-body p-2">
        <a href="{{ $browseRoute }}" class="btn btn-outline-secondary btn-sm w-100 mb-1 text-start">
            <i class="fas fa-arrow-left me-1"></i> Browse {{ ucfirst($typeSlug) }}
        </a>
        <a href="{{ route('ric.entities.edit', [$typeSlug, $entity->slug]) }}" class="btn btn-outline-primary btn-sm w-100 mb-1 text-start">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        <a href="{{ route('ric.explorer') }}?id={{ $entity->id }}" class="btn btn-outline-info btn-sm w-100 mb-1 text-start">
            <i class="fas fa-project-diagram me-1"></i> Graph Explorer
        </a>
        <a href="{{ route('ric.export-jsonld') }}?id={{ $entity->id }}" class="btn btn-outline-success btn-sm w-100 mb-1 text-start" target="_blank">
            <i class="fas fa-code me-1"></i> JSON-LD Export
        </a>
        <a href="{{ route('ric.explorer') }}?id={{ $entity->id }}&view=timeline" class="btn btn-outline-warning btn-sm w-100 mb-1 text-start">
            <i class="fas fa-clock me-1"></i> Timeline
        </a>
        <form method="post" action="{{ route('ric.entities.destroy-form', [$typeSlug, $entity->slug]) }}" onsubmit="return confirm('Delete this {{ strtolower($ricOType) }}?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm w-100 text-start"><i class="fas fa-trash me-1"></i> Delete</button>
        </form>
    </div>
</div>

{{-- Metadata --}}
<div class="card">
    <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-info-circle me-1"></i> Metadata</h6></div>
    <div class="card-body p-2">
        <table class="table table-sm table-borderless mb-0" style="font-size:0.85rem">
            <tr><td class="text-muted">RiC-O Type</td><td><code>rico:{{ $ricOType }}</code></td></tr>
            <tr><td class="text-muted">ID</td><td>{{ $entity->id }}</td></tr>
            <tr><td class="text-muted">Slug</td><td><code style="word-break:break-all">{{ $entity->slug }}</code></td></tr>
            @if(isset($entity->authority_uri) && $entity->authority_uri)
            <tr><td class="text-muted">Authority</td><td><a href="{{ $entity->authority_uri }}" target="_blank"><i class="fas fa-external-link-alt"></i> Link</a></td></tr>
            @endif
            <tr><td class="text-muted">Created</td><td>{{ $entity->created_at ? \Carbon\Carbon::parse($entity->created_at)->format('Y-m-d H:i') : '' }}</td></tr>
            <tr><td class="text-muted">Updated</td><td>{{ $entity->updated_at ? \Carbon\Carbon::parse($entity->updated_at)->format('Y-m-d H:i') : '' }}</td></tr>
        </table>
    </div>
</div>
