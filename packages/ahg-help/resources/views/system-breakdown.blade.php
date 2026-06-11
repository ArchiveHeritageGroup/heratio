@extends('theme::layouts.1col')

@section('title', 'System Breakdown - Capabilities by record type')
@section('body-class', 'help system-breakdown')

@push('css')
<style nonce="{{ $cspNonce ?? '' }}">
  /* The shell must NOT clip the canvas - an overflow:hidden ancestor would
     paint the Cytoscape <canvas> blank, so keep the shell explicitly visible. */
  #sbShell { position: relative; overflow: visible; }
  #sbCanvas {
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
  #sbCanvas:focus { outline: 2px solid #0d6efd; outline-offset: 2px; }
  /* Toolbar must wrap cleanly on narrow / mobile screens. */
  .sb-toolbar { gap: .25rem; flex-wrap: wrap; }
  .sb-toolbar .btn { flex: 0 0 auto; }
  .sb-bread {
    min-height: 1.75rem;
    font-size: .9rem;
  }
  .sb-bread .crumb-link { cursor: pointer; }
  .sb-help-hint { font-size: .8rem; }

  /* ----- Navigation aids (search, entity filter, minimap) ----- */
  .sb-search { position: relative; }
  .sb-search .form-control-sm { min-width: 9rem; }
  .sb-search .sb-search-clear {
    position: absolute; right: .25rem; top: 50%; transform: translateY(-50%);
    border: 0; background: transparent; color: #6c757d; line-height: 1;
    padding: 0 .35rem; cursor: pointer;
  }
  /* Entity filter chips - wrap freely below the toolbar on narrow screens. */
  .sb-entity-filter { gap: .35rem .4rem; flex-wrap: wrap; }
  .sb-entity-filter .sb-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    border: 1px solid #ced4da; background: #fff; color: #212529;
    border-radius: 999px; padding: .15rem .6rem; font-size: .8rem;
    cursor: pointer; user-select: none; line-height: 1.4;
  }
  .sb-entity-filter .sb-chip:hover { background: #f1f3f5; }
  .sb-entity-filter .sb-chip.sb-chip-off { opacity: .5; }
  .sb-entity-filter .sb-chip.sb-chip-off .sb-chip-dot { filter: grayscale(1); }
  .sb-entity-filter .sb-chip-dot {
    display: inline-block; width: .7rem; height: .7rem;
    border-radius: 50%; flex: 0 0 auto;
  }
  .sb-entity-filter .sb-chip input { display: none; }
  .sb-entity-count { font-size: .78rem; }

  /* Minimap overview panel - absolutely positioned over the canvas,
     bottom-right, never overlapping toolbar/fallback. Click-to-recenter;
     the host canvas keeps full pan/zoom. */
  #sbMinimap {
    position: absolute;
    right: .6rem; bottom: .6rem;
    width: 168px; height: 120px;
    background: rgba(255,255,255,.92);
    border: 1px solid #ced4da;
    border-radius: .4rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.12);
    overflow: hidden;
    z-index: 5;
    cursor: pointer;
  }
  #sbMinimap canvas { display: block; width: 100%; height: 100%; }
  #sbMinimap .sb-mini-label {
    position: absolute; top: 2px; left: 5px;
    font-size: .62rem; color: #6c757d; pointer-events: none;
    text-transform: uppercase; letter-spacing: .03em;
  }
  /* On very small screens the minimap eats too much of the canvas - shrink it. */
  @media (max-width: 575.98px) {
    #sbMinimap { width: 116px; height: 84px; right: .4rem; bottom: .4rem; }
  }

  /* ----- Visible-failure + static fallback surfaces -----
     These replace the silent blank-white canvas whenever Cytoscape cannot
     render (lib missing, init/layout exception, or no usable size). */
  .sb-error {
    padding: 1.25rem 1.5rem;
    background: #fff3cd;
    border: 1px solid #ffecb5;
    border-radius: .5rem;
    color: #664d03;
  }
  .sb-error code {
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
  .sb-fallback { margin-top: 1rem; }
  .sb-fallback > ul { padding-left: 1.1rem; }
  .sb-fallback ul { list-style: none; padding-left: 1.25rem; }
  .sb-fallback li { margin: .35rem 0; }
  .sb-fallback .sb-entity {
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
  }
  .sb-fallback .sb-dot {
    display: inline-block; width: .8rem; height: .8rem;
    border-radius: .2rem; flex: 0 0 auto;
  }
  .sb-fallback .sb-sub { color: #6c757d; font-size: .85rem; }
</style>
@endpush

@section('content')
@php
  // Reconstruct the root -> entity -> aspect -> feature tree from the flat
  // Cytoscape element list (the controller passes only 'elements'). Pure
  // derivation, no controller change needed - feeds BOTH the entity-filter
  // chips and the server-rendered static fallback (now 4 levels deep) below.
  $sbRoot = null;
  $sbEntities = [];      // depth-1 nodes (L2), keyed by id
  $sbChildren = [];      // parent-id => [child node datas]  (works for all levels)
  foreach ($graph['elements'] ?? [] as $el) {
      $d = $el['data'] ?? [];
      $kind = $d['kind'] ?? null;
      if ($kind === 'root') {
          $sbRoot = $d;
      } elseif ($kind === 'l2') {
          $sbEntities[$d['id']] = $d;
          $sbChildren[$d['parent'] ?? ''][] = $d;
      } elseif ($kind === 'l3' || $kind === 'l4') {
          $sbChildren[$d['parent'] ?? ''][] = $d;
      }
  }
@endphp
<div class="row">
  <div class="col-12">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-2">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('help.index') }}">{{ __('Help Center') }}</a></li>
        <li class="breadcrumb-item active">{{ __('System Breakdown') }}</li>
      </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
      <div>
        <h1 class="h3 mb-1"><i class="bi bi-diagram-2 me-2"></i>{{ __('System Breakdown') }}</h1>
        <p class="text-muted mb-0">
          {{ __('A four-level capability tree of the whole platform: Heratio, then each record type, then the functional aspects, then the concrete tools. Pan, zoom, and drill down level by level. Click a leaf with a link icon to open its help article.') }}
        </p>
      </div>
      <div class="text-md-end mt-2 mt-md-0">
        <a href="{{ route('help.system-map') }}" class="btn btn-sm atom-btn-white">
          <i class="bi bi-diagram-3 me-1"></i>{{ __('View workflow System Map') }}
        </a>
      </div>
    </div>

    {{-- Toolbar --}}
    <div class="d-flex align-items-center sb-toolbar mb-2">
      <button type="button" id="sbZoomIn"  class="btn btn-sm atom-btn-white" title="{{ __('Zoom in') }}"><i class="bi bi-zoom-in"></i></button>
      <button type="button" id="sbZoomOut" class="btn btn-sm atom-btn-white" title="{{ __('Zoom out') }}"><i class="bi bi-zoom-out"></i></button>
      <button type="button" id="sbFit"     class="btn btn-sm atom-btn-white" title="{{ __('Reset / fit to screen') }}"><i class="bi bi-arrows-fullscreen me-1"></i>{{ __('Fit') }}</button>
      <button type="button" id="sbUp"      class="btn btn-sm atom-btn-white" title="{{ __('Collapse / back up') }}"><i class="bi bi-arrow-up-left-circle me-1"></i>{{ __('Up') }}</button>
      <span class="vr mx-1 d-none d-md-inline"></span>
      {{-- In-map search: type to highlight matching nodes and dim the rest. --}}
      <div class="sb-search">
        <label for="sbSearch" class="visually-hidden">{{ __('Search the breakdown') }}</label>
        <input type="search" id="sbSearch" class="form-control form-control-sm"
               autocomplete="off" placeholder="{{ __('Search nodes…') }}"
               aria-label="{{ __('Search the breakdown by node label') }}" style="padding-right: 1.6rem;">
        <button type="button" id="sbSearchClear" class="sb-search-clear" hidden
                title="{{ __('Clear search') }}" aria-label="{{ __('Clear search') }}"><i class="bi bi-x-circle"></i></button>
      </div>
      <span class="vr mx-1 d-none d-md-inline"></span>
      <span class="text-muted small align-self-center d-none d-md-inline">
        {{ __('Drag to pan - wheel to zoom - arrow keys to pan - click a node to drill in') }}
      </span>
    </div>

    {{-- Entity filter: one colour-dotted chip per L2 record type. Toggling a
         chip hides/shows that entity's subtree and re-fits the visible graph. --}}
    <div class="d-flex align-items-center sb-entity-filter mb-2" id="sbEntityFilter">
      <span class="text-muted small me-1">{{ __('Record types:') }}</span>
      <button type="button" id="sbEntitiesAll"   class="sb-chip" title="{{ __('Show all record types') }}">
        <i class="bi bi-check2-all"></i>{{ __('All') }}
      </button>
      <button type="button" id="sbEntitiesReset" class="sb-chip" title="{{ __('Reset filter and view') }}">
        <i class="bi bi-arrow-counterclockwise"></i>{{ __('Reset') }}
      </button>
      @foreach($sbEntities as $eid => $entity)
        <label class="sb-chip" data-entity="{{ $eid }}" title="{{ $entity['label'] }}">
          <input type="checkbox" class="sb-entity-toggle" value="{{ $eid }}" checked>
          <span class="sb-chip-dot" style="background: {{ $entity['color'] ?? '#264653' }};"></span>
          <span class="sb-chip-text">{{ $entity['label'] }}</span>
        </label>
      @endforeach
      <span class="text-muted sb-entity-count ms-2" id="sbEntityCount" aria-live="polite"></span>
    </div>

    {{-- Breadcrumb of where you are inside the tree (Heratio / L2 / L3 / L4) --}}
    <div class="sb-bread mb-2" id="sbBread"></div>

    <div id="sbShell">
      <div id="sbCanvas" tabindex="0" role="application"
           aria-label="{{ __('Interactive system breakdown tree. Use arrow keys to pan, plus and minus to zoom, Enter to drill into the focused node, and Escape to go up.') }}"></div>
      {{-- Minimap overview: a small whole-graph render with a viewport rectangle,
           absolutely positioned over the canvas (bottom-right). Built by the init
           script ONLY after the main canvas paints; hidden if Cytoscape fails. --}}
      <div id="sbMinimap" hidden role="img"
           aria-label="{{ __('Overview minimap of the whole breakdown') }}" title="{{ __('Overview - click to recenter') }}">
        <span class="sb-mini-label">{{ __('Overview') }}</span>
      </div>
    </div>

    {{--
      Static fallback outline. Rendered server-side from the SAME data as the
      interactive diagram so the page is NEVER just blank white. It is visible by
      default (covers JS-disabled + lib-failed-to-parse cases); the init script
      hides it the moment Cytoscape paints successfully. On a caught init/layout
      error the script re-shows it (and shows the error text above), so a failure
      is always diagnosable instead of a silent white box. Four levels deep.
    --}}
    <div id="sbFallback" class="sb-fallback">
      <p class="text-muted small mb-2">
        <i class="bi bi-list-nested me-1"></i>{{ __('Capabilities by record type (text outline)') }}
      </p>
      <ul>
        @foreach($sbEntities as $eid => $entity)
          <li>
            <span class="sb-entity">
              <span class="sb-dot" style="background: {{ $entity['color'] ?? '#264653' }};"></span>
              @if(!empty($entity['help']))
                <a href="{{ route('help.article', $entity['help']) }}" class="text-decoration-none">{{ $entity['label'] }}</a>
              @else
                {{ $entity['label'] }}
              @endif
            </span>
            @if(!empty($entity['sub']))
              <span class="sb-sub ms-1">- {{ $entity['sub'] }}</span>
            @endif
            @if(!empty($sbChildren[$eid]))
              <ul>
                @foreach($sbChildren[$eid] as $aspect)
                  <li>
                    @if(!empty($aspect['help']))
                      <a href="{{ route('help.article', $aspect['help']) }}" class="text-decoration-none">{{ $aspect['label'] }}</a>
                    @else
                      {{ $aspect['label'] }}
                    @endif
                    @if(!empty($aspect['sub']))
                      <span class="sb-sub ms-1">- {{ $aspect['sub'] }}</span>
                    @endif
                    @if(!empty($sbChildren[$aspect['id'] ?? '']))
                      <ul>
                        @foreach($sbChildren[$aspect['id']] as $feature)
                          <li>
                            @if(!empty($feature['help']))
                              <a href="{{ route('help.article', $feature['help']) }}" class="text-decoration-none">{{ $feature['label'] }}</a>
                            @else
                              {{ $feature['label'] }}
                            @endif
                            @if(!empty($feature['sub']))
                              <span class="sb-sub ms-1">- {{ $feature['sub'] }}</span>
                            @endif
                          </li>
                        @endforeach
                      </ul>
                    @endif
                  </li>
                @endforeach
              </ul>
            @endif
          </li>
        @endforeach
      </ul>
    </div>

    <p class="sb-help-hint text-muted mt-2 mb-0">
      <i class="bi bi-info-circle me-1"></i>
      {{ __('This breakdown is data-driven. To update it, edit packages/ahg-help/resources/data/system-breakdown.php.') }}
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

  // Each node declares `parent: <parentId>`, which would make Cytoscape treat
  // the parent as a COMPOUND PARENT node. The :parent style then paints it at
  // background-opacity 0.10 (90% transparent) and auto-sizes it to its (hidden)
  // children, so collapsed nodes render nearly invisible - the diagram looked
  // blank even though the nodes were present and clickable. The drill model is
  // pure show/hide and never relies on compound nesting, so strip `parent` to
  // render every node as a solid, visible block. (We keep the parent linkage in
  // a side map below so the drill model still knows the hierarchy.)
  var PARENT_OF = Object.create(null);     // childId -> parentId
  GRAPH.forEach(function (el) {
    if (el && el.data && el.data.parent != null) {
      PARENT_OF[el.data.id] = el.data.parent;
      delete el.data.parent;
    }
  });
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
  var fallbackEl = document.getElementById('sbFallback');
  function showFallback() { if (fallbackEl) { fallbackEl.style.display = ''; } }
  function hideFallback() { if (fallbackEl) { fallbackEl.style.display = 'none'; } }

  // Small-screen mode: present the static outline as the primary view and hide
  // the interactive canvas + its controls (the canvas is unreliable at phone
  // width and a deep tree is hard to use on a phone), with an explicit opt-in to
  // load the interactive diagram anyway. The choice is remembered for the
  // session so the opt-in survives the reload.
  function enterOutlineMode(container) {
    showFallback();
    if (container) { container.style.display = 'none'; }
    var mini = document.getElementById('sbMinimap'); if (mini) { mini.hidden = true; }
    ['sb-toolbar', 'sb-entity-filter', 'sb-entity-count', 'sb-bread'].forEach(function (c) {
      var els = document.getElementsByClassName(c);
      for (var i = 0; i < els.length; i++) { els[i].style.display = 'none'; }
    });
    var note = document.createElement('div');
    note.className = 'alert alert-info d-flex flex-wrap align-items-center gap-2 small mb-2';
    var span = document.createElement('span');
    span.innerHTML = '<i class="bi bi-list-nested me-1"></i>{{ __('Showing the text outline, which works best on small screens.') }}';
    note.appendChild(span);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-primary';
    btn.textContent = '{{ __('Load interactive diagram anyway') }}';
    btn.addEventListener('click', function () {
      try { sessionStorage.setItem('sb-force-interactive', '1'); } catch (e) {}
      window.location.reload();
    });
    note.appendChild(btn);
    if (container && container.parentNode) { container.parentNode.insertBefore(note, container); }
  }

  // Replace the (potentially blank) canvas with a visible, diagnosable error
  // instead of silent white. Keeps the static outline below it.
  function showError(container, msg, err) {
    var detail = '';
    if (err) { detail = (err && err.stack) ? String(err.stack) : String(err && err.message ? err.message : err); }
    var box = document.createElement('div');
    box.className = 'sb-error';
    var p = document.createElement('div');
    p.innerHTML = '<strong><i class="bi bi-exclamation-triangle me-1"></i>' +
      '{{ __('The interactive diagram could not be drawn.') }}</strong> ' +
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
    var container = document.getElementById('sbCanvas');
    if (!container || typeof cytoscape === 'undefined') {
      // Library failed to load/parse - keep the static outline visible and say so.
      showError(container, '{{ __('Diagram library failed to load.') }}', null);
      return;
    }

    // ---- Small screens default to the text outline (reliable) over the canvas. ----
    var _sbForce = false;
    try {
      var _sbP = new URLSearchParams(window.location.search);
      _sbForce = _sbP.get('map') === 'interactive' || sessionStorage.getItem('sb-force-interactive') === '1';
    } catch (e) {}
    if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches && !_sbForce) {
      enterOutlineMode(container);
      return;
    }

    // ---- Guaranteed size: never hand Cytoscape a zero-sized container. ----
    function reliableWidth() {
      var w = container.clientWidth, p = container.parentElement;
      while ((!w || w < 50) && p) { w = p.clientWidth; p = p.parentElement; }
      if (!w || w < 50) { w = document.documentElement.clientWidth || window.innerWidth || 0; }
      return Math.round(w);
    }
    function ensureCanvasSize() {
      if (container.clientWidth < 50) {
        var w = reliableWidth();
        if (w >= 50) { container.style.width = w + 'px'; }
      }
      if (container.clientHeight < 50) {
        container.style.height = Math.max(Math.round(window.innerHeight * 0.7), 420) + 'px';
      }
    }
    ensureCanvasSize();

    var cy;

    try {

    // ----------------------------------------------------------------
    // Drill state: a DEPTH STACK of expanded ancestor ids. The LAST entry is
    // the currently-open node (the one whose direct children are shown). The
    // root id is always at the bottom of the stack, so:
    //
    //   stack = [root]                       -> show root + L2 entities
    //   stack = [root, entity]               -> show that entity + its L3 aspects
    //   stack = [root, entity, aspect]       -> show that aspect + its L4 features
    //
    // openNode() is the deepest entry; openDepth() its depth (0..2). The drill
    // reveals exactly one level at a time. (The longest path is root->L2->L3->L4,
    // i.e. three drill steps; an L4 feature is a leaf and is never "opened".)
    // ----------------------------------------------------------------
    var ROOT_ID = (function () {
      var r = GRAPH.filter(function (el) { return el.data && el.data.kind === 'root'; })[0];
      return r ? r.data.id : 'heratio';
    })();
    var stack = [ROOT_ID];
    function openNode() { return stack[stack.length - 1]; }

    // ---- hiddenEntities: L2 entity ids toggled OFF via the filter chips.
    //      A hidden entity (and its whole subtree) is removed from the visible
    //      set by applyVisibility(). Only meaningful at the root level. ----
    var hiddenEntities = Object.create(null);
    function isEntityHidden(id) { return !!hiddenEntities[id]; }

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
          // The root (L1) node gets a slightly larger, distinct presence.
          selector: 'node[kind="root"]',
          style: { 'font-size': '16px', 'padding': '18px' }
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
        { selector: '.sb-hidden', style: { 'display': 'none' } },
        // ---- In-map search highlight / dim ----
        {
          selector: 'node.sb-match',
          style: {
            'border-width': 5,
            'border-color': '#ffc107',
            'border-opacity': 1,
            'opacity': 1,
            'z-index': 9999
          }
        },
        { selector: '.sb-dim', style: { 'opacity': 0.18 } },
        { selector: 'edge.sb-dim', style: { 'opacity': 0.10 } }
      ]
    });

    // ----------------------------------------------------------------
    // Drill model (4 levels, pure show/hide via .sb-hidden - never compound
    // nesting). At any depth the visible set is exactly:
    //   - the open node (the deepest entry on the stack), PLUS
    //   - its DIRECT children (nodes whose parent is the open node), PLUS
    //   - the hierarchy edge from the open node to each visible child.
    // Everything else is hidden. When the root is open we additionally honour
    // the entity filter (hidden L2 entities and their connectors drop out).
    // ----------------------------------------------------------------
    function applyVisibility() {
      var open = openNode();
      cy.batch(function () {
        // Hide everything first, then reveal the open node + its visible
        // children + the connectors to them.
        cy.elements().addClass('sb-hidden');

        var openEl = cy.getElementById(open);
        if (openEl && openEl.nonempty()) { openEl.removeClass('sb-hidden'); }

        // Direct children of the open node. A child is hidden when it is an L2
        // entity that the filter has toggled off (only applies at root level).
        cy.nodes().forEach(function (n) {
          if (PARENT_OF[n.id()] !== open) { return; }
          if (n.data('kind') === 'l2' && isEntityHidden(n.id())) { return; }
          n.removeClass('sb-hidden');
        });

        // Hierarchy connectors from the open node to each visible child.
        cy.edges().forEach(function (e) {
          if (e.data('parentId') !== open) { return; }
          var tgt = cy.getElementById(e.data('target'));
          if (tgt && tgt.nonempty() && !tgt.hasClass('sb-hidden')) {
            e.removeClass('sb-hidden');
          }
        });
      });
    }

    function relayout(fit) {
      var open = openNode();
      var opts = { name: 'breadthfirst', directed: true, spacingFactor: 0.55, padding: 20, animate: false };
      // Force the open node to be the layout ROOT so it sits at the TOP with its
      // items beneath it (clean parent/child tree). Without this, breadthfirst
      // auto-picks a root and the parent floats off to one side. The tight
      // spacingFactor packs the items so the fit can zoom in (bigger, more
      // readable blocks).
      var r = cy.getElementById(open);
      if (r && r.nonempty()) { opts.roots = r; }
      var eles = cy.elements(':visible');
      eles.layout(opts).run();
      // Defer the fit so breadthfirst has actually applied the new positions.
      // Fitting inline frames the PRE-layout positions and zooms onto a single
      // node, leaving the rest off-screen - the "drill shows only one block"
      // bug. Fit NODES ONLY (a transient edge bbox can skew cy.elements right
      // after a layout). rAF + a short timeout covers both sync and deferred
      // position application.
      if (fit !== false) {
        var doFit = function () { try { cy.fit(cy.nodes(':visible'), 40); } catch (e) {} };
        requestAnimationFrame(doFit);
        setTimeout(doFit, 60);
      }
    }

    function renderBread() {
      var bread = document.getElementById('sbBread');
      if (!bread) return;
      bread.innerHTML = '';

      function sep() {
        var s = document.createElement('span');
        s.className = 'text-muted mx-2';
        s.textContent = '/';
        bread.appendChild(s);
      }
      // A crumb is a link when it is SHALLOWER than the current open node (click
      // to go back up to it); the deepest crumb (the open node) is plain text.
      function crumb(label, onClick) {
        if (onClick) {
          var a = document.createElement('a');
          a.className = 'crumb-link link-primary text-decoration-none';
          a.textContent = label;
          a.addEventListener('click', onClick);
          bread.appendChild(a);
        } else {
          var cur = document.createElement('span');
          cur.className = 'fw-semibold';
          cur.textContent = label;
          bread.appendChild(cur);
        }
      }
      function openHelpLink(node) {
        if (node && node.data('help')) {
          var a = document.createElement('a');
          a.className = 'ms-2 small';
          a.href = ARTICLE_BASE + node.data('help');
          a.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> {{ __('Open help') }}';
          bread.appendChild(a);
        }
      }

      // Walk the depth stack: every entry except the last is a clickable crumb
      // that drills BACK UP to that depth; the last entry is the open node.
      for (var i = 0; i < stack.length; i++) {
        if (i > 0) { sep(); }
        var node = cy.getElementById(stack[i]);
        var label = (node && node.nonempty() && node.data('label')) || stack[i];
        var isLast = (i === stack.length - 1);
        if (isLast) {
          crumb(label, null);
          openHelpLink(node);
        } else {
          (function (depth) {
            crumb(label, function () { drillToDepth(depth); });
          })(i);
        }
      }
    }

    // Shared post-drill refresh: re-run visibility, re-layout + re-fit, redraw
    // the breadcrumb, the entity counter, and the minimap. Every state change
    // funnels through here for one code path.
    function afterDrill() {
      applyVisibility();
      relayout(true);
      renderBread();
      refreshEntityCount();
      scheduleMinimap();
    }

    // Drill INTO a node: push it onto the stack so its children become visible.
    // Only nodes that actually have children can be opened; a leaf (or a node
    // already open) instead opens its help article if it has one.
    function drillInto(nodeId) {
      if (!nodeId) return;
      var n = cy.getElementById(nodeId);
      if (n.empty()) return;
      if (n.id() === openNode()) {
        // already the open node -> open its help article if present
        if (n.data('help')) { window.location.href = ARTICLE_BASE + n.data('help'); }
        return;
      }
      if (!n.data('hasChildren')) {
        // leaf -> open its help article directly if present
        if (n.data('help')) { window.location.href = ARTICLE_BASE + n.data('help'); }
        return;
      }
      // Only a DIRECT child of the currently-open node can be drilled into.
      if (PARENT_OF[nodeId] !== openNode()) { return; }
      stack.push(nodeId);
      afterDrill();
    }

    // Jump straight to a given depth on the stack (drop everything below it).
    function drillToDepth(depth) {
      if (depth < 0) { depth = 0; }
      if (depth >= stack.length - 1) { return; }
      stack = stack.slice(0, depth + 1);
      afterDrill();
    }

    // Jump straight back to the whole-system (root) view.
    function drillToRoot() {
      stack = [ROOT_ID];
      afterDrill();
    }

    // One step up the tree: pop the deepest entry. At the root, just re-fit.
    function drillUp() {
      if (stack.length > 1) { stack.pop(); afterDrill(); return; }
      cy.fit(cy.elements(':visible'), 40);
    }

    // ---- node click: drill-in if it has children, else deep-link at the leaf ----
    cy.on('tap', 'node', function (evt) {
      var n = evt.target;
      if (n.id() === openNode()) {
        // tapping the open node opens its help article (avoids a dead-end)
        if (n.data('help')) { window.location.href = ARTICLE_BASE + n.data('help'); }
        return;
      }
      if (n.data('hasChildren')) {
        drillInto(n.id());
      } else if (n.data('help')) {
        // leaf (L4 feature, or any childless node) -> open its help article
        window.location.href = ARTICLE_BASE + n.data('help');
      }
    });

    // ---- toolbar buttons ----
    function zoomBy(factor) {
      cy.zoom({ level: cy.zoom() * factor, renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 } });
    }
    document.getElementById('sbZoomIn').addEventListener('click', function () { zoomBy(1.25); });
    document.getElementById('sbZoomOut').addEventListener('click', function () { zoomBy(0.8); });
    document.getElementById('sbFit').addEventListener('click', function () { drillUp(); });
    document.getElementById('sbUp').addEventListener('click', function () { drillUp(); });

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
          if (sel.nonempty()) {
            var sn = sel[0];
            if (sn.data('hasChildren') && sn.id() !== openNode()) { drillInto(sn.id()); }
            else if (sn.data('help')) { window.location.href = ARTICLE_BASE + sn.data('help'); }
          }
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
      if (n.data('hasChildren') || n.data('help')) { container.style.cursor = 'pointer'; }
    });
    cy.on('mouseout', 'node', function () { container.style.cursor = 'default'; });

    // ================================================================
    // Navigation aids: (1) in-map search, (2) entity filter, (3) minimap.
    // ================================================================

    // ---------- (2) Entity filter: count indicator ----------
    function totalEntities() { return cy.nodes('[kind="l2"]').length; }
    function shownEntities() {
      var total = totalEntities(), hidden = 0, i;
      var ids = cy.nodes('[kind="l2"]').map(function (n) { return n.id(); });
      for (i = 0; i < ids.length; i++) { if (isEntityHidden(ids[i])) { hidden++; } }
      return total - hidden;
    }
    function refreshEntityCount() {
      var el = document.getElementById('sbEntityCount');
      if (!el) return;
      var shown = shownEntities(), total = totalEntities();
      el.textContent = shown + ' {{ __('of') }} ' + total + ' {{ __('record types shown') }}';
    }

    // ---------- (2) Entity filter: chip toggles ----------
    function syncChipUI() {
      var chips = document.querySelectorAll('#sbEntityFilter .sb-chip[data-entity]');
      chips.forEach(function (chip) {
        var id = chip.getAttribute('data-entity');
        var box = chip.querySelector('input.sb-entity-toggle');
        var off = isEntityHidden(id);
        if (box) { box.checked = !off; }
        chip.classList.toggle('sb-chip-off', off);
      });
    }
    function applyEntityFilter(refit) {
      // If the open node (or any ancestor on the stack) belongs to a now-hidden
      // entity, collapse back up to a still-visible depth. The entity is the
      // stack entry at index 1 (root is index 0); if it is hidden, drop to root.
      if (stack.length > 1 && isEntityHidden(stack[1])) { stack = [ROOT_ID]; }
      applyVisibility();
      relayout(refit !== false);
      renderBread();
      syncChipUI();
      refreshEntityCount();
      scheduleMinimap();
    }
    (function wireEntityFilter() {
      var boxes = document.querySelectorAll('#sbEntityFilter input.sb-entity-toggle');
      boxes.forEach(function (box) {
        box.addEventListener('change', function () {
          if (box.checked) { delete hiddenEntities[box.value]; }
          else { hiddenEntities[box.value] = true; }
          applyEntityFilter(true);
        });
      });
      var allBtn = document.getElementById('sbEntitiesAll');
      if (allBtn) {
        allBtn.addEventListener('click', function () {
          hiddenEntities = Object.create(null);
          applyEntityFilter(true);
        });
      }
      var resetBtn = document.getElementById('sbEntitiesReset');
      if (resetBtn) {
        resetBtn.addEventListener('click', function () {
          hiddenEntities = Object.create(null);
          stack = [ROOT_ID];
          clearSearch();
          applyEntityFilter(true);
        });
      }
    })();

    // ---------- (1) In-map search ----------
    var searchInput = document.getElementById('sbSearch');
    var searchClear = document.getElementById('sbSearchClear');
    var _searchT = null;

    function clearSearchClasses() {
      cy.batch(function () {
        cy.elements().removeClass('sb-match sb-dim');
      });
    }
    function clearSearch() {
      if (searchInput) { searchInput.value = ''; }
      if (searchClear) { searchClear.hidden = true; }
      clearSearchClasses();
    }
    function runSearch(raw) {
      var q = (raw || '').trim().toLowerCase();
      if (searchClear) { searchClear.hidden = (q === ''); }
      if (q === '') { clearSearchClasses(); return; }

      var visible = cy.nodes(':visible');
      var matches = visible.filter(function (n) {
        var label = String(n.data('label') || '').toLowerCase();
        return label.indexOf(q) !== -1;
      });

      cy.batch(function () {
        cy.elements(':visible').addClass('sb-dim');
        matches.removeClass('sb-dim').addClass('sb-match');
        matches.edgesWith(matches).removeClass('sb-dim');
      });

      if (matches.length === 1) {
        try { cy.animate({ center: { eles: matches }, duration: 250 }); } catch (e) {}
      }
    }
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        if (_searchT) { clearTimeout(_searchT); }
        var val = searchInput.value;
        _searchT = setTimeout(function () { runSearch(val); scheduleMinimap(); }, 180); // debounce
      });
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { clearSearch(); e.stopPropagation(); }
      });
    }
    if (searchClear) {
      searchClear.addEventListener('click', function () { clearSearch(); if (searchInput) { searchInput.focus(); } });
    }

    // ---------- (3) Minimap overview ----------
    var miniEl = document.getElementById('sbMinimap');
    var miniCanvas = null, miniCtx = null, _miniT = null, _miniBB = null;
    function ensureMiniCanvas() {
      if (!miniEl) return false;
      if (!miniCanvas) {
        miniCanvas = document.createElement('canvas');
        miniEl.appendChild(miniCanvas);
      }
      var w = miniEl.clientWidth, h = miniEl.clientHeight;
      if (w <= 0 || h <= 0) return false;
      var dpr = window.devicePixelRatio || 1;
      if (miniCanvas.width !== Math.round(w * dpr) || miniCanvas.height !== Math.round(h * dpr)) {
        miniCanvas.width = Math.round(w * dpr);
        miniCanvas.height = Math.round(h * dpr);
        miniCanvas.style.width = w + 'px';
        miniCanvas.style.height = h + 'px';
      }
      miniCtx = miniCanvas.getContext('2d');
      if (miniCtx) { miniCtx.setTransform(dpr, 0, 0, dpr, 0, 0); }
      return !!miniCtx;
    }
    function miniTransform(w, h) {
      var bb = _miniBB;
      if (!bb || bb.w <= 0 || bb.h <= 0) { return null; }
      var pad = 6;
      var s = Math.min((w - pad * 2) / bb.w, (h - pad * 2) / bb.h);
      var offX = (w - bb.w * s) / 2;
      var offY = (h - bb.h * s) / 2;
      return {
        s: s,
        x: function (mx) { return offX + (mx - bb.x1) * s; },
        y: function (my) { return offY + (my - bb.y1) * s; }
      };
    }
    function drawMinimap() {
      if (!miniEl || miniEl.hidden) return;
      if (!ensureMiniCanvas()) return;
      var w = miniEl.clientWidth, h = miniEl.clientHeight;
      var vis = cy.elements(':visible');
      var bb = vis.boundingBox();
      _miniBB = { x1: bb.x1, y1: bb.y1, w: bb.w, h: bb.h };
      var t = miniTransform(w, h);
      miniCtx.clearRect(0, 0, w, h);
      if (!t) { return; }

      miniCtx.strokeStyle = 'rgba(108,117,125,.45)';
      miniCtx.lineWidth = 1;
      vis.filter('edge').forEach(function (e) {
        var s = e.source().position(), tg = e.target().position();
        miniCtx.beginPath();
        miniCtx.moveTo(t.x(s.x), t.y(s.y));
        miniCtx.lineTo(t.x(tg.x), t.y(tg.y));
        miniCtx.stroke();
      });
      vis.filter('node').forEach(function (n) {
        if (n.isParent()) { return; }
        var p = n.position();
        miniCtx.fillStyle = n.data('color') || '#264653';
        miniCtx.beginPath();
        miniCtx.arc(t.x(p.x), t.y(p.y), 2.4, 0, Math.PI * 2);
        miniCtx.fill();
      });

      var ext = cy.extent();
      var rx = t.x(ext.x1), ry = t.y(ext.y1);
      var rw = (ext.x2 - ext.x1) * t.s, rh = (ext.y2 - ext.y1) * t.s;
      miniCtx.strokeStyle = '#0d6efd';
      miniCtx.lineWidth = 1.5;
      miniCtx.strokeRect(
        Math.max(0, rx), Math.max(0, ry),
        Math.min(rw, w), Math.min(rh, h)
      );
      miniCtx.fillStyle = 'rgba(13,110,253,.10)';
      miniCtx.fillRect(Math.max(0, rx), Math.max(0, ry), Math.min(rw, w), Math.min(rh, h));
    }
    function scheduleMinimap() {
      if (_miniT) { return; }
      _miniT = requestAnimationFrame(function () { _miniT = null; try { drawMinimap(); } catch (e) {} });
    }
    function showMinimap() {
      if (miniEl) { miniEl.hidden = false; scheduleMinimap(); }
    }
    if (miniEl) {
      miniEl.addEventListener('click', function (ev) {
        if (!ensureMiniCanvas()) return;
        var rect = miniEl.getBoundingClientRect();
        var px = ev.clientX - rect.left, py = ev.clientY - rect.top;
        var w = miniEl.clientWidth, h = miniEl.clientHeight;
        var t = miniTransform(w, h);
        if (!t || !_miniBB) return;
        var s = t.s;
        var offX = (w - _miniBB.w * s) / 2;
        var offY = (h - _miniBB.h * s) / 2;
        var mx = _miniBB.x1 + (px - offX) / s;
        var my = _miniBB.y1 + (py - offY) / s;
        var z = cy.zoom();
        var targetPan = { x: container.clientWidth / 2 - mx * z, y: container.clientHeight / 2 - my * z };
        try { cy.animate({ pan: targetPan, duration: 200 }); }
        catch (e) { cy.pan(targetPan); }
      });
    }
    cy.on('pan zoom render', scheduleMinimap);

    // ---- initial render ----
    applyVisibility();
    relayout(true);
    renderBread();
    syncChipUI();
    refreshEntityCount();
    showMinimap();

    // The interactive canvas painted successfully - hide the static outline.
    hideFallback();

    // ---- keep the canvas sized to its container (mobile blank-map fix). ----
    function refit() { try { ensureCanvasSize(); cy.resize(); cy.fit(cy.elements(':visible'), 40); } catch (e) {} }
    requestAnimationFrame(refit);
    setTimeout(refit, 250);
    setTimeout(refit, 800);
    var _sbRt;
    window.addEventListener('resize', function () { clearTimeout(_sbRt); _sbRt = setTimeout(refit, 150); });
    window.addEventListener('orientationchange', function () { setTimeout(refit, 300); });

    if (typeof ResizeObserver !== 'undefined') {
      var _lastW = 0, _lastH = 0, _roT;
      var ro = new ResizeObserver(function (entries) {
        var box = entries[0] && entries[0].contentRect ? entries[0].contentRect
                : { width: container.clientWidth, height: container.clientHeight };
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
      try { if (cy && cy.destroy) { cy.destroy(); } } catch (e2) {}
      showError(container, '{{ __('The interactive diagram hit an error while drawing.') }}', err);
      if (window.console && console.error) { console.error('[system-breakdown] init failed:', err); }
    }
  });
})();
</script>
@endpush
