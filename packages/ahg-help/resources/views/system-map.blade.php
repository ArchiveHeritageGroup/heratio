@extends('theme::layouts.1col')

@section('title', 'System Map - How it all fits together')
@section('body-class', 'help system-map')

@push('css')
<style nonce="{{ $cspNonce ?? '' }}">
  #systemMapShell { position: relative; }
  #systemMapCanvas {
    width: 100%;
    height: 70vh;
    min-height: 480px;
    background:
      radial-gradient(circle, rgba(0,0,0,.04) 1px, transparent 1px) 0 0 / 22px 22px,
      #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    touch-action: none;
  }
  #systemMapCanvas:focus { outline: 2px solid #0d6efd; outline-offset: 2px; }
  .system-map-toolbar { gap: .25rem; flex-wrap: wrap; }
  .system-map-bread {
    min-height: 1.75rem;
    font-size: .9rem;
  }
  .system-map-bread .crumb-link { cursor: pointer; }
  .system-map-legend .swatch {
    display: inline-block; width: .85rem; height: .85rem;
    border-radius: .2rem; vertical-align: middle; margin-right: .35rem;
  }
  .system-map-help-hint { font-size: .8rem; }
</style>
@endpush

@section('content')
<div class="row">
  <div class="col-12">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-2">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('help.index') }}">{{ __('Help Center') }}</a></li>
        <li class="breadcrumb-item active">{{ __('System Map') }}</li>
      </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
      <div>
        <h1 class="h3 mb-1"><i class="bi bi-diagram-3 me-2"></i>{{ __('System Map') }}</h1>
        <p class="text-muted mb-0">
          {{ __('One traversable diagram of the whole platform - pan, zoom, and drill into any stage. Click a node with a link icon to open its help article.') }}
        </p>
      </div>
    </div>

    {{-- Toolbar --}}
    <div class="d-flex system-map-toolbar mb-2">
      <button type="button" id="smZoomIn"  class="btn btn-sm atom-btn-white" title="{{ __('Zoom in') }}"><i class="bi bi-zoom-in"></i></button>
      <button type="button" id="smZoomOut" class="btn btn-sm atom-btn-white" title="{{ __('Zoom out') }}"><i class="bi bi-zoom-out"></i></button>
      <button type="button" id="smFit"     class="btn btn-sm atom-btn-white" title="{{ __('Reset / fit to screen') }}"><i class="bi bi-arrows-fullscreen me-1"></i>{{ __('Fit') }}</button>
      <button type="button" id="smUp"      class="btn btn-sm atom-btn-white" title="{{ __('Collapse / back up') }}"><i class="bi bi-arrow-up-left-circle me-1"></i>{{ __('Up') }}</button>
      <span class="vr mx-1 d-none d-md-inline"></span>
      <span class="text-muted small align-self-center d-none d-md-inline">
        {{ __('Drag to pan - wheel to zoom - arrow keys to pan - click a stage to drill in') }}
      </span>
    </div>

    {{-- Breadcrumb of where you are inside the map --}}
    <div class="system-map-bread mb-2" id="smBread"></div>

    <div id="systemMapShell">
      <div id="systemMapCanvas" tabindex="0" role="application"
           aria-label="{{ __('Interactive system flow map. Use arrow keys to pan, plus and minus to zoom, Enter to drill into the focused stage, and Escape to go up.') }}"></div>
    </div>

    {{-- Cross-cutting bands legend --}}
    @if(!empty($graph['bands']))
      <div class="system-map-legend mt-3">
        <span class="text-muted small me-2">{{ __('Cross-cutting concerns:') }}</span>
        @foreach($graph['bands'] as $bid => $band)
          @if(!empty($band['help']))
            <a href="{{ route('help.article', $band['help']) }}" class="badge text-decoration-none me-2 mb-1" style="background: {{ $band['color'] }};">
              <span class="swatch" style="background: rgba(255,255,255,.6);"></span>{{ $band['label'] }}
            </a>
          @else
            <span class="badge me-2 mb-1" style="background: {{ $band['color'] }};">{{ $band['label'] }}</span>
          @endif
        @endforeach
      </div>
    @endif

    <p class="system-map-help-hint text-muted mt-2 mb-0">
      <i class="bi bi-info-circle me-1"></i>
      {{ __('This map is data-driven. To update it, edit packages/ahg-help/resources/data/system-map.php.') }}
    </p>
  </div>
