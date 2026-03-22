{{-- View Snapshot - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'collections'])@endsection
@section('title', 'Snapshot Details')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.collections') }}">Evidence Sets</a></li><li class="breadcrumb-item active">Snapshot</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-camera text-primary me-2"></i>{{ e($snapshot->title ?? 'Snapshot') }}</h1>
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Snapshot Metadata</div><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Collection</dt><dd class="col-sm-9">{{ e($snapshot->collection_name ?? '') }}</dd>
        <dt class="col-sm-3">Items</dt><dd class="col-sm-9">{{ $snapshot->item_count ?? 0 }}</dd>
        <dt class="col-sm-3">SHA-256 Hash</dt><dd class="col-sm-9"><code>{{ e($snapshot->hash_sha256 ?? '') }}</code></dd>
        <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $snapshot->created_at ?? '' }}</dd>
    </dl>
</div></div>
@if(!empty($items))
<div class="card"><div class="card-header">Snapshot Items ({{ count($items) }})</div><div class="card-body p-0">
    <table class="table table-hover mb-0"><thead class="table-light"><tr><th>Item</th><th>Type</th><th>Added</th></tr></thead><tbody>
        @foreach($items as $item)<tr><td>{{ e($item->title ?? 'Item #' . ($item->object_id ?? '')) }}</td><td><span class="badge bg-secondary">{{ $item->object_type ?? '' }}</span></td><td class="small">{{ $item->added_at ?? '' }}</td></tr>@endforeach
    </tbody></table>
</div></div>
@endif
<div class="mt-3"><a href="{{ url()->previous() }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a></div>
@endsection