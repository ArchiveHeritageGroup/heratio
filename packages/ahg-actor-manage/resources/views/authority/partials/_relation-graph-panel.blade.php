@php
/**
 * Embeddable panel: Cytoscape.js agent-to-agent graph for actor view pages.
 * Usage: @include('ahg-actor-manage::authority.partials._relation-graph-panel', ['actorId' => $actorId])
 */
$actorId = $actorId ?? 0;
if (!$actorId) return;
@endphp

<div class="card mb-3 authority-graph-panel">
  <div class="card-header py-2 d-flex justify-content-between" style="background: var(--ahg-primary); color: #fff;">
    <span><i class="fas fa-project-diagram me-1"></i>Relationship Graph</span>
    <div>
      <select id="graph-depth" class="form-select form-select-sm d-inline-block" style="width:auto">
        <option value="1">Depth 1</option>
        <option value="2">Depth 2</option>
        <option value="3">Depth 3</option>
      </select>
      <button class="btn btn-sm atom-btn-white" id="btn-load-graph">
        <i class="fas fa-sync"></i>
      </button>
    </div>
  </div>
  <div class="card-body p-0">
    <div id="authority-graph" style="height:400px; background:#f8f9fa;"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var actorId = {{ (int) $actorId }};

  function loadGraph() {
    var depth = document.getElementById('graph-depth').value;
    fetch('{{ route("actor.api.graph.data", ["actorId" => "__ACTOR_ID__"]) }}'.replace('__ACTOR_ID__', actorId) + '?depth=' + depth)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (typeof cytoscape === 'undefined') {
          document.getElementById('authority-graph').innerHTML =
            '<div class="p-3 text-muted text-center">Cytoscape.js not loaded. Include it via CDN to enable graph visualization.</div>';
          return;
        }

        var elements = [];
        (data.nodes || []).forEach(function(n) { elements.push(n); });
        (data.edges || []).forEach(function(e) { elements.push(e); });

        cytoscape({
          container: document.getElementById('authority-graph'),
          elements: elements,
          style: [
            { selector: 'node', style: { 'label': 'data(label)', 'background-color': '#0d6efd', 'color': '#333', 'font-size': '11px', 'text-wrap': 'wrap', 'text-max-width': '100px' } },
            { selector: 'edge', style: { 'label': 'data(label)', 'curve-style': 'bezier', 'target-arrow-shape': 'triangle', 'font-size': '9px', 'line-color': '#adb5bd', 'target-arrow-color': '#adb5bd' } }
          ],
          layout: { name: 'cose', animate: true }
        });
      });
  }

  document.getElementById('btn-load-graph').addEventListener('click', loadGraph);
  loadGraph();
});
</script>