</div>
@endsection

@push('js')
<script nonce="{{ $cspNonce ?? '' }}" src="/vendor/cytoscape/cytoscape.min.js"></script>
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  'use strict';

  var GRAPH = @json($graph['elements']);
  var ARTICLE_BASE = "{{ url('/help/article') }}/";

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var container = document.getElementById('systemMapCanvas');
    if (!container || typeof cytoscape === 'undefined') {
      if (container) {
        container.innerHTML = '<div class="p-4 text-danger">Map library failed to load.</div>';
      }
      return;
    }

    // ---- expandedStage: which top-level stage (if any) is drilled into ----
    var expandedStage = null;

    var cy = cytoscape({
      container: container,
      elements: GRAPH,
      wheelSensitivity: 0.2,
      minZoom: 0.15,
      maxZoom: 3.0,
      boxSelectionEnabled: false,
      style: [
        {
          selector: 'node',
          style: {
            'label': 'data(label)',
            'text-valign': 'center',
            'text-halign': 'center',
            'text-wrap': 'wrap',
            'text-max-width': '120px',
            'font-size': '13px',
            'font-weight': 600,
            'color': '#fff',
            'background-color': 'data(color)',
            'border-width': 0,
            'shape': 'round-rectangle',
            'width': 'label',
            'height': 'label',
            'padding': '14px'
          }
        },
        {
          // Compound (stage) parent containers when expanded.
          selector: ':parent',
          style: {
            'background-opacity': 0.10,
            'background-color': 'data(color)',
            'border-width': 2,
            'border-color': 'data(color)',
            'border-opacity': 0.5,
            'text-valign': 'top',
            'text-halign': 'center',
            'color': '#212529',
            'font-size': '15px',
            'padding': '24px',
            'shape': 'round-rectangle'
          }
        },
        {
          selector: 'node[?help]',
          style: {
            // nodes with a help slug get a subtle ring to signal "linkable"
            'border-width': 3,
            'border-color': '#fff',
            'border-opacity': 0.9
          }
        },
        {
          selector: 'node:selected',
          style: { 'border-width': 4, 'border-color': '#0d6efd', 'border-opacity': 1 }
        },
        {
          selector: 'edge',
          style: {
            'width': 2.5,
            'line-color': '#adb5bd',
            'target-arrow-color': '#adb5bd',
            'target-arrow-shape': 'triangle',
            'curve-style': 'bezier'
          }
        },
        {
          selector: 'edge[kind="child-edge"]',
          style: { 'line-color': '#6c757d', 'target-arrow-color': '#6c757d', 'line-style': 'dashed', 'width': 2 }
        },
        { selector: '.sm-hidden', style: { 'display': 'none' } }
      ]
    });

    // ----------------------------------------------------------------
    // Drill model: by default ONLY top-level stage nodes + stage edges
    // are visible. Children + child-edges are hidden until you drill in.
    // ----------------------------------------------------------------
    function applyVisibility() {
      cy.batch(function () {
        cy.elements().removeClass('sm-hidden');

        cy.nodes('[kind="child"]').forEach(function (n) {
          if (expandedStage && n.data('subId') === expandedStage) {
            n.removeClass('sm-hidden');
          } else {
            n.addClass('sm-hidden');
          }
        });

        cy.edges('[kind="child-edge"]').forEach(function (e) {
          if (expandedStage && e.data('subId') === expandedStage) {
            e.removeClass('sm-hidden');
          } else {
            e.addClass('sm-hidden');
          }
        });

        // When focused on one stage, dim the sibling stages + spine edges
        if (expandedStage) {
          cy.nodes('[kind="stage"]').forEach(function (n) {
            if (n.id() !== expandedStage) { n.addClass('sm-hidden'); }
          });
          cy.edges('[kind="stage-edge"]').addClass('sm-hidden');
        }
      });
    }

    function relayout(fit) {
      var opts;
      if (expandedStage) {
        opts = { name: 'breadthfirst', directed: true, spacingFactor: 1.25, padding: 30, animate: false };
      } else {
        opts = { name: 'breadthfirst', directed: true, spacingFactor: 1.4, padding: 40, animate: false };
      }
      var eles = cy.elements(':visible');
      eles.layout(opts).run();
      if (fit !== false) { cy.fit(eles, 40); }
    }

    function renderBread() {
      var bread = document.getElementById('smBread');
      if (!bread) return;
      bread.innerHTML = '';

      var root = document.createElement('a');
      root.className = 'crumb-link link-primary text-decoration-none';
      root.textContent = '{{ __('Whole system') }}';
      root.addEventListener('click', function () { drillUp(); });
      bread.appendChild(root);

      if (expandedStage) {
        var node = cy.getElementById(expandedStage);
        var sep = document.createElement('span');
        sep.className = 'text-muted mx-2';
        sep.textContent = '/';
        bread.appendChild(sep);
        var cur = document.createElement('span');
        cur.className = 'fw-semibold';
        cur.textContent = node.data('label') || expandedStage;
        bread.appendChild(cur);

        if (node.data('help')) {
          var a = document.createElement('a');
          a.className = 'ms-2 small';
          a.href = ARTICLE_BASE + node.data('help');
          a.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> {{ __('Open help') }}';
          bread.appendChild(a);
        }
      }
    }

    function drillInto(stageId) {
      if (!stageId) return;
      var n = cy.getElementById(stageId);
      if (n.empty() || n.data('kind') !== 'stage') return;
      if (!n.data('hasChildren')) {
        // leaf stage -> open its help article directly if present
        if (n.data('help')) { window.location.href = ARTICLE_BASE + n.data('help'); }
        return;
      }
      expandedStage = stageId;
      applyVisibility();
      relayout(true);
      renderBread();
    }

    function drillUp() {
      if (!expandedStage) { cy.fit(cy.elements(':visible'), 40); return; }
      expandedStage = null;
      applyVisibility();
      relayout(true);
      renderBread();
    }

    // ---- node click: drill-in for stages, deep-link for children ----
    cy.on('tap', 'node', function (evt) {
      var n = evt.target;
      var kind = n.data('kind');
      if (kind === 'stage') {
        // single tap on a collapsed stage drills in; if already expanded,
        // tapping it opens its help article (avoids a dead-end).
        if (expandedStage === n.id() && n.data('help')) {
          window.location.href = ARTICLE_BASE + n.data('help');
        } else {
          drillInto(n.id());
        }
      } else if (kind === 'child') {
        if (n.data('help')) {
          window.location.href = ARTICLE_BASE + n.data('help');
        }
      }
    });

    // ---- toolbar buttons ----
    function zoomBy(factor) {
      cy.zoom({ level: cy.zoom() * factor, renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 } });
    }
    document.getElementById('smZoomIn').addEventListener('click', function () { zoomBy(1.25); });
    document.getElementById('smZoomOut').addEventListener('click', function () { zoomBy(0.8); });
    document.getElementById('smFit').addEventListener('click', function () { drillUp(); });
    document.getElementById('smUp').addEventListener('click', function () { drillUp(); });

    // ---- keyboard: arrows pan, +/- zoom, Enter drills, Esc up ----
    container.addEventListener('keydown', function (e) {
      var step = 60;
      switch (e.key) {
        case 'ArrowLeft':  cy.panBy({ x: step, y: 0 });  e.preventDefault(); break;
        case 'ArrowRight': cy.panBy({ x: -step, y: 0 }); e.preventDefault(); break;
        case 'ArrowUp':    cy.panBy({ x: 0, y: step });  e.preventDefault(); break;
        case 'ArrowDown':  cy.panBy({ x: 0, y: -step }); e.preventDefault(); break;
        case '+': case '=': zoomBy(1.25); e.preventDefault(); break;
        case '-': case '_': zoomBy(0.8);  e.preventDefault(); break;
        case 'Enter': {
          var sel = cy.nodes(':selected');
          if (sel.nonempty()) { drillInto(sel[0].id()); }
          e.preventDefault();
          break;
        }
        case 'Escape': drillUp(); e.preventDefault(); break;
        default: break;
      }
    });

    // ---- pointer cursor hint on hoverable nodes ----
    cy.on('mouseover', 'node', function (evt) {
      var n = evt.target;
      if (n.data('kind') === 'stage' || n.data('help')) { container.style.cursor = 'pointer'; }
    });
    cy.on('mouseout', 'node', function () { container.style.cursor = 'default'; });

    // ---- initial render ----
    applyVisibility();
    relayout(true);
    renderBread();
  });
})();
</script>
@endpush
