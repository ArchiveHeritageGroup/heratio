@extends('theme::layouts.1col')

@section('title', 'RiC Explorer')
@section('body-class', 'admin ric')

@push('css')
<link rel="stylesheet" href="/vendor/ahg-ric/css/ric-explorer.css">
@endpush

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-project-diagram me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('RiC Explorer') }}</h1>
      <span class="small text-muted">{{ __('Records in Contexts &mdash; Graph Visualization') }}</span>
    </div>
  </div>

  {{-- Search / Autocomplete Bar --}}
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row align-items-center">
        <div class="col-md-8">
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="ric-autocomplete-input" class="form-control"
                   placeholder="{{ __('Search records by title, identifier, or slug...') }}" autocomplete="off" />
          </div>
          <div id="ric-autocomplete-dropdown" class="list-group position-absolute shadow-sm" style="display:none; z-index:1050; max-height:300px; overflow-y:auto; width:calc(100% - 2rem);"></div>
        </div>
        <div class="col-md-4 text-end mt-2 mt-md-0">
          <div class="btn-group btn-group-sm">
            <a href="#" class="btn atom-btn-secondary" id="ric-back-to-record-btn" style="display:none">
              <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Record') }}
            </a>
            <button type="button" class="btn atom-btn-secondary" id="ric-load-overview-btn">
              <i class="fas fa-globe me-1"></i> {{ __('Overview') }}
            </button>
            <button type="button" class="btn atom-btn-secondary ric-view-btn active" data-view="2d">2D</button>
            <button type="button" class="btn atom-btn-secondary ric-view-btn" data-view="3d">3D</button>
            <button type="button" class="btn atom-btn-secondary ric-view-btn" data-view="timeline"><i class="fas fa-stream me-1"></i>{{ __('Timeline') }}</button>
            <button type="button" class="btn atom-btn-secondary" id="ric-fullscreen-btn" title="{{ __('Fullscreen') }}">
              <i class="fas fa-expand"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Graph Container --}}
  <div class="card mb-3 ric-explorer-page">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <span><i class="fas fa-project-diagram me-1"></i> {{ __('Graph View') }}</span>
      <span id="ric-node-count" class="badge bg-light text-dark">0 nodes</span>
    </div>
    <div class="card-body p-0">
      <div class="ric-graph-main" style="position:relative; height:500px; background:#1a1a2e;">
        <div id="ric-explorer-placeholder" style="display:flex; align-items:center; justify-content:center; height:100%; color:#fff;">
          <div class="text-center">
            <i class="fas fa-project-diagram fa-3x mb-2"></i>
            <p>Search for a record above, or click "Overview" to visualize the graph</p>
          </div>
        </div>
        <div id="ric-explorer-loading" style="display:none; align-items:center; justify-content:center; height:100%;">
          <div class="spinner-border text-success"></div>
        </div>
        <div id="ric-explorer-graph-2d" style="position:absolute; top:0; left:0; width:100%; height:100%; display:none;"></div>
        <div id="ric-explorer-graph-3d" style="position:absolute; top:0; left:0; width:100%; height:100%; display:none;"></div>

        {{-- Legend --}}
        <div class="ric-legend" style="display:none;" id="ric-explorer-legend">
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#4ecdc4;"></span> RecordSet</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#45b7d1;"></span> Record</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#dc3545;"></span> Person</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#ffc107;"></span> CorporateBody</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#6f42c1;"></span> Activity/Event</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#fd7e14;"></span> Place</div>
          <div class="ric-legend-item"><span class="ric-legend-color" style="background:#20c997;"></span> Concept/Term</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Node Info Panel --}}
  <div id="ric-node-info" class="card mb-3" style="display:none;">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-info-circle me-1"></i> <span id="ric-node-info-title">{{ __('Node Details') }}</span>
    </div>
    <div class="card-body ric-node-info" id="ric-node-info-body"></div>
  </div>

  {{-- Back to Dashboard --}}
  <div class="mb-3">
    <a href="{{ route('ric.index') }}" class="btn atom-btn-secondary">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to RiC Dashboard') }}
    </a>
    <a href="{{ route('ric.semantic-search') }}" class="btn atom-btn-secondary">
      <i class="fas fa-brain me-1"></i> {{ __('Semantic Search') }}
    </a>
  </div>

  {{-- New Entity Modal --}}
  <div class="modal fade" id="ricCreateEntityModal" tabindex="-1" aria-labelledby="ricCreateEntityLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content" style="background:#2a2a3e; color:#e0e0e0;">
        <div class="modal-header" style="border-color:#444;">
          <h5 class="modal-title" id="ricCreateEntityLabel"><i class="fas fa-plus me-2"></i>{{ __('New RiC Entity') }}</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small">{{ __('Entity Type') }}</label>
            <select id="ric-create-type" class="form-select form-select-sm" style="background:#1a1a2e; color:#e0e0e0; border-color:#444;">
              <option value="RecordSet">{{ __('RecordSet (Fonds/Collection)') }}</option>
              <option value="Record" selected>{{ __('Record (Item/File)') }}</option>
              <option value="RecordPart">{{ __('RecordPart') }}</option>
              <option value="Person">{{ __('Person') }}</option>
              <option value="CorporateBody">{{ __('CorporateBody') }}</option>
              <option value="Family">{{ __('Family') }}</option>
              <option value="Place">{{ __('Place') }}</option>
              <option value="Activity">{{ __('Activity') }}</option>
              <option value="Event">{{ __('Event') }}</option>
              <option value="Concept">{{ __('Concept/Term') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small">Name / Title <span class="text-danger">*</span></label>
            <input type="text" id="ric-create-name" class="form-control form-control-sm" style="background:#1a1a2e; color:#e0e0e0; border-color:#444;" placeholder="{{ __('Enter entity name') }}">
          </div>
          <div class="mb-3">
            <label class="form-label small">{{ __('Identifier') }}</label>
            <input type="text" id="ric-create-identifier" class="form-control form-control-sm" style="background:#1a1a2e; color:#e0e0e0; border-color:#444;" placeholder="{{ __('Optional identifier') }}">
          </div>
          <div class="mb-3">
            <label class="form-label small">{{ __('Description') }}</label>
            <textarea id="ric-create-description" class="form-control form-control-sm" rows="3" style="background:#1a1a2e; color:#e0e0e0; border-color:#444;" placeholder="{{ __('Optional description') }}"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label small">Parent URI <span class="text-muted">(link to existing entity)</span></label>
            <input type="text" id="ric-create-parent" class="form-control form-control-sm" style="background:#1a1a2e; color:#e0e0e0; border-color:#444;" placeholder="{{ __('Optional parent URI') }}">
          </div>
          <div id="ric-create-result" style="display:none;"></div>
        </div>
        <div class="modal-footer" style="border-color:#444;">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="button" class="btn btn-sm btn-success" id="ric-create-submit" onclick="submitCreateEntity()">
            <i class="fas fa-plus me-1"></i>{{ __('Create Entity') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Fullscreen Modal --}}
  <div id="ric-fullscreen-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:#1a1a2e; z-index:9999;">
    <div style="position:absolute; top:15px; right:15px; z-index:10001;">
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-light ric-fs-view-btn active" data-view="2d">2D</button>
        <button type="button" class="btn btn-light ric-fs-view-btn" data-view="3d">3D</button>
      </div>
      <button type="button" class="btn btn-danger btn-sm ms-2" id="ric-close-fullscreen">
        <i class="fas fa-times"></i> {{ __('Close') }}
      </button>
    </div>
    <div id="ric-fullscreen-graph" style="width:100%; height:100%;"></div>
  </div>
@endsection

@push('js')
<script src="/vendor/ahg-ric/js/cytoscape.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://unpkg.com/three-spritetext@1.8.2/dist/three-spritetext.min.js"></script>
<script src="https://unpkg.com/3d-force-graph@1.73.3/dist/3d-force-graph.min.js"></script>
<script src="/vendor/ahg-ric/js/ric-explorer.js"></script>
<script>
(function() {
  'use strict';

  var graphData = null;
  var cy2d = null;
  var graph3d = null;
  var fsGraph = null;
  var currentView = '2d';
  var autocompleteTimeout = null;

  var autocompleteInput = document.getElementById('ric-autocomplete-input');
  var autocompleteDropdown = document.getElementById('ric-autocomplete-dropdown');

  // Autocomplete
  autocompleteInput.addEventListener('input', function() {
    var q = this.value.trim();
    clearTimeout(autocompleteTimeout);
    if (q.length < 2) {
      autocompleteDropdown.style.display = 'none';
      return;
    }
    autocompleteTimeout = setTimeout(function() {
      fetch('{{ route("ric.autocomplete") }}?q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(items) {
          if (!items || items.length === 0) {
            autocompleteDropdown.style.display = 'none';
            return;
          }
          var html = '';
          items.forEach(function(item) {
            html += '<a href="#" class="list-group-item list-group-item-action ric-ac-item" data-id="' + item.id + '">';
            html += '<div class="d-flex justify-content-between">';
            html += '<span>' + escapeHtml(item.title) + '</span>';
            if (item.lod) html += '<span class="badge bg-info">' + escapeHtml(item.lod) + '</span>';
            html += '</div>';
            if (item.identifier) html += '<small class="text-muted">' + escapeHtml(item.identifier) + '</small>';
            html += '</a>';
          });
          autocompleteDropdown.innerHTML = html;
          autocompleteDropdown.style.display = 'block';

          autocompleteDropdown.querySelectorAll('.ric-ac-item').forEach(function(el) {
            el.addEventListener('click', function(e) {
              e.preventDefault();
              autocompleteDropdown.style.display = 'none';
              autocompleteInput.value = el.querySelector('span').textContent;
              loadGraphData(this.dataset.id);
            });
          });
        })
        .catch(function() {
          autocompleteDropdown.style.display = 'none';
        });
    }, 250);
  });

  // Close dropdown on outside click
  document.addEventListener('click', function(e) {
    if (!autocompleteDropdown.contains(e.target) && e.target !== autocompleteInput) {
      autocompleteDropdown.style.display = 'none';
    }
  });

  // Load graph data for a record
  function loadGraphData(recordId) {
    document.getElementById('ric-explorer-placeholder').style.display = 'none';
    document.getElementById('ric-explorer-loading').style.display = 'flex';
    hideGraphContainers();

    fetch('{{ route("ric.data") }}?id=' + encodeURIComponent(recordId) + '&_=' + Date.now())
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.getElementById('ric-explorer-loading').style.display = 'none';
        if (data.success && data.graphData && data.graphData.nodes && data.graphData.nodes.length > 0) {
          graphData = data.graphData;
          document.getElementById('ric-node-count').textContent = graphData.nodes.length + ' nodes';
          document.getElementById('ric-explorer-legend').style.display = 'block';
          showGraph();
        } else {
          document.getElementById('ric-explorer-placeholder').innerHTML = '<div class="text-center text-warning"><i class="fas fa-info-circle fa-2x mb-2"></i><p>No graph data found for this record</p></div>';
          document.getElementById('ric-explorer-placeholder').style.display = 'flex';
        }
      })
      .catch(function(err) {
        document.getElementById('ric-explorer-loading').style.display = 'none';
        document.getElementById('ric-explorer-placeholder').innerHTML = '<div class="text-center text-danger"><p>Error: ' + escapeHtml(err.message) + '</p></div>';
        document.getElementById('ric-explorer-placeholder').style.display = 'flex';
      });
  }

  function hideGraphContainers() {
    document.getElementById('ric-explorer-graph-2d').style.display = 'none';
    document.getElementById('ric-explorer-graph-3d').style.display = 'none';
  }

  function showGraph() {
    var container2d = document.getElementById('ric-explorer-graph-2d');
    var container3d = document.getElementById('ric-explorer-graph-3d');

    if (currentView === '2d') {
      container3d.style.display = 'none';
      container2d.style.display = 'block';
      setTimeout(function() {
        if (cy2d) { cy2d.destroy(); cy2d = null; }
        container2d.innerHTML = '';
        cy2d = window.RicExplorer.init2DGraph(container2d, graphData, { nodeSize: 25, fontSize: '9px' });
      }, 100);
    } else {
      container2d.style.display = 'none';
      container3d.style.display = 'block';
      setTimeout(function() {
        if (graph3d) {
          if (graph3d._destructor) graph3d._destructor();
          graph3d = null;
        }
        container3d.innerHTML = '';
        graph3d = window.RicExplorer.init3DGraph(container3d, graphData);
      }, 100);
    }
  }

  // View switching
  function switchView(view) {
    currentView = view;
    document.querySelectorAll('.ric-view-btn').forEach(function(btn) {
      btn.classList.toggle('active', btn.dataset.view === view);
    });
    if (graphData) showGraph();
  }

  // Fullscreen
  function openFullscreen() {
    if (!graphData) return;
    var modal = document.getElementById('ric-fullscreen-modal');
    var container = document.getElementById('ric-fullscreen-graph');
    modal.style.display = 'block';
    container.innerHTML = '';

    setTimeout(function() {
      if (currentView === '2d') {
        fsGraph = window.RicExplorer.init2DGraph(container, graphData, { nodeSize: 30, fontSize: '10px', nodeRepulsion: 8000, padding: 30 });
      } else {
        fsGraph = window.RicExplorer.init3DGraph(container, graphData);
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
        fsGraph = window.RicExplorer.init2DGraph(container, graphData, { nodeSize: 30, fontSize: '10px' });
      } else {
        fsGraph = window.RicExplorer.init3DGraph(container, graphData);
      }
    }, 100);
  }

  function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Event listeners
  document.querySelectorAll('.ric-view-btn').forEach(function(btn) {
    btn.addEventListener('click', function() { switchView(this.dataset.view); });
  });

  document.getElementById('ric-fullscreen-btn').addEventListener('click', openFullscreen);
  document.getElementById('ric-close-fullscreen').addEventListener('click', closeFullscreen);

  document.querySelectorAll('.ric-fs-view-btn').forEach(function(btn) {
    btn.addEventListener('click', function() { switchFullscreenView(this.dataset.view); });
  });

  document.getElementById('ric-load-overview-btn').addEventListener('click', function() {
    loadGraphData('overview');
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFullscreen();
  });

  // Enter key on autocomplete input
  autocompleteInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      autocompleteDropdown.style.display = 'none';
      var firstItem = autocompleteDropdown.querySelector('.ric-ac-item');
      if (firstItem) {
        loadGraphData(firstItem.dataset.id);
      }
    }
  });

  // Auto-load entity from URL parameter ?id=X
  var urlParams = new URLSearchParams(window.location.search);
  var initialId = urlParams.get('id');
  if (initialId) {
    loadGraphData(initialId);
    var backBtn = document.getElementById('ric-back-to-record-btn');
    @if(request()->has('id'))
      @php $explorerSlug = \Illuminate\Support\Facades\DB::table('slug')->where('object_id', request('id'))->value('slug'); @endphp
      @if($explorerSlug)
        backBtn.href = '/{{ $explorerSlug }}';
        backBtn.style.display = '';
      @endif
    @endif
  }
})();

