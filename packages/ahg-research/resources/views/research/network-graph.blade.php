{{-- Network Graph --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Network Graph')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Network Graph</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-share-alt text-primary me-2"></i>Network Graph</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" id="exportPng" disabled><i class="fas fa-image me-1"></i>Export PNG</button>
        <button class="btn btn-outline-secondary btn-sm" id="exportJson" disabled><i class="fas fa-download me-1"></i>Export JSON</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-end">
            <div class="col-md-4">
                <label class="form-label">Assertion Type</label>
                <select id="filterAssertionType" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="biographical">Biographical</option>
                    <option value="chronological">Chronological</option>
                    <option value="spatial">Spatial</option>
                    <option value="relational">Relational</option>
                    <option value="attributive">Attributive</option>
                </select>
            </div>
            <div class="col-md-4">
                <button id="applyFilter" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div id="network-container" style="width:100%; height:600px; background:#1a1a2e; border-radius:0.375rem;"></div>
    </div>
</div>

<script src="https://unpkg.com/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('network-container');

    function loadNetwork() {
        var assertionType = document.getElementById('filterAssertionType').value;
        var url = window.location.pathname + '?';
        if (assertionType) url += 'assertion_type=' + assertionType;

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var nodes = new vis.DataSet(data.nodes || []);
                var edges = new vis.DataSet(data.edges || []);
                var network = new vis.Network(container, { nodes: nodes, edges: edges }, {
                    nodes: { shape: 'dot', size: 16, font: { color: '#ffffff', size: 12 } },
                    edges: { color: { color: '#848484' }, arrows: 'to', font: { color: '#cccccc', size: 10 } },
                    physics: { stabilization: { iterations: 150 } },
                    interaction: { hover: true, tooltipDelay: 200 }
                });
            })
            .catch(function(err) {
                container.innerHTML = '<div class="text-center text-white py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><p>Failed to load network data.</p></div>';
            });
    }

    loadNetwork();
    document.getElementById('applyFilter').addEventListener('click', loadNetwork);
});
</script>
@endsection
