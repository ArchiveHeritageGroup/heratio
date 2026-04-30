{{-- RiC Context Sidebar — shows related entities when in RiC view mode --}}
@php
  $viewMode = session('ric_view_mode', config('ric.default_view', 'heratio'));
@endphp

@if($viewMode === 'ric' && !empty($resourceId))
<section id="ric-context-sidebar" class="card mb-3">
  <div class="card-header bg-success text-white py-2">
    <h6 class="mb-0"><i class="fas fa-sitemap me-2"></i>RiC Context</h6>
  </div>
  <div class="card-body p-0" id="ric-context-body">
    <div class="text-center py-3">
      <div class="spinner-border spinner-border-sm text-success"></div>
      <p class="small text-muted mt-1 mb-0">Loading context...</p>
    </div>
  </div>
  <div class="card-footer py-2">
    <a href="/explorer" class="btn btn-sm btn-outline-success w-100">
      <i class="fas fa-project-diagram me-1"></i>{{ __('View in Graph Explorer') }}
    </a>
  </div>
</section>

<script>
(function() {
  var resourceId = {{ (int) $resourceId }};

  fetch('/ric-api/data?id=' + resourceId + '&_=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var body = document.getElementById('ric-context-body');
      if (!data.success || !data.graphData || !data.graphData.nodes || data.graphData.nodes.length === 0) {
        body.innerHTML = '<div class="p-3 text-muted small">No RiC context available for this entity.</div>';
        return;
      }

      var nodes = data.graphData.nodes;
      var edges = data.graphData.edges || [];

      // Group related nodes by type
      var groups = {};
      nodes.forEach(function(n) {
        if (n.id == resourceId) return;
        var type = n.type || 'Other';
        if (!groups[type]) groups[type] = [];
        groups[type].push(n);
      });

      var typeIcons = {
        'RecordSet': 'fas fa-folder text-info',
        'Record': 'fas fa-file-alt text-info',
        'RecordPart': 'fas fa-file text-info',
        'Person': 'fas fa-user text-danger',
        'CorporateBody': 'fas fa-building text-warning',
        'Family': 'fas fa-users text-danger',
        'Activity': 'fas fa-bolt text-purple',
        'Function': 'fas fa-cogs text-purple',
        'Place': 'fas fa-map-marker-alt text-orange',
        'Concept': 'fas fa-tag text-success',
        'DocumentaryFormType': 'fas fa-tag text-secondary',
        'CarrierType': 'fas fa-tag text-secondary',
        'ContentType': 'fas fa-tag text-secondary',
        'Language': 'fas fa-globe text-primary'
      };

      var html = '<div class="list-group list-group-flush">';

      var typeOrder = ['Person', 'CorporateBody', 'Family', 'RecordSet', 'Record', 'RecordPart', 'Place', 'Activity', 'Function', 'Concept'];
      var allTypes = Object.keys(groups);
      // Sort: known types first, then rest
      allTypes.sort(function(a, b) {
        var ai = typeOrder.indexOf(a), bi = typeOrder.indexOf(b);
        if (ai === -1) ai = 999;
        if (bi === -1) bi = 999;
        return ai - bi;
      });

      allTypes.forEach(function(type) {
        var items = groups[type];
        var icon = typeIcons[type] || 'fas fa-circle text-secondary';
        html += '<div class="list-group-item px-3 py-2">';
        html += '<div class="d-flex justify-content-between align-items-center mb-1">';
        html += '<strong class="small"><i class="' + icon + ' me-1"></i>' + type + '</strong>';
        html += '<span class="badge bg-secondary">' + items.length + '</span>';
        html += '</div>';
        items.slice(0, 5).forEach(function(n) {
          var label = n.label || 'Unknown';
          if (label.length > 40) label = label.substring(0, 40) + '...';
          html += '<div class="small text-muted ps-3">' + label.replace(/</g, '&lt;') + '</div>';
        });
        if (items.length > 5) {
          html += '<div class="small text-muted ps-3 fst-italic">+ ' + (items.length - 5) + ' more</div>';
        }
        html += '</div>';
      });

      html += '</div>';

      // Summary line
      html += '<div class="px-3 py-2 border-top small text-muted">';
      html += '<i class="fas fa-info-circle me-1"></i>' + nodes.length + ' entities, ' + edges.length + ' relationships';
      html += '</div>';

      body.innerHTML = html;
    })
    .catch(function(err) {
      document.getElementById('ric-context-body').innerHTML =
        '<div class="p-3 text-danger small">Error loading context: ' + err.message + '</div>';
    });
})();
</script>
@endif
