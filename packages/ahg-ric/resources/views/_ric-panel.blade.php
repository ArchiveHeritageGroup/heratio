{{-- Partial: RiC Explorer panel for IO show page --}}
@if(!empty($resourceId))
<section id="ric-explorer-panel" class="card mb-3">
  <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-2">
    <h6 class="mb-0">
      <i class="fas fa-project-diagram me-2"></i>RiC Explorer
    </h6>
    <div class="btn-group btn-group-sm">
      <button type="button" class="btn btn-light btn-sm" id="ric-load-btn">
        <i class="fas fa-sync-alt me-1"></i>Load
      </button>
      <button type="button" class="btn btn-light btn-sm ric-view-btn active" data-view="2d">2D</button>
      <button type="button" class="btn btn-outline-light btn-sm ric-view-btn" data-view="3d">3D</button>
      <button type="button" class="btn btn-outline-light btn-sm" id="ric-fullscreen-btn" title="Fullscreen">
        <i class="fas fa-expand"></i>
      </button>
    </div>
  </div>

  <div class="card-body p-0">
    <div id="ric-mini-graph-container" style="height: 350px; position: relative; overflow: hidden; background: #1a1a2e;">
      <div id="ric-placeholder" style="display:flex; align-items:center; justify-content:center; height:100%; color:#fff;">
        <div class="text-center">
          <i class="fas fa-project-diagram fa-3x mb-2"></i>
          <p>Click "Load" to view RiC relationships</p>
        </div>
      </div>
      <div id="ric-loading" style="display:none; align-items:center; justify-content:center; height:100%;">
        <div class="spinner-border text-success"></div>
      </div>
      <div id="ric-graph-2d" style="position:absolute; top:0; left:0; width:100%; height:100%; display:none;"></div>
      <div id="ric-graph-3d" style="position:absolute; top:0; left:0; width:100%; height:100%; display:none;"></div>
    </div>
  </div>
</section>

<div id="ric-fullscreen-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:#1a1a2e; z-index:9999;">
  <div style="position:absolute; top:15px; right:15px; z-index:10001;">
    <div class="btn-group btn-group-sm">
      <button type="button" class="btn btn-light ric-fs-view-btn active" data-view="2d">2D</button>
      <button type="button" class="btn btn-light ric-fs-view-btn" data-view="3d">3D</button>
    </div>
    <button type="button" class="btn btn-danger btn-sm ms-2" id="ric-close-fullscreen">
      <i class="fas fa-times"></i> Close
    </button>
  </div>
  <div id="ric-fullscreen-graph" style="width:100%; height:100%;"></div>
</div>