// ── New Entity ──
window.openCreateForm = function() {
  var modal = new bootstrap.Modal(document.getElementById('ricCreateEntityModal'));
  document.getElementById('ric-create-result').style.display = 'none';
  document.getElementById('ric-create-name').value = '';
  document.getElementById('ric-create-identifier').value = '';
  document.getElementById('ric-create-description').value = '';
  document.getElementById('ric-create-parent').value = '';
  modal.show();
};

window.submitCreateEntity = function() {
  var name = document.getElementById('ric-create-name').value.trim();
  if (!name) { alert('Name is required'); return; }

  var btn = document.getElementById('ric-create-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating...';

  fetch('{{ route("ric.create-entity") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
    },
    body: JSON.stringify({
      type: document.getElementById('ric-create-type').value,
      name: name,
      identifier: document.getElementById('ric-create-identifier').value.trim(),
      description: document.getElementById('ric-create-description').value.trim(),
      parent_uri: document.getElementById('ric-create-parent').value.trim(),
    }),
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-plus me-1"></i>Create Entity';
    var resultDiv = document.getElementById('ric-create-result');
    resultDiv.style.display = '';

    if (data.success) {
      resultDiv.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fas fa-check me-1"></i>Created: <strong>' + data.name + '</strong> (' + data.type + ')<br><code style="font-size:0.75rem">' + data.uri + '</code></div>';
      // Load the new entity in the graph
      setTimeout(function() {
        bootstrap.Modal.getInstance(document.getElementById('ricCreateEntityModal')).hide();
        if (typeof loadEntityById === 'function') loadEntityById(data.uri);
      }, 1500);
    } else {
      resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-times me-1"></i>' + (data.error || 'Failed') + '</div>';
    }
  })
  .catch(function(err) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-plus me-1"></i>Create Entity';
    alert('Error: ' + err.message);
  });
};
</script>
@endpush
