{{-- Knowledge Graph - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'Knowledge Graph')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Knowledge Graph</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-project-diagram text-primary me-2"></i>Knowledge Graph</h1>
<div class="row mb-3">
    <div class="col-md-8">
        <div class="card"><div class="card-body p-0">
            <div id="knowledgeGraphContainer" style="width:100%;height:600px;background:#1a1a2e;border-radius:0.375rem;"></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4"><div class="card-header"><h6 class="mb-0">Filters</h6></div><div class="card-body">
            <div class="mb-3"><label class="form-label">Entity Types</label>
                @foreach(['person','place','event','document','concept'] as $t)
                <div class="form-check"><input type="checkbox" class="form-check-input graph-filter" id="filter_{{ $t }}" value="{{ $t }}" checked><label class="form-check-label" for="filter_{{ $t }}">{{ ucfirst($t) }}s</label></div>
                @endforeach
            </div>
            <div class="mb-3"><label class="form-label">Depth</label><input type="range" class="form-range" id="graphDepth" min="1" max="5" value="2"><small class="text-muted">Levels: <span id="depthLabel">2</span></small></div>
        </div></div>
        <div class="card"><div class="card-header"><h6 class="mb-0">Legend</h6></div><div class="card-body small">
            <div class="mb-1"><span class="badge" style="background:#4CAF50">&nbsp;</span> Person</div>
            <div class="mb-1"><span class="badge" style="background:#2196F3">&nbsp;</span> Place</div>
            <div class="mb-1"><span class="badge" style="background:#FF9800">&nbsp;</span> Event</div>
            <div class="mb-1"><span class="badge" style="background:#9C27B0">&nbsp;</span> Document</div>
            <div class="mb-0"><span class="badge" style="background:#607D8B">&nbsp;</span> Concept</div>
        </div></div>
    </div>
</div>
<div class="card"><div class="card-header"><h6 class="mb-0">Graph Statistics</h6></div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col"><h4>{{ $stats['nodes'] ?? 0 }}</h4><small class="text-muted">Nodes</small></div>
            <div class="col"><h4>{{ $stats['edges'] ?? 0 }}</h4><small class="text-muted">Edges</small></div>
            <div class="col"><h4>{{ $stats['clusters'] ?? 0 }}</h4><small class="text-muted">Clusters</small></div>
            <div class="col"><h4>{{ number_format($stats['density'] ?? 0, 3) }}</h4><small class="text-muted">Density</small></div>
        </div>
    </div>
</div>
@endsection