{{-- Knowledge Graph — cloned from AtoM ahgResearchPlugin/knowledgeGraphSuccess.php --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">Knowledge Graph</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Knowledge Graph</h1>
    <div class="d-flex gap-2">
        <input type="text" id="nodeSearch" class="form-control form-control-sm" style="width:160px;" placeholder="Search nodes...">
        <select id="filterType" class="form-select form-select-sm" style="width:auto;">
            <option value="">All Types</option>
            <option value="biographical">Biographical</option>
            <option value="chronological">Chronological</option>
            <option value="spatial">Spatial</option>
            <option value="relational">Relational</option>
            <option value="attributive">Attributive</option>
        </select>
        <div class="btn-group btn-group-sm">
            <button id="exportGraphMLBtn" class="btn btn-outline-secondary" title="Export GraphML"><i class="fas fa-download me-1"></i>GraphML</button>
        </div>
        <a href="{{ route('research.assertions', $project->id) }}" class="btn btn-sm btn-outline-primary">List View</a>
    </div>
</div>

{{-- Legend --}}
<div class="card mb-3">
    <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
        <small class="text-muted me-1">Node types:</small>
        <span><svg width="12" height="12"><circle cx="6" cy="6" r="5" fill="#4e79a7"/></svg> Actor</span>
        <span><svg width="12" height="12"><circle cx="6" cy="6" r="5" fill="#f28e2c"/></svg> Object</span>
        <span><svg width="12" height="12"><circle cx="6" cy="6" r="5" fill="#59a14f"/></svg> Place</span>
        <span><svg width="12" height="12"><circle cx="6" cy="6" r="5" fill="#e15759"/></svg> Event</span>
        <span><svg width="12" height="12"><circle cx="6" cy="6" r="5" fill="#76b7b2"/></svg> Other</span>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0 position-relative">
                <div id="graphContainer" style="width:100%; height:600px; background:#fafafa;"></div>
                <div class="position-absolute bottom-0 end-0 p-2 d-flex gap-1">
                    <button id="zoomIn" class="btn btn-sm btn-light border" title="Zoom in"><i class="fas fa-plus"></i></button>
                    <button id="zoomOut" class="btn btn-sm btn-light border" title="Zoom out"><i class="fas fa-minus"></i></button>
                    <button id="zoomReset" class="btn btn-sm btn-light border" title="Reset"><i class="fas fa-expand"></i></button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card" id="detailPanel">
            <div class="card-header"><h5 class="mb-0">Node Details</h5></div>
            <div class="card-body" id="detailContent">
                <p class="text-muted mb-0">Click a node to see details.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var projectId = {{ (int) $project->id }};
    var allData = {nodes:[], edges:[]};
    var svg, gAll, simulation, nodeEls, labelEls;
    var typeColors = {actor:'#4e79a7', person:'#4e79a7', information_object:'#f28e2c', place:'#59a14f', event:'#e15759'};

    function loadGraph() {
        var url = '{{ route("research.knowledgeGraph", $project->id) }}';
        var ft = document.getElementById('filterType').value;
        var params = [];
        if (ft) params.push('assertion_type=' + ft);
        if (params.length) url += '?' + params.join('&');

        fetch(url, {headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(data) {
            allData = data;
            renderGraph(data);
        });
    }

    function renderGraph(data) {
        d3.select('#graphContainer').selectAll('*').remove();
        if (!data.nodes || data.nodes.length === 0) {
            d3.select('#graphContainer').append('p').attr('class','text-muted p-4').text('No assertions to display. Create assertions first.');
            return;
        }
        var width = document.getElementById('graphContainer').clientWidth, height = 600;
        svg = d3.select('#graphContainer').append('svg').attr('width', width).attr('height', height);
        gAll = svg.append('g');

        var zoom = d3.zoom().scaleExtent([0.1, 5]).on('zoom', function(event) { gAll.attr('transform', event.transform); });
        svg.call(zoom);
        document.getElementById('zoomIn').onclick = function() { svg.transition().call(zoom.scaleBy, 1.3); };
        document.getElementById('zoomOut').onclick = function() { svg.transition().call(zoom.scaleBy, 0.7); };
        document.getElementById('zoomReset').onclick = function() { svg.transition().call(zoom.transform, d3.zoomIdentity); };

        simulation = d3.forceSimulation(data.nodes)
            .force('link', d3.forceLink(data.edges).id(function(d){return d.id;}).distance(150))
            .force('charge', d3.forceManyBody().strength(-300))
            .force('center', d3.forceCenter(width/2, height/2))
            .force('collision', d3.forceCollide().radius(15));

        var linkEls = gAll.append('g').selectAll('line').data(data.edges).enter().append('line')
            .attr('stroke','#999').attr('stroke-opacity',0.6);
        var edgeLabels = gAll.append('g').selectAll('text').data(data.edges).enter().append('text')
            .text(function(d){return d.label||'';}).attr('font-size','8px').attr('fill','#888').attr('text-anchor','middle');

        nodeEls = gAll.append('g').selectAll('circle').data(data.nodes).enter().append('circle')
            .attr('r', function(d){return Math.max(6, Math.min(14, 6 + (d.connections||0)));})
            .attr('fill', function(d){return typeColors[d.type]||'#76b7b2';})
            .attr('stroke','#fff').attr('stroke-width',1.5).style('cursor','pointer')
            .call(d3.drag().on('start',function(e,d){if(!e.active)simulation.alphaTarget(0.3).restart();d.fx=d.x;d.fy=d.y;})
                .on('drag',function(e,d){d.fx=e.x;d.fy=e.y;}).on('end',function(e,d){if(!e.active)simulation.alphaTarget(0);d.fx=null;d.fy=null;}));

        labelEls = gAll.append('g').selectAll('text').data(data.nodes).enter().append('text')
            .text(function(d){return d.label;}).attr('font-size','10px').attr('dx',12).attr('dy',4);

        nodeEls.on('click', function(event, d) {
            nodeEls.attr('stroke','#fff').attr('stroke-width',1.5);
            d3.select(this).attr('stroke','#333').attr('stroke-width',3);
            showDetail(d);
        });

        simulation.on('tick', function() {
            linkEls.attr('x1',function(d){return d.source.x;}).attr('y1',function(d){return d.source.y;})
                .attr('x2',function(d){return d.target.x;}).attr('y2',function(d){return d.target.y;});
            edgeLabels.attr('x',function(d){return(d.source.x+d.target.x)/2;}).attr('y',function(d){return(d.source.y+d.target.y)/2;});
            nodeEls.attr('cx',function(d){return d.x;}).attr('cy',function(d){return d.y;});
            labelEls.attr('x',function(d){return d.x;}).attr('y',function(d){return d.y;});
        });
    }

    function showDetail(node) {
        var conns = allData.edges.filter(function(e) {
            var s = typeof e.source==='object'?e.source.id:e.source, t = typeof e.target==='object'?e.target.id:e.target;
            return s===node.id||t===node.id;
        });
        var h = '<h6>'+esc(node.label)+'</h6>';
        h += '<p class="mb-1"><span class="badge" style="background:'+(typeColors[node.type]||'#76b7b2')+'">'+esc(node.type||'unknown')+'</span></p>';
        h += '<p class="mb-2 text-muted small">ID: '+node.id+' | Connections: '+conns.length+'</p>';
        if (conns.length) {
            h += '<hr><h6 class="small">Connections</h6><ul class="list-unstyled small">';
            conns.forEach(function(e) {
                var s=typeof e.source==='object'?e.source:allData.nodes.find(function(n){return n.id===e.source;});
                var t=typeof e.target==='object'?e.target:allData.nodes.find(function(n){return n.id===e.target;});
                var other=(s&&s.id===node.id)?t:s;
                if(other) h+='<li><span class="badge bg-light text-dark">'+esc(e.label||'related')+'</span> '+esc(other.label)+'</li>';
            });
            h += '</ul>';
        }
        if(node.entity_url) h+='<a href="'+node.entity_url+'" class="btn btn-sm btn-outline-primary mt-2">View Entity</a>';
        document.getElementById('detailContent').innerHTML = h;
    }

    function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

    // Search
    document.getElementById('nodeSearch').addEventListener('input', function() {
        var term = this.value.toLowerCase();
        if (!nodeEls) return;
        nodeEls.style('opacity', function(d){return !term||d.label.toLowerCase().indexOf(term)>=0?1:0.15;});
        labelEls.style('opacity', function(d){return !term||d.label.toLowerCase().indexOf(term)>=0?1:0.15;});
    });

    // GraphML export
    document.getElementById('exportGraphMLBtn').addEventListener('click', function() {
        window.location.href = '/research/network-graph/' + projectId + '/export/graphml';
    });

    loadGraph();
    document.getElementById('filterType').addEventListener('change', loadGraph);
});
</script>
@endsection
