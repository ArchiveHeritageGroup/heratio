{{-- View Snapshot --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.snapshots', $project->id) }}">Snapshots</a></li>
        <li class="breadcrumb-item active">{{ e($snapshot->title) }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">{{ e($snapshot->title) }}</h1>
    <span class="badge bg-{{ ($snapshot->status ?? '') === 'active' ? 'success' : 'secondary' }} fs-6">{{ ucfirst($snapshot->status ?? 'active') }}</span>
</div>

{{-- Snapshot metadata --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Snapshot Details</h5></div>
    <div class="card-body">
        <dl class="row mb-0">
            @if($snapshot->description ?? null)
            <dt class="col-sm-3">Description</dt>
            <dd class="col-sm-9">{{ e($snapshot->description) }}</dd>
            @endif
            <dt class="col-sm-3">Items</dt>
            <dd class="col-sm-9">{{ (int)($snapshot->item_count ?? 0) }}</dd>
            <dt class="col-sm-3">SHA256 Hash</dt>
            <dd class="col-sm-9"><code>{{ $snapshot->hash_sha256 ?? 'Not computed' }}</code></dd>
            <dt class="col-sm-3">Created</dt>
            <dd class="col-sm-9">{{ $snapshot->created_at ?? '' }}</dd>
            @if($snapshot->frozen_at ?? null)
            <dt class="col-sm-3">Frozen at</dt>
            <dd class="col-sm-9">{{ $snapshot->frozen_at }}</dd>
            @endif
            @if($snapshot->citation_id ?? null)
            <dt class="col-sm-3">Citation ID</dt>
            <dd class="col-sm-9"><code>{{ $snapshot->citation_id }}</code></dd>
            @endif
        </dl>
    </div>
</div>

{{-- Items --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Snapshot Items</h5>
        <span class="badge bg-primary">{{ count($items) }}</span>
    </div>
    @if(!empty($items))
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Object</th><th>Type</th><th>Slug</th></tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        @if($item->slug ?? null)
                            <a href="{{ url('/' . $item->slug) }}">{{ e($item->object_title ?? 'Untitled') }}</a>
                        @else
                            {{ e($item->object_title ?? 'Object #' . $item->object_id) }}
                        @endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ $item->object_type ?? 'information_object' }}</span></td>
                    <td><small class="text-muted">{{ $item->slug ?? '' }}</small></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="card-body text-muted">No items in this snapshot.</div>
    @endif
</div>

<a href="{{ route('research.snapshots', $project->id) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Snapshots</a>
@endsection