{{-- RiC Explorer dependencies from CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.28.1/cytoscape.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://unpkg.com/three-spritetext@1.8.2/dist/three-spritetext.min.js"></script>
<script src="https://unpkg.com/3d-force-graph@1.73.3/dist/3d-force-graph.min.js"></script>

<script>
(function() {
  var resourceId = {{ (int)$resourceId }};
  var graphData = null;
  var cy2d = null;
  var graph3d = null;
  var fsGraph = null;
  var currentView = '2d';

  var typeColors = {
    'RecordSet': '#17a2b8', 'Record': '#17a2b8',
    'CorporateBody': '#ffc107', 'Person': '#dc3545', 'Family': '#dc3545',
    'Production': '#6f42c1', 'Accumulation': '#6f42c1', 'Activity': '#6f42c1',
    'Place': '#fd7e14', 'Thing': '#20c997',
    'Concept': '#20c997', 'DocumentaryFormType': '#20c997', 'CarrierType': '#20c997',
    'ContentType': '#20c997', 'RecordState': '#adb5bd', 'Language': '#0d6efd'
  };

  function getColor(type) { return typeColors[type] || '#6c757d'; }

  function loadRicData() {
    document.getElementById('ric-placeholder').style.display = 'none';
    document.getElementById('ric-loading').style.display = 'flex';
    document.getElementById('ric-load-btn').disabled = true;

    fetch('{{ route("ric.data") }}?id=' + resourceId + '&_=' + Date.now())
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.getElementById('ric-loading').style.display = 'none';
        if (data.success && data.graphData && data.graphData.nodes && data.graphData.nodes.length > 0) {
          graphData = data.graphData;
          document.getElementById('ric-load-btn').innerHTML = '<i class="fas fa-check me-1"></i>' + graphData.nodes.length + ' nodes';
          document.getElementById('ric-load-btn').classList.replace('btn-light', 'btn-success');
          showMiniGraph();
        } else {
          document.getElementById('ric-placeholder').innerHTML = '<div class="text-center text-warning"><i class="fas fa-info-circle fa-2x mb-2"></i><p>No data found</p></div>';
          document.getElementById('ric-placeholder').style.display = 'flex';
          document.getElementById('ric-load-btn').disabled = false;
        }
      })
      .catch(function(err) {
        document.getElementById('ric-loading').style.display = 'none';
        document.getElementById('ric-placeholder').innerHTML = '<div class="text-center text-danger"><p>Error: ' + err.message + '</p></div>';
        document.getElementById('ric-placeholder').style.display = 'flex';
        document.getElementById('ric-load-btn').disabled = false;
      });
  }

  function showMiniGraph() {
    var container2d = document.getElementById('ric-graph-2d');
    var container3d = document.getElementById('ric-graph-3d');

    if (currentView === '2d') {
      container3d.style.display = 'none';
      container2d.style.display = 'block';
      setTimeout(function() {
        if (!cy2d) cy2d = init2DGraph(container2d);
        else cy2d.resize();
      }, 100);
    } else {
      container2d.style.display = 'none';
      container3d.style.display = 'block';
      setTimeout(function() {
        if (graph3d) {
          graph3d._destructor && graph3d._destructor();
          graph3d = null;
        }
        container3d.innerHTML = '';
        graph3d = init3DGraph(container3d);
      }, 100);
    }
  }

  function init2DGraph(container) {
    if (!graphData || !graphData.nodes) return null;

    var elements = [];
    graphData.nodes.forEach(function(node) {
      elements.push({
        data: {
          id: node.id,
          label: node.label || 'Unknown',
          color: getColor(node.type)
        }
      });
    });
    (graphData.edges || []).forEach(function(edge, idx) {
      elements.push({
        data: {
          id: 'e' + idx,
          source: edge.source,
          target: edge.target
        }
      });
    });

    try {
      return cytoscape({
        container: container,
        elements: elements,
        style: [
          { selector: 'node', style: {
            'background-color': 'data(color)',
            'label': 'data(label)',
            'font-size': '6px',
            'color': '#fff',
            'text-valign': 'bottom',
            'text-margin-y': '2px',
            'width': '10px',
            'height': '10px'
          }},
          { selector: 'edge', style: {
            'width': 1,
            'line-color': '#555',
            'target-arrow-color': '#555',
            'target-arrow-shape': 'triangle',
            'curve-style': 'bezier'
          }}
        ],
        layout: { name: 'cose', animate: false, padding: 10, nodeRepulsion: function(){ return 8000; } }
      });
    } catch(e) {
      console.error('2D graph error:', e);
      return null;
    }
  }

  function init3DGraph(container) {
    if (!graphData || !graphData.nodes) return null;

    var nodes = graphData.nodes.map(function(n) {
      return { id: n.id, name: n.label || 'Unknown', color: getColor(n.type), val: 1 };
    });
    var links = (graphData.edges || []).map(function(e) {
      return { source: e.source, target: e.target };
    });

    try {
      var w = container.clientWidth || 400;
      var h = container.clientHeight || 350;

      return ForceGraph3D()(container)
        .graphData({ nodes: nodes, links: links })
        .nodeColor('color')
        .nodeVal('val')
        .nodeLabel('name')
        .nodeThreeObject(function(node) {
          var sprite = new SpriteText(node.name.length > 15 ? node.name.substring(0,15) + '...' : node.name);
          sprite.color = '#ffffff';
          sprite.textHeight = 3;
          sprite.backgroundColor = node.color;
          sprite.padding = 0.5;
          sprite.borderRadius = 1;
          return sprite;
        })
        .nodeThreeObjectExtend(false)
        .linkDirectionalParticles(1)
        .backgroundColor('#1a1a2e')
        .width(w)
        .height(h);
    } catch(e) {
      console.error('3D graph error:', e);
      return null;
    }
  }

  function switchView(view) {
    currentView = view;
    document.querySelectorAll('.ric-view-btn').forEach(function(btn) {
      btn.classList.toggle('active', btn.dataset.view === view);
      btn.classList.toggle('btn-light', btn.dataset.view === view);
      btn.classList.toggle('btn-outline-light', btn.dataset.view !== view);
    });
    if (graphData) showMiniGraph();
  }

  function openFullscreen() {
    if (!graphData) return;
    var modal = document.getElementById('ric-fullscreen-modal');
    var container = document.getElementById('ric-fullscreen-graph');
    modal.style.display = 'block';
    container.innerHTML = '';

    setTimeout(function() {
      if (currentView === '2d') {
        fsGraph = init2DGraph(container);
      } else {
        fsGraph = init3DGraph(container);
      }
      document.querySelectorAll('.ric-fs-view-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.view === currentView);
      });
    }, 200);
  }

  function closeFullscreen() {
    document.getElementById('ric-fullscreen-modal').style.display = 'none';
    var container = document.getElementById('ric-fullscreen-graph');
    if (fsGraph) {
      if (fsGraph.destroy) fsGraph.destroy();
      if (fsGraph._destructor) fsGraph._destructor();
      fsGraph = null;
    }
    container.innerHTML = '';
  }

  function switchFullscreenView(view) {
    currentView = view;
    var container = document.getElementById('ric-fullscreen-graph');
    if (fsGraph) {
      if (fsGraph.destroy) fsGraph.destroy();
      if (fsGraph._destructor) fsGraph._destructor();
      fsGraph = null;
    }
    container.innerHTML = '';

    document.querySelectorAll('.ric-fs-view-btn').forEach(function(btn) {
      btn.classList.toggle('active', btn.dataset.view === view);
    });

    setTimeout(function() {
      if (view === '2d') {
        fsGraph = init2DGraph(container);
      } else {
        fsGraph = init3DGraph(container);
      }
    }, 100);
  }

  // Event listeners
  document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('ric-load-btn').addEventListener('click', loadRicData);

    document.querySelectorAll('.ric-view-btn').forEach(function(btn) {
      btn.addEventListener('click', function() { switchView(this.dataset.view); });
    });

    document.getElementById('ric-fullscreen-btn').addEventListener('click', openFullscreen);
    document.getElementById('ric-close-fullscreen').addEventListener('click', closeFullscreen);

    document.querySelectorAll('.ric-fs-view-btn').forEach(function(btn) {
      btn.addEventListener('click', function() { switchFullscreenView(this.dataset.view); });
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeFullscreen();
    });
  });
})();
</script>
@endif
