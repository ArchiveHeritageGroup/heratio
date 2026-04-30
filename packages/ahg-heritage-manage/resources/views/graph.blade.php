@extends('theme::layouts.1col')
@section('title', 'Entity Relationship Graph')
@section('body-class', 'heritage')

@section('content')
<div class="heritage-graph-page py-4">
  <div class="container-xxl">
    <div class="row mb-4"><div class="col">
      <nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('heritage.landing') }}">Heritage</a></li><li class="breadcrumb-item active">Knowledge Graph</li></ol></nav>
      <div class="d-flex justify-content-between align-items-center">
        <h1 class="h2 mb-0">{{ __('Entity Relationship Graph') }}</h1>
        <div class="btn-group"><a href="{{ route('heritage.search') }}" class="btn atom-btn-white"><i class="fas fa-search me-1"></i>{{ __('Search') }}</a><a href="{{ route('heritage.explore') }}" class="btn atom-btn-white"><i class="fas fa-compass me-1"></i>{{ __('Explore') }}</a></div>
      </div>
    </div></div>

    <div class="row mb-3">
      <div class="col-md-8">
        <div class="card shadow-sm"><div class="card-body py-2"><div class="row g-2 align-items-center">
          <div class="col-auto"><label class="form-label mb-0 small text-muted">{{ __('Filter by type:') }}</label></div>
          <div class="col-auto"><select id="entity-type-filter" class="form-select form-select-sm"><option value="">{{ __('All Types') }}</option>@foreach($entityTypes ?? [] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></div>
          <div class="col-auto"><input type="text" id="graph-search" class="form-control form-control-sm" placeholder="{{ __('Search entities...') }}" style="width:180px"></div>
          <div class="col-auto"><label class="form-label mb-0 small text-muted">{{ __('Min occurrences:') }}</label></div>
          <div class="col-auto"><input type="number" id="min-occurrences" class="form-control form-control-sm" value="1" min="1" max="100" style="width:70px"></div>
          <div class="col-auto"><button id="refresh-graph" class="btn btn-sm atom-btn-secondary"><i class="fas fa-sync-alt"></i> {{ __('Refresh') }}</button></div>
        </div></div></div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm"><div class="card-body py-2"><div class="d-flex flex-wrap gap-3 small">
          @foreach(['Person'=>'#4e79a7','Organization'=>'#59a14f','Place'=>'#e15759','Date'=>'#b07aa1','Event'=>'#76b7b2','Work'=>'#ff9da7'] as $label=>$color)
          <span><span class="badge rounded-pill" style="background-color:{{ $color }}">{{ $label }}</span></span>
          @endforeach
        </div></div></div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-8">
        <div class="card shadow-sm"><div class="card-body p-0 position-relative"><div id="graph-container" style="height:600px;width:100%"></div><div id="graph-loading" class="position-absolute top-50 start-50 translate-middle" style="display:none"><div class="spinner-border" style="color:var(--ahg-primary)"></div></div></div></div>
        <div class="card shadow-sm mt-3"><div class="card-body py-2"><div class="row text-center small">
          <div class="col"><span class="text-muted">{{ __('Nodes:') }}</span> <strong id="stat-nodes">{{ number_format($stats['total_nodes'] ?? 0) }}</strong></div>
          <div class="col"><span class="text-muted">{{ __('Edges:') }}</span> <strong id="stat-edges">{{ number_format($stats['total_edges'] ?? 0) }}</strong></div>
          <div class="col"><span class="text-muted">{{ __('Avg connections:') }}</span> <strong id="stat-avg">{{ number_format($stats['avg_connections_per_node'] ?? 0, 1) }}</strong></div>
        </div></div></div>
      </div>
      <div class="col-md-4">
        <div id="entity-panel" class="card shadow-sm" style="display:none">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0" id="entity-panel-title">{{ __('Entity Details') }}</h5><button type="button" class="btn-close btn-close-white" id="close-entity-panel"></button></div>
          <div class="card-body"><div id="entity-panel-content"></div></div>
        </div>
        <div id="entity-instructions" class="card shadow-sm"><div class="card-body text-center py-5"><i class="fas fa-project-diagram fs-1 text-muted mb-3 d-block"></i><p class="text-muted mb-0">Click on a node to see entity details and related records.</p></div></div>
      </div>
    </div>
  </div>
</div>

<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
(function(){
  'use strict';
  var colorScale = {person:'#4e79a7',organization:'#59a14f',place:'#e15759',date:'#b07aa1',event:'#76b7b2',work:'#ff9da7',concept:'#edc949'};
  var container = document.getElementById('graph-container');
  if(!container) return;
  var width = container.clientWidth, height = container.clientHeight;
  var svg = d3.select('#graph-container').append('svg').attr('width','100%').attr('height','100%').attr('viewBox',[0,0,width,height]);
  var g = svg.append('g');
  svg.call(d3.zoom().extent([[0,0],[width,height]]).scaleExtent([0.1,4]).on('zoom',function(e){g.attr('transform',e.transform)}));
  var simulation = null;

  function loadGraph(){
    var type=document.getElementById('entity-type-filter').value;
    var search=document.getElementById('graph-search').value;
    var min=document.getElementById('min-occurrences').value;
    document.getElementById('graph-loading').style.display='block';
    var url='/heritage/graph-data?limit=100';
    if(type)url+='&entity_type='+encodeURIComponent(type);
    if(search)url+='&search='+encodeURIComponent(search);
    if(min>1)url+='&min_occurrences='+encodeURIComponent(min);
    fetch(url,{headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(data){
      document.getElementById('graph-loading').style.display='none';
      if(data.success!==false)renderGraph(data);
    }).catch(function(){document.getElementById('graph-loading').style.display='none'});
  }

  function renderGraph(data){
    g.selectAll('*').remove();
    if(!data.nodes||!data.nodes.length){g.append('text').attr('x',width/2).attr('y',height/2).attr('text-anchor','middle').attr('fill','#666').text('No entities found.');return;}
    simulation=d3.forceSimulation(data.nodes).force('link',d3.forceLink(data.links).id(function(d){return d.id}).distance(100)).force('charge',d3.forceManyBody().strength(-300)).force('center',d3.forceCenter(width/2,height/2)).force('collision',d3.forceCollide().radius(function(d){return(d.size||10)+5}));
    var link=g.append('g').selectAll('line').data(data.links).enter().append('line').attr('stroke','#999').attr('stroke-opacity',0.6).attr('stroke-width',function(d){return Math.min(5,d.weight||1)});
    var node=g.append('g').selectAll('g').data(data.nodes).enter().append('g').style('cursor','pointer').call(d3.drag().on('start',function(e,d){if(!e.active)simulation.alphaTarget(0.3).restart();d.fx=d.x;d.fy=d.y}).on('drag',function(e,d){d.fx=e.x;d.fy=e.y}).on('end',function(e,d){if(!e.active)simulation.alphaTarget(0);d.fx=null;d.fy=null}));
    node.append('circle').attr('r',function(d){return d.size||10}).attr('fill',function(d){return colorScale[d.type]||'#999'}).attr('stroke','#fff').attr('stroke-width',2);
    node.append('text').text(function(d){return d.label.length>20?d.label.substring(0,20)+'...':d.label}).attr('x',function(d){return(d.size||10)+5}).attr('y',4).attr('font-size','11px').attr('fill','#333');
    node.on('click',function(e,d){e.stopPropagation();showEntityPanel(d)});
    simulation.on('tick',function(){link.attr('x1',function(d){return d.source.x}).attr('y1',function(d){return d.source.y}).attr('x2',function(d){return d.target.x}).attr('y2',function(d){return d.target.y});node.attr('transform',function(d){return'translate('+d.x+','+d.y+')'})});
  }

  function showEntityPanel(entity){
    document.getElementById('entity-instructions').style.display='none';
    document.getElementById('entity-panel').style.display='block';
    document.getElementById('entity-panel-title').textContent=entity.label;
    document.getElementById('entity-panel-content').innerHTML='<div class="mb-3"><span class="badge" style="background-color:'+(colorScale[entity.type]||'#999')+'">'+entity.type+'</span> <span class="badge bg-light text-dark ms-1">'+entity.occurrences+' occurrences</span></div><div class="d-grid gap-2"><a href="/heritage/search?ner_'+entity.type+'[]='+encodeURIComponent(entity.label)+'" class="btn btn-sm atom-btn-secondary"><i class="fas fa-search me-1"></i>View Records</a><a href="/heritage/entity/'+entity.type+'/'+encodeURIComponent(entity.label)+'" class="btn btn-sm atom-btn-white"><i class="fas fa-info-circle me-1"></i>Entity Details</a></div>';
  }

  document.getElementById('refresh-graph').addEventListener('click',loadGraph);
  document.getElementById('entity-type-filter').addEventListener('change',loadGraph);
  document.getElementById('close-entity-panel').addEventListener('click',function(){document.getElementById('entity-panel').style.display='none';document.getElementById('entity-instructions').style.display='block'});
  var st;document.getElementById('graph-search').addEventListener('input',function(){clearTimeout(st);st=setTimeout(loadGraph,500)});
  document.getElementById('min-occurrences').addEventListener('change',loadGraph);
  loadGraph();
})();
</script>
@endsection
