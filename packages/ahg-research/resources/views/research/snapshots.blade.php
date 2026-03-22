{{-- Snapshots - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'collections'])@endsection
@section('title', 'Snapshots')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.collections') }}">Evidence Sets</a></li><li class="breadcrumb-item active">Snapshots</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-camera text-primary me-2"></i>Collection Snapshots</h1>
    <form method="POST" class="d-inline">@csrf <input type="hidden" name="collection_id" value="{{ $collection->id ?? 0 }}">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-camera me-1"></i>Take Snapshot</button>
    </form>
</div>
@if(!empty($snapshots))
<div class="card"><div class="card-body p-0">
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Title</th><th>Items</th><th>Hash</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($snapshots as $s)
            <tr>
                <td><strong>{{ e($s->title ?? 'Snapshot') }}</strong></td>
                <td>{{ $s->item_count ?? 0 }}</td>
                <td><code class="small">{{ Str::limit($s->hash_sha256 ?? '', 16, '') }}</code></td>
                <td class="small">{{ $s->created_at ?? '' }}</td>
                <td>
                    <a href="{{ route('research.dashboard', ['view_snapshot' => $s->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                    @if(!$loop->first)
                    <a href="{{ route('research.dashboard', ['compare_snapshots' => $s->id, 'compare_with' => $snapshots[0]->id ?? 0]) }}" class="btn btn-sm btn-outline-info"><i class="fas fa-not-equal"></i></a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div></div>
@else
<div class="alert alert-info">No snapshots yet. Take a snapshot to record the current state of this collection.</div>
@endif
@endsection