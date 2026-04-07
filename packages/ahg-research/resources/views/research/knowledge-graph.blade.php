{{-- Knowledge Graph --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Knowledge Graph')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Knowledge Graph</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-project-diagram text-primary me-2"></i>Knowledge Graph</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Project</a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Filters</h6>
    </div>
    <div class="card-body">
        <div class="row">
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
                <label class="form-label">Status</label>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="proposed">Proposed</option>
                    <option value="verified">Verified</option>
                    <option value="disputed">Disputed</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button id="applyFilters" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div id="knowledge-graph-container" style="width:100%; height:700px; background:#1a1a2e; border-radius:0.375rem;"></div>
    </div>
</div>

<script src="https://unpkg.com/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('knowledge-graph-container');
    var projectId = {{ $project->id ?? 0 }};

    function loadGraph() {
        var assertionType = document.getElementById('filterAssertionType').value;
        var status = document.getElementById('filterStatus').value;
        var url = window.location.pathname + '?';
        if (assertionType) url += 'assertion_type=' + assertionType + '&';
        if (status) url += 'status=' + status;

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
                container.innerHTML = '<div class="text-center text-white py-5"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><p>Failed to load graph data.</p></div>';
            });
    }

    loadGraph();

    document.getElementById('applyFilters').addEventListener('click', loadGraph);
});
</script>
@endsection
