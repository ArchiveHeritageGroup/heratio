{{-- Reproducibility Pack - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Reproducibility Pack')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">Reproducibility Pack</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Reproducibility Pack</h1>
    <button id="downloadPack" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>Download Pack (JSON)</button>
</div>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Project Metadata</h5></div><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Title</dt><dd class="col-sm-9">{{ e($project->title ?? '') }}</dd>
        <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-{{ match($project->status ?? '') { 'active' => 'success', 'completed' => 'primary', 'on_hold' => 'warning', default => 'secondary' } }}">{{ ucfirst($project->status ?? 'unknown') }}</span></dd>
        @if($project->description ?? false)<dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ e($project->description) }}</dd>@endif
    </dl>
</div></div>
<div class="row">
    <div class="col-md-6"><div class="card mb-4"><div class="card-header">Evidence Sets ({{ count($collections ?? []) }})</div><ul class="list-group list-group-flush">
        @forelse($collections ?? [] as $c)<li class="list-group-item d-flex justify-content-between">{{ e($c->name ?? '') }}<span class="badge bg-secondary">{{ $c->item_count ?? 0 }} items</span></li>@empty <li class="list-group-item text-muted">None</li>@endforelse
    </ul></div></div>
    <div class="col-md-6"><div class="card mb-4"><div class="card-header">Assertions ({{ count($assertions ?? []) }})</div><ul class="list-group list-group-flush">
        @forelse($assertions ?? [] as $a)<li class="list-group-item"><strong>{{ e(Str::limit($a->claim ?? '', 50)) }}</strong><br><small class="badge bg-{{ ($a->status ?? '') === 'approved' ? 'success' : 'warning' }}">{{ ucfirst($a->status ?? '') }}</small></li>@empty <li class="list-group-item text-muted">None</li>@endforelse
    </ul></div></div>
</div>
<script>
document.getElementById('downloadPack')?.addEventListener('click', function() {
    var pack = @json($packData ?? []);
    var blob = new Blob([JSON.stringify(pack, null, 2)], {type: 'application/json'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'reproducibility-pack.json';
    a.click();
});
</script>
@endsection