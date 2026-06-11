@extends('theme::layouts.1col')

@section('title', 'System Map - How it all fits together')
@section('body-class', 'help system-map')

@push('css')
<style nonce="{{ $cspNonce ?? '' }}">
  /* The shell must NOT clip the canvas - an overflow:hidden ancestor would
     paint the Cytoscape <canvas> blank, so keep the shell explicitly visible. */
  #systemMapShell { position: relative; overflow: visible; }
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
    overflow: hidden; /* clip the canvas to the rounded box itself */
  }
  #systemMapCanvas:focus { outline: 2px solid #0d6efd; outline-offset: 2px; }
  /* Toolbar must wrap cleanly on narrow / mobile screens. */
  .system-map-toolbar { gap: .25rem; flex-wrap: wrap; }
  .system-map-toolbar .btn { flex: 0 0 auto; }
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

  /* ----- Visible-failure + static fallback surfaces -----
     These replace the silent blank-white canvas whenever Cytoscape cannot
     render (lib missing, init/layout exception, or no usable size). */
  .system-map-error {
    padding: 1.25rem 1.5rem;
    background: #fff3cd;
    border: 1px solid #ffecb5;
    border-radius: .5rem;
    color: #664d03;
  }
  .system-map-error code {
    display: block;
    margin-top: .5rem;
    padding: .5rem .75rem;
    background: rgba(0,0,0,.05);
    border-radius: .35rem;
    color: #842029;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: .8rem;
  }
  .system-map-fallback { margin-top: 1rem; }
  .system-map-fallback > ul { padding-left: 1.1rem; }
  .system-map-fallback ul { list-style: none; padding-left: 1.25rem; }
  .system-map-fallback li { margin: .35rem 0; }
  .system-map-fallback .sm-stage {
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
  }
  .system-map-fallback .sm-dot {
    display: inline-block; width: .8rem; height: .8rem;
    border-radius: .2rem; flex: 0 0 auto;
  }
  .system-map-fallback .sm-sub { color: #6c757d; font-size: .85rem; }
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

    {{--
      Static fallback outline. Rendered server-side from the SAME data as the
      interactive map so the page is NEVER just blank white. It is visible by
      default (covers JS-disabled + lib-failed-to-parse cases); the init script
      hides it the moment Cytoscape paints successfully. On a caught init/layout
      error the script re-shows it (and shows the error text above), so a failure
      is always diagnosable instead of a silent white box.
    --}}
    @php
      // Reconstruct the stage -> children tree from the flat Cytoscape element
      // list (the controller passes only 'elements' + 'bands'). Pure derivation,
      // no controller change needed - keeps the fallback fully data-driven.
      $smStages = [];
      $smChildren = [];
      foreach ($graph['elements'] ?? [] as $el) {
          $d = $el['data'] ?? [];
          if (($d['kind'] ?? null) === 'stage') {
              $smStages[$d['id']] = $d;
          } elseif (($d['kind'] ?? null) === 'child') {
              $smChildren[$d['subId'] ?? ''][] = $d;
          }
      }
    @endphp
    <div id="systemMapFallback" class="system-map-fallback">
      <p class="text-muted small mb-2">
        <i class="bi bi-list-nested me-1"></i>{{ __('Platform stages (text outline)') }}
      </p>
      <ul>
        @foreach($smStages as $sid => $stage)
          <li>
            <span class="sm-stage">
              <span class="sm-dot" style="background: {{ $stage['color'] ?? '#264653' }};"></span>
              @if(!empty($stage['help']))
                <a href="{{ route('help.article', $stage['help']) }}" class="text-decoration-none">{{ $stage['label'] }}</a>
              @else
                {{ $stage['label'] }}
              @endif
            </span>
            @if(!empty($stage['sub']))
              <span class="sm-sub ms-1">- {{ $stage['sub'] }}</span>
            @endif
            @if(!empty($smChildren[$sid]))
              <ul>
                @foreach($smChildren[$sid] as $child)
                  <li>
                    @if(!empty($child['help']))
                      <a href="{{ route('help.article', $child['help']) }}" class="text-decoration-none">{{ $child['label'] }}</a>
                    @else
                      {{ $child['label'] }}
                    @endif
                    @if(!empty($child['sub']))
                      <span class="sm-sub ms-1">- {{ $child['sub'] }}</span>
                    @endif
                  </li>
                @endforeach
              </ul>
            @endif
          </li>
        @endforeach
      </ul>
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

  // Run AFTER the full load event: by then the stylesheet + webfonts have
  // applied and the container has its real laid-out size. On mobile the box
  // height (70vh) settles only after the address bar / viewport stabilises, so
  // measuring at DOMContentLoaded can yield a zero/too-small box and a blank
  // canvas. window 'load' + a rAF gives Cytoscape a real size to measure.
  function ready(fn) {
    function go() { requestAnimationFrame(fn); }
    if (document.readyState === 'complete') { go(); }
    else { window.addEventListener('load', go); }
  }

  // Static fallback outline (server-rendered). Shown by default; hidden once
  // the canvas paints, re-shown on any caught failure.
  var fallbackEl = document.getElementById('systemMapFallback');
  function showFallback() { if (fallbackEl) { fallbackEl.style.display = ''; } }
  function hideFallback() { if (fallbackEl) { fallbackEl.style.display = 'none'; } }

  // Replace the (potentially blank) canvas with a visible, diagnosable error
  // instead of silent white. Keeps the static outline below it.
  function showError(container, msg, err) {
    var detail = '';
    if (err) { detail = (err && err.stack) ? String(err.stack) : String(err && err.message ? err.message : err); }
    var box = document.createElement('div');
    box.className = 'system-map-error';
    var p = document.createElement('div');
    p.innerHTML = '<strong><i class="bi bi-exclamation-triangle me-1"></i>' +
      '{{ __('The interactive map could not be drawn.') }}</strong> ' +
      '{{ __('A text outline is shown below.') }}';
    box.appendChild(p);
    var why = document.createElement('div');
    why.className = 'mt-1 small';
    why.textContent = msg || '';
    box.appendChild(why);
    if (detail) {
      var code = document.createElement('code');
      code.textContent = detail;
      box.appendChild(code);
    }
    if (container) {
      container.style.height = 'auto';
      container.style.minHeight = '0';
      container.innerHTML = '';
      container.appendChild(box);
    }
    showFallback();
  }

  ready(function () {
    var container = document.getElementById('systemMapCanvas');
    if (!container || typeof cytoscape === 'undefined') {
      // Library failed to load/parse - keep the static outline visible and say so.
      showError(container, '{{ __('Map library failed to load.') }}', null);
      return;
    }

    // ---- Guaranteed size: never hand Cytoscape a zero-sized container. ----
    // If CSS hasn't given the box a real height/width yet, force an explicit
    // pixel size so the very first measure is non-zero. This is the core
    // mobile fix - a 0px container paints blank and never recovers without a
    // resize event we can't guarantee.
    if (container.clientHeight === 0 || container.clientWidth === 0) {
      var h = Math.max(Math.round(window.innerHeight * 0.7), 420);
      container.style.height = h + 'px';
      if (container.clientWidth === 0) { container.style.width = '100%'; }
    }

    var cy;

    try {

    // ---- expandedStage: which top-level stage (if any) is drilled into ----
    var expandedStage = null;

    cy = cytoscape({
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

    // The interactive canvas painted successfully - hide the static outline.
    hideFallback();

    // ---- keep the canvas sized to its container. Fixes the blank/empty map on mobile,
    //      where the container's real size settles AFTER Cytoscape first measures it (CSS/
    //      font reflow, address bar), and re-fits on rotate / window resize. ----
    function refit() { try { cy.resize(); cy.fit(cy.elements(':visible'), 40); } catch (e) {} }
    requestAnimationFrame(refit);
    setTimeout(refit, 250);
    setTimeout(refit, 800);
    var _smRt;
    window.addEventListener('resize', function () { clearTimeout(_smRt); _smRt = setTimeout(refit, 150); });
    window.addEventListener('orientationchange', function () { setTimeout(refit, 300); });

    // ---- ResizeObserver: the reliable fix for "container settles after init".
    //      On mobile the box frequently grows from a small/zero size to its real
    //      size a tick or two AFTER Cytoscape inits. Observing the canvas box and
    //      re-running resize+fit whenever it changes guarantees the graph fills
    //      the box once it finally has one - covering cases the timed refits miss.
    if (typeof ResizeObserver !== 'undefined') {
      var _lastW = 0, _lastH = 0, _roT;
      var ro = new ResizeObserver(function (entries) {
        var box = entries[0] && entries[0].contentRect ? entries[0].contentRect
                : { width: container.clientWidth, height: container.clientHeight };
        // Only react to real size changes (avoid feedback loops from fit()).
        if (Math.abs(box.width - _lastW) < 2 && Math.abs(box.height - _lastH) < 2) { return; }
        _lastW = box.width; _lastH = box.height;
        if (box.width > 0 && box.height > 0) {
          clearTimeout(_roT);
          _roT = setTimeout(refit, 60);
        }
      });
      ro.observe(container);
    }

    } catch (err) {
      // Any init/layout exception used to leave a silent blank-white box.
      // Surface it instead, and fall back to the server-rendered text outline.
      try { if (cy && cy.destroy) { cy.destroy(); } } catch (e2) {}
      showError(container, '{{ __('The interactive map hit an error while drawing.') }}', err);
      if (window.console && console.error) { console.error('[system-map] init failed:', err); }
    }
  });
})();
</script>
@endpush
