{{-- View Workspace - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspaces'])@endsection
@section('title', 'Workspace Details')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.workspaces') }}">Workspaces</a></li><li class="breadcrumb-item active">{{ e($workspace->name ?? '') }}</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-users text-primary me-2"></i>{{ e($workspace->name ?? 'Workspace') }}</h1>
    <span class="badge bg-{{ ($workspace->is_active ?? true) ? 'success' : 'secondary' }} fs-6">{{ ($workspace->is_active ?? true) ? 'Active' : 'Archived' }}</span>
</div>
<div class="row"><div class="col-md-8">
@if($workspace->description ?? false)<div class="card mb-4"><div class="card-body">{{ e($workspace->description) }}</div></div>@endif
<div class="card mb-4"><div class="card-header">Shared Collections</div><div class="card-body p-0">
    @if(!empty($collections))
    <table class="table table-hover mb-0"><thead class="table-light"><tr><th>Collection</th><th>Items</th><th>Owner</th></tr></thead><tbody>
        @foreach($collections as $c)<tr><td><a href="{{ route('research.viewCollection', ['id' => $c->id]) }}">{{ e($c->name ?? '') }}</a></td><td>{{ $c->item_count ?? 0 }}</td><td>{{ e(($c->owner_first_name ?? '') . ' ' . ($c->owner_last_name ?? '')) }}</td></tr>@endforeach
    </tbody></table>
    @else <div class="text-center py-4 text-muted">No shared collections.</div>@endif
</div></div>
</div><div class="col-md-4">
<div class="card mb-4"><div class="card-header"><h6 class="mb-0">Members ({{ count($members ?? []) }})</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($members ?? [] as $m)<li class="list-group-item d-flex justify-content-between"><span>{{ e(($m->first_name ?? '') . ' ' . ($m->last_name ?? '')) }}</span><span class="badge bg-{{ $m->role === 'admin' ? 'danger' : 'secondary' }}">{{ ucfirst($m->role ?? '') }}</span></li>@empty
        <li class="list-group-item text-muted small">No members.</li>@endforelse
    </ul>
</div>
<div class="card"><div class="card-header"><h6 class="mb-0">Details</h6></div><div class="card-body small">
    <dl class="row mb-0"><dt class="col-5">Created</dt><dd class="col-7">{{ $workspace->created_at ?? '' }}</dd><dt class="col-5">Owner</dt><dd class="col-7">{{ e(($workspace->owner_first_name ?? '') . ' ' . ($workspace->owner_last_name ?? '')) }}</dd></dl>
</div></div>
</div></div>
@endsection