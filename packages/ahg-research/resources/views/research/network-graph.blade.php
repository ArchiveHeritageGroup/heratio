{{-- Network Graph - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Network Graph')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">Network Graph</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-share-alt text-primary me-2"></i>Network Graph</h1>
<div class="card mb-4"><div class="card-body p-0">
    <div id="networkGraphContainer" style="width:100%;height:600px;background:#1a1a2e;border-radius:0.375rem;"></div>
</div></div>
<div class="row">
    <div class="col-md-6"><div class="card"><div class="card-header">Nodes ({{ count($nodes ?? []) }})</div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Label</th><th>Type</th><th>Connections</th></tr></thead><tbody>
            @foreach($nodes ?? [] as $node)<tr><td>{{ e($node->label ?? '') }}</td><td><span class="badge bg-secondary">{{ $node->type ?? '' }}</span></td><td>{{ $node->connections ?? 0 }}</td></tr>@endforeach
        </tbody></table></div></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">Relationships ({{ count($edges ?? []) }})</div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>From</th><th>To</th><th>Type</th></tr></thead><tbody>
            @foreach($edges ?? [] as $edge)<tr><td>{{ e($edge->from_label ?? '') }}</td><td>{{ e($edge->to_label ?? '') }}</td><td>{{ e($edge->relationship ?? '') }}</td></tr>@endforeach
        </tbody></table></div></div></div></div>
</div>
@endsection