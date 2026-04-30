{{-- Compare Snapshots - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'collections'])
@endsection
@section('title', 'Compare Snapshots')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Compare Snapshots</li></ol></nav>
<h1 class="h2 mb-4">{{ __('Compare Snapshots') }}</h1>
<div class="row mb-4">
    <div class="col-md-6"><div class="card"><div class="card-header bg-primary text-white"><h6 class="mb-0">{{ e($snapshotA->title ?? 'Snapshot A') }}</h6></div><div class="card-body"><small>Items: {{ (int)($snapshotA->item_count ?? 0) }} | Hash: <code>{{ Str::limit($snapshotA->hash_sha256 ?? '', 12, '') }}</code></small></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header bg-info text-white"><h6 class="mb-0">{{ e($snapshotB->title ?? 'Snapshot B') }}</h6></div><div class="card-body"><small>Items: {{ (int)($snapshotB->item_count ?? 0) }} | Hash: <code>{{ Str::limit($snapshotB->hash_sha256 ?? '', 12, '') }}</code></small></div></div></div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="card border-success mb-3"><div class="card-header bg-success text-white">Added ({{ count($diff['added'] ?? []) }})</div>
            <ul class="list-group list-group-flush">
                @foreach(($diff['added'] ?? []) as $item)<li class="list-group-item small">{{ e($item->object_type . ':' . $item->object_id) }}</li>@endforeach
                @if(empty($diff['added']))<li class="list-group-item text-muted small">None</li>@endif
            </ul>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger mb-3"><div class="card-header bg-danger text-white">Removed ({{ count($diff['removed'] ?? []) }})</div>
            <ul class="list-group list-group-flush">
                @foreach(($diff['removed'] ?? []) as $item)<li class="list-group-item small">{{ e($item->object_type . ':' . $item->object_id) }}</li>@endforeach
                @if(empty($diff['removed']))<li class="list-group-item text-muted small">None</li>@endif
            </ul>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning mb-3"><div class="card-header bg-warning text-dark">Modified ({{ count($diff['modified'] ?? []) }})</div>
            <ul class="list-group list-group-flush">
                @foreach(($diff['modified'] ?? []) as $item)<li class="list-group-item small">{{ e($item->object_type . ':' . $item->object_id) }}</li>@endforeach
                @if(empty($diff['modified']))<li class="list-group-item text-muted small">None</li>@endif
            </ul>
        </div>
    </div>
</div>
<a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
@endsection