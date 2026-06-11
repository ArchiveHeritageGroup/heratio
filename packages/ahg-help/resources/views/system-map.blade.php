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

  /* ----- Navigation aids (search, stage filter, minimap) ----- */
  .system-map-search { position: relative; }
  .system-map-search .form-control-sm { min-width: 9rem; }
  .system-map-search .sm-search-clear {
    position: absolute; right: .25rem; top: 50%; transform: translateY(-50%);
    border: 0; background: transparent; color: #6c757d; line-height: 1;
    padding: 0 .35rem; cursor: pointer;
  }
  /* Stage filter chips - wrap freely below the toolbar on narrow screens. */
  .system-map-stage-filter { gap: .35rem .4rem; flex-wrap: wrap; }
  .system-map-stage-filter .sm-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    border: 1px solid #ced4da; background: #fff; color: #212529;
    border-radius: 999px; padding: .15rem .6rem; font-size: .8rem;
    cursor: pointer; user-select: none; line-height: 1.4;
  }
  .system-map-stage-filter .sm-chip:hover { background: #f1f3f5; }
  .system-map-stage-filter .sm-chip.sm-chip-off { opacity: .5; }
  .system-map-stage-filter .sm-chip.sm-chip-off .sm-chip-dot { filter: grayscale(1); }
  .system-map-stage-filter .sm-chip-dot {
    display: inline-block; width: .7rem; height: .7rem;
    border-radius: 50%; flex: 0 0 auto;
  }
  .system-map-stage-filter .sm-chip input { display: none; }
  .system-map-stage-count { font-size: .78rem; }

  /* Minimap overview panel - absolutely positioned over the canvas,
     bottom-right, never overlapping toolbar/fallback. Click-to-recenter;
     the host canvas keeps full pan/zoom. */
  #systemMapMinimap {
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
  #systemMapMinimap canvas { display: block; width: 100%; height: 100%; }
  #systemMapMinimap .sm-mini-label {
    position: absolute; top: 2px; left: 5px;
    font-size: .62rem; color: #6c757d; pointer-events: none;
    text-transform: uppercase; letter-spacing: .03em;
  }
  /* On very small screens the minimap eats too much of the canvas - shrink it. */
  @media (max-width: 575.98px) {
    #systemMapMinimap { width: 116px; height: 84px; right: .4rem; bottom: .4rem; }
  }

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
@php
  // Reconstruct the stage -> child -> grandchild tree from the flat Cytoscape
  // element list (the controller passes only 'elements' + 'bands'). Pure
  // derivation, no controller change needed - feeds BOTH the stage-filter chips
  // and the server-rendered static fallback (now 3 levels deep) below.
  $smStages = [];
  $smChildren = [];
  $smGrand = [];
  foreach ($graph['elements'] ?? [] as $el) {
      $d = $el['data'] ?? [];
      if (($d['kind'] ?? null) === 'stage') {
          $smStages[$d['id']] = $d;
      } elseif (($d['kind'] ?? null) === 'child') {
          $smChildren[$d['subId'] ?? ''][] = $d;
      } elseif (($d['kind'] ?? null) === 'grandchild') {
          $smGrand[$d['subId'] ?? ''][] = $d;
      }
  }
@endphp
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
      @if(\Illuminate\Support\Facades\Route::has('help.system-breakdown'))
        <a href="{{ route('help.system-breakdown') }}" class="btn btn-sm atom-btn-white align-self-start">
          <i class="bi bi-grid-3x3-gap-fill me-1"></i>{{ __('System breakdown') }}
        </a>
      @endif
    </div>

    {{-- Toolbar --}}
    <div class="d-flex align-items-center system-map-toolbar mb-2">
      <button type="button" id="smZoomIn"  class="btn btn-sm atom-btn-white" title="{{ __('Zoom in') }}"><i class="bi bi-zoom-in"></i></button>
      <button type="button" id="smZoomOut" class="btn btn-sm atom-btn-white" title="{{ __('Zoom out') }}"><i class="bi bi-zoom-out"></i></button>
      <button type="button" id="smFit"     class="btn btn-sm atom-btn-white" title="{{ __('Reset / fit to screen') }}"><i class="bi bi-arrows-fullscreen me-1"></i>{{ __('Fit') }}</button>
      <button type="button" id="smUp"      class="btn btn-sm atom-btn-white" title="{{ __('Collapse / back up') }}"><i class="bi bi-arrow-up-left-circle me-1"></i>{{ __('Up') }}</button>
      <span class="vr mx-1 d-none d-md-inline"></span>
      {{-- In-map search: type to highlight matching nodes and dim the rest. --}}
      <div class="system-map-search">
        <label for="smSearch" class="visually-hidden">{{ __('Search the map') }}</label>
        <input type="search" id="smSearch" class="form-control form-control-sm"
               autocomplete="off" placeholder="{{ __('Search nodes…') }}"
               aria-label="{{ __('Search the map by node label') }}" style="padding-right: 1.6rem;">
        <button type="button" id="smSearchClear" class="sm-search-clear" hidden
                title="{{ __('Clear search') }}" aria-label="{{ __('Clear search') }}"><i class="bi bi-x-circle"></i></button>
      </div>
      <span class="vr mx-1 d-none d-md-inline"></span>
      <span class="text-muted small align-self-center d-none d-md-inline">
        {{ __('Drag to pan - wheel to zoom - arrow keys to pan - click a stage to drill in') }}
      </span>
    </div>

    {{-- Stage filter: one colour-dotted chip per top-level stage. Toggling a
         chip hides/shows that stage's subtree and re-fits the visible graph. --}}
    <div class="d-flex align-items-center system-map-stage-filter mb-2" id="smStageFilter">
      <span class="text-muted small me-1">{{ __('Stages:') }}</span>
      <button type="button" id="smStagesAll"   class="sm-chip" title="{{ __('Show all stages') }}">
        <i class="bi bi-check2-all"></i>{{ __('All') }}
      </button>
      <button type="button" id="smStagesReset" class="sm-chip" title="{{ __('Reset filter and view') }}">
        <i class="bi bi-arrow-counterclockwise"></i>{{ __('Reset') }}
      </button>
      @foreach($smStages as $sid => $stage)
        <label class="sm-chip" data-stage="{{ $sid }}" title="{{ $stage['label'] }}">
          <input type="checkbox" class="sm-stage-toggle" value="{{ $sid }}" checked>
          <span class="sm-chip-dot" style="background: {{ $stage['color'] ?? '#264653' }};"></span>
          <span class="sm-chip-text">{{ $stage['label'] }}</span>
        </label>
      @endforeach
      <span class="text-muted system-map-stage-count ms-2" id="smStageCount" aria-live="polite"></span>
    </div>

    {{-- Breadcrumb of where you are inside the map --}}
    <div class="system-map-bread mb-2" id="smBread"></div>

    <div id="systemMapShell">
      <div id="systemMapCanvas" tabindex="0" role="application"
           aria-label="{{ __('Interactive system flow map. Use arrow keys to pan, plus and minus to zoom, Enter to drill into the focused stage, and Escape to go up.') }}"></div>
      {{-- Minimap overview: a small whole-graph render with a viewport rectangle,
           absolutely positioned over the canvas (bottom-right). Built by the init
           script ONLY after the main canvas paints; hidden if Cytoscape fails. --}}
      <div id="systemMapMinimap" hidden role="img"
           aria-label="{{ __('Overview minimap of the whole system map') }}" title="{{ __('Overview - click to recenter') }}">
        <span class="sm-mini-label">{{ __('Overview') }}</span>
      </div>
    </div>

    {{--
      Static fallback outline. Rendered server-side from the SAME data as the
      interactive map so the page is NEVER just blank white. It is visible by
      default (covers JS-disabled + lib-failed-to-parse cases); the init script
      hides it the moment Cytoscape paints successfully. On a caught init/layout
      error the script re-shows it (and shows the error text above), so a failure
      is always diagnosable instead of a silent white box.
    --}}
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
                    @if(!empty($smGrand[$child['id'] ?? '']))
                      <ul>
                        @foreach($smGrand[$child['id']] as $grand)
                          <li>
                            @if(!empty($grand['help']))
                              <a href="{{ route('help.article', $grand['help']) }}" class="text-decoration-none">{{ $grand['label'] }}</a>
                            @else
                              {{ $grand['label'] }}
                            @endif
                            @if(!empty($grand['sub']))
                              <span class="sm-sub ms-1">- {{ $grand['sub'] }}</span>
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

  // Each stage's children declare `parent: <stageId>`, which makes Cytoscape
  // treat the stage as a COMPOUND PARENT node. The :parent style then paints it
  // at background-opacity 0.10 (90% transparent) and auto-sizes it to its
  // (hidden) children, so collapsed stages render nearly invisible - the map
  // looked blank on every device even though the nodes were present and
  // clickable (and the minimap, which draws from data.color, showed them fine).
  // The drill model is pure show/hide and never relies on compound nesting, so
  // strip `parent` to render every stage as a solid, visible node.
  GRAPH.forEach(function (el) { if (el && el.data && el.data.parent != null) { delete el.data.parent; } });
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

  // Small-screen mode: present the static outline as the primary view and hide
  // the interactive canvas + its controls (the canvas is unreliable at phone
  // width and a 70-node graph is hard to use on a phone), with an explicit
  // opt-in to load the interactive map anyway. The choice is remembered for the
  // session so the opt-in survives the reload.
  function enterOutlineMode(container) {
    showFallback();
    if (container) { container.style.display = 'none'; }
    var mini = document.getElementById('systemMapMinimap'); if (mini) { mini.hidden = true; }
    ['system-map-toolbar', 'system-map-stage-filter', 'system-map-stage-count', 'system-map-bread'].forEach(function (c) {
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
    btn.textContent = '{{ __('Load interactive map anyway') }}';
    btn.addEventListener('click', function () {
      try { sessionStorage.setItem('sm-force-interactive', '1'); } catch (e) {}
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

    // ---- Small screens default to the text outline (reliable) over the canvas. ----
    var _smForce = false;
    try {
      var _smP = new URLSearchParams(window.location.search);
      _smForce = _smP.get('map') === 'interactive' || sessionStorage.getItem('sm-force-interactive') === '1';
    } catch (e) {}
    if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches && !_smForce) {
      enterOutlineMode(container);
      return;
    }

    // ---- Guaranteed size: never hand Cytoscape a zero-sized container. ----
    // If CSS hasn't given the box a real height/width yet, force an explicit
    // pixel size so the very first measure is non-zero. This is the core
    // mobile fix - a 0px container paints blank and never recovers without a
    // resize event we can't guarantee.
    // A width:100% canvas inside any shrink-to-fit ancestor can compute to 0 on
    // mobile, painting the main canvas blank while the fixed-size minimap still
    // renders (the "white map + Overview block" symptom). width:'100%' cannot fix
    // a 0-width PARENT, so resolve an explicit pixel width from the first ancestor
    // that actually has one, falling back to the viewport, and re-assert it before
    // every fit.
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

    // ---- Drill state: two levels of "where am I". ----
    //   expandedStage = which top-level stage (if any) is drilled into.
    //   expandedChild = which child (sub-area) inside that stage is drilled
    //                   into, revealing its grandchild detail nodes.
    // expandedChild is only ever set while expandedStage is also set.
    var expandedStage = null;
    var expandedChild = null;

    // ---- hiddenStages: stage ids toggled OFF via the stage-filter chips.
    //      A hidden stage (and its whole subtree) is removed from the visible
    //      set by applyVisibility(), exactly like the drill model does. ----
    var hiddenStages = Object.create(null);
    function isStageHidden(id) { return !!hiddenStages[id]; }

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
        { selector: '.sm-hidden', style: { 'display': 'none' } },
        // ---- In-map search highlight / dim ----
        // Matching nodes stay fully opaque and gain a bright halo; everything
        // else (nodes + edges) is dimmed so the matches pop. Classes are added
        // / removed by the search handler below.
        {
          selector: 'node.sm-match',
          style: {
            'border-width': 5,
            'border-color': '#ffc107',
            'border-opacity': 1,
            'opacity': 1,
            'z-index': 9999
          }
        },
        { selector: '.sm-dim', style: { 'opacity': 0.18 } },
        { selector: 'edge.sm-dim', style: { 'opacity': 0.10 } }
      ]
    });

    // ----------------------------------------------------------------
    // Drill model (3 levels, pure show/hide via .sm-hidden - never compound
    // nesting). The visible set depends on (expandedStage, expandedChild):
    //
    //   (null, null)          -> only `stage` nodes + `stage-edge`s.
    //   (stage, null)         -> that stage + its `child` nodes + `child-edge`s;
    //                            all grandchildren hidden.
    //   (stage, child)        -> that child + its `grandchild` nodes +
    //                            `grandchild-edge`s; sibling children's
    //                            grandchildren hidden; the parent child stays
    //                            visible (it's the drilled-into node).
    // ----------------------------------------------------------------
    function applyVisibility() {
      cy.batch(function () {
        cy.elements().removeClass('sm-hidden');

        // ---- Level 2: child sub-flow nodes. Visible only when their stage is
        //      the expanded one (and that stage isn't filtered off). ----
        cy.nodes('[kind="child"]').forEach(function (n) {
          if (expandedStage && n.data('subId') === expandedStage && !isStageHidden(n.data('subId'))) {
            n.removeClass('sm-hidden');
          } else {
            n.addClass('sm-hidden');
          }
        });
        cy.edges('[kind="child-edge"]').forEach(function (e) {
          if (expandedStage && e.data('subId') === expandedStage && !isStageHidden(e.data('subId'))) {
            e.removeClass('sm-hidden');
          } else {
            e.addClass('sm-hidden');
          }
        });

        // ---- Level 3: grandchild detail nodes. Visible only when their parent
        //      child is the expanded one. ----
        cy.nodes('[kind="grandchild"]').forEach(function (n) {
          if (expandedChild && n.data('subId') === expandedChild && !isStageHidden(n.data('stageId'))) {
            n.removeClass('sm-hidden');
          } else {
            n.addClass('sm-hidden');
          }
        });
        cy.edges('[kind="grandchild-edge"]').forEach(function (e) {
          if (expandedChild && e.data('subId') === expandedChild && !isStageHidden(e.data('stageId'))) {
            e.removeClass('sm-hidden');
          } else {
            e.addClass('sm-hidden');
          }
        });

        // ---- Hierarchy edges: parent -> child connectors that root the drill
        //      layout so the open stage/child sits ABOVE its items. A stage's
        //      down-edges show while it is open (all at level 2; only the edge
        //      to the open child once a child is drilled, so nothing dangles).
        cy.edges('[kind="down-stage"]').forEach(function (e) {
          var show = expandedStage && e.data('subId') === expandedStage && !isStageHidden(e.data('subId'))
            && (!expandedChild || e.data('target') === expandedChild);
          if (show) { e.removeClass('sm-hidden'); } else { e.addClass('sm-hidden'); }
        });
        cy.edges('[kind="down-child"]').forEach(function (e) {
          if (expandedChild && e.data('subId') === expandedChild && !isStageHidden(e.data('stageId'))) {
            e.removeClass('sm-hidden');
          } else {
            e.addClass('sm-hidden');
          }
        });

        // When a child is drilled into, hide its SIBLING children (and their
        // child-edges) so only the open child + its grandchildren remain. The
        // open child itself is re-shown just below.
        if (expandedChild) {
          cy.nodes('[kind="child"]').forEach(function (n) {
            if (n.id() !== expandedChild) { n.addClass('sm-hidden'); }
          });
          cy.edges('[kind="child-edge"]').addClass('sm-hidden');
          var openChild = cy.getElementById(expandedChild);
          if (openChild && openChild.nonempty()) { openChild.removeClass('sm-hidden'); }
        }

        // Stage-filter: stages toggled OFF are removed from the visible set,
        // along with any stage-edge that touches them (so no dangling arrows).
        cy.nodes('[kind="stage"]').forEach(function (n) {
          if (isStageHidden(n.id())) { n.addClass('sm-hidden'); }
        });
        cy.edges('[kind="stage-edge"]').forEach(function (e) {
          if (isStageHidden(e.data('source')) || isStageHidden(e.data('target'))) {
            e.addClass('sm-hidden');
          }
        });

        // When focused on one stage, hide the sibling stages + spine edges
        // (at any sub-depth).
        if (expandedStage) {
          cy.nodes('[kind="stage"]').forEach(function (n) {
            if (n.id() !== expandedStage) { n.addClass('sm-hidden'); }
          });
          cy.edges('[kind="stage-edge"]').addClass('sm-hidden');
        }
      });
    }

    function relayout(fit) {
      var opts, root = null;
      if (expandedChild) {
        root = expandedChild;
        opts = { name: 'breadthfirst', directed: true, spacingFactor: 0.55, padding: 20, animate: false };
      } else if (expandedStage) {
        root = expandedStage;
        opts = { name: 'breadthfirst', directed: true, spacingFactor: 0.55, padding: 20, animate: false };
      } else {
        opts = { name: 'breadthfirst', directed: true, spacingFactor: 1.4, padding: 40, animate: false };
      }
      // Force the open parent to be the layout ROOT so it sits at the TOP with
      // its items beneath it (clean parent/child tree). Without this, breadthfirst
      // auto-picks a root from the flow edges and the parent floats off to one
      // side. The tighter spacingFactor packs the items so the fit can zoom in
      // (bigger, more readable blocks).
      if (root) { var r = cy.getElementById(root); if (r && r.nonempty()) { opts.roots = r; } }
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
      var bread = document.getElementById('smBread');
      if (!bread) return;
      bread.innerHTML = '';

      function sep() {
        var s = document.createElement('span');
        s.className = 'text-muted mx-2';
        s.textContent = '/';
        bread.appendChild(s);
      }
      // A crumb is a link when it targets a SHALLOWER level than the current
      // one (click to go back up to it); the deepest crumb is plain text.
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

      // Level 0: Whole system. Clickable unless already at the root.
      crumb('{{ __('Whole system') }}', (expandedStage || expandedChild) ? function () { drillToRoot(); } : null);

      // Level 1: the expanded stage.
      if (expandedStage) {
        sep();
        var stageNode = cy.getElementById(expandedStage);
        var stageLabel = (stageNode && stageNode.nonempty() && stageNode.data('label')) || expandedStage;
        // Clickable (back to stage view) only when we are deeper than it.
        crumb(stageLabel, expandedChild ? function () { drillToStage(); } : null);
        if (!expandedChild) { openHelpLink(stageNode); }
      }

      // Level 2: the expanded child (deepest - always plain text + help link).
      if (expandedChild) {
        sep();
        var childNode = cy.getElementById(expandedChild);
        var childLabel = (childNode && childNode.nonempty() && childNode.data('label')) || expandedChild;
        crumb(childLabel, null);
        openHelpLink(childNode);
      }
    }

    // Shared post-drill refresh: re-run visibility, re-layout + re-fit, redraw
    // the breadcrumb, the stage counter, and the minimap. Every state change
    // (tap, up, breadcrumb, escape) funnels through here for one code path.
    function afterDrill() {
      applyVisibility();
      relayout(true);
      renderBread();
      refreshStageCount();
      scheduleMinimap();
    }

    // Level 1: drill from the whole-system view INTO a stage.
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
      expandedChild = null;
      afterDrill();
    }

    // Level 2: drill from a stage view INTO one of its children.
    function drillIntoChild(childId) {
      if (!childId) return;
      var n = cy.getElementById(childId);
      if (n.empty() || n.data('kind') !== 'child') return;
      if (!n.data('hasChildren')) {
        // leaf child (no detail level) -> open its help article if present
        if (n.data('help')) { window.location.href = ARTICLE_BASE + n.data('help'); }
        return;
      }
      // A child only opens while its own stage is the expanded one.
      expandedStage = n.data('subId');
      expandedChild = childId;
      afterDrill();
    }

    // Jump straight back to the stage view (drop the child level, keep stage).
    function drillToStage() {
      if (!expandedChild) { return; }
      expandedChild = null;
      afterDrill();
    }

    // Jump straight back to the whole-system view.
    function drillToRoot() {
      expandedStage = null;
      expandedChild = null;
      afterDrill();
    }

    // One step up: child view -> stage view -> whole system.
    function drillUp() {
      if (expandedChild) { drillToStage(); return; }
      if (expandedStage) { drillToRoot(); return; }
      cy.fit(cy.elements(':visible'), 40);
    }

    // ---- node click: drill-in by level, deep-link at the leaves ----
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
        // A child with its own detail level drills in; if it's already the
        // expanded child (or has no detail level), tap opens its help article.
        if (expandedChild === n.id()) {
          if (n.data('help')) { window.location.href = ARTICLE_BASE + n.data('help'); }
        } else if (n.data('hasChildren')) {
          drillIntoChild(n.id());
        } else if (n.data('help')) {
          window.location.href = ARTICLE_BASE + n.data('help');
        }
      } else if (kind === 'grandchild') {
        // Leaf detail node: open its help article if it has one.
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
          if (sel.nonempty()) {
            var sn = sel[0], sk = sn.data('kind');
            if (sk === 'stage') { drillInto(sn.id()); }
            else if (sk === 'child') {
              if (sn.data('hasChildren')) { drillIntoChild(sn.id()); }
              else if (sn.data('help')) { window.location.href = ARTICLE_BASE + sn.data('help'); }
            } else if (sk === 'grandchild' && sn.data('help')) {
              window.location.href = ARTICLE_BASE + sn.data('help');
            }
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
      if (n.data('kind') === 'stage' || n.data('hasChildren') || n.data('help')) { container.style.cursor = 'pointer'; }
    });
    cy.on('mouseout', 'node', function () { container.style.cursor = 'default'; });

    // ================================================================
    // Navigation aids: (1) in-map search, (2) stage filter, (3) minimap.
    // All client-side, layered on top of the drill/visibility model above.
    // ================================================================

    // ---------- (2) Stage filter: count indicator ----------
    function totalStages() { return cy.nodes('[kind="stage"]').length; }
    function shownStages() {
      var total = totalStages(), hidden = 0, i;
      var ids = cy.nodes('[kind="stage"]').map(function (n) { return n.id(); });
      for (i = 0; i < ids.length; i++) { if (isStageHidden(ids[i])) { hidden++; } }
      return total - hidden;
    }
    function refreshStageCount() {
      var el = document.getElementById('smStageCount');
      if (!el) return;
      var shown = shownStages(), total = totalStages();
      // "X of Y stages shown" - always visible context indicator.
      el.textContent = shown + ' {{ __('of') }} ' + total + ' {{ __('stages shown') }}';
    }

    // ---------- (2) Stage filter: chip toggles ----------
    // Toggling a chip flips hiddenStages[id], then re-runs the SAME visibility
    // + relayout + re-fit path the drill model uses, so the visible graph
    // re-lays-out and re-fits cleanly with the filtered set.
    function syncChipUI() {
      var chips = document.querySelectorAll('#smStageFilter .sm-chip[data-stage]');
      chips.forEach(function (chip) {
        var id = chip.getAttribute('data-stage');
        var box = chip.querySelector('input.sm-stage-toggle');
        var off = isStageHidden(id);
        if (box) { box.checked = !off; }
        chip.classList.toggle('sm-chip-off', off);
      });
    }
    function applyStageFilter(refit) {
      // If the currently-expanded stage was just hidden, collapse back up
      // (dropping any drilled-in child with it).
      if (expandedStage && isStageHidden(expandedStage)) { expandedStage = null; expandedChild = null; }
      applyVisibility();
      relayout(refit !== false);
      renderBread();
      syncChipUI();
      refreshStageCount();
      scheduleMinimap();
    }
    (function wireStageFilter() {
      var boxes = document.querySelectorAll('#smStageFilter input.sm-stage-toggle');
      boxes.forEach(function (box) {
        box.addEventListener('change', function () {
          if (box.checked) { delete hiddenStages[box.value]; }
          else { hiddenStages[box.value] = true; }
          applyStageFilter(true);
        });
      });
      var allBtn = document.getElementById('smStagesAll');
      if (allBtn) {
        allBtn.addEventListener('click', function () {
          hiddenStages = Object.create(null);
          applyStageFilter(true);
        });
      }
      var resetBtn = document.getElementById('smStagesReset');
      if (resetBtn) {
        resetBtn.addEventListener('click', function () {
          hiddenStages = Object.create(null);
          expandedStage = null;
          expandedChild = null;
          clearSearch();
          applyStageFilter(true);
        });
      }
    })();

    // ---------- (1) In-map search ----------
    // Case-insensitive substring match on node labels (over the CURRENTLY
    // visible nodes only - i.e. stage view or the drilled-in stage). Matches
    // get .sm-match (bright halo, full opacity); everything else gets .sm-dim.
    // Empty box clears both classes. A single strong match centers on it.
    var searchInput = document.getElementById('smSearch');
    var searchClear = document.getElementById('smSearchClear');
    var _searchT = null;

    function clearSearchClasses() {
      cy.batch(function () {
        cy.elements().removeClass('sm-match sm-dim');
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
        // Dim every visible element, then un-dim + halo the matching nodes.
        cy.elements(':visible').addClass('sm-dim');
        matches.removeClass('sm-dim').addClass('sm-match');
        // Keep edges between two matches readable.
        matches.edgesWith(matches).removeClass('sm-dim');
      });

      // On exactly one match, gently center it so the user can see where it is.
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
      // Escape inside the box clears it without bubbling to the canvas handler.
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { clearSearch(); e.stopPropagation(); }
      });
    }
    if (searchClear) {
      searchClear.addEventListener('click', function () { clearSearch(); if (searchInput) { searchInput.focus(); } });
    }

    // ---------- (3) Minimap overview ----------
    // A true minimap: render the whole graph to a small canvas (bounding box of
    // ALL elements, scaled to fit) with a viewport rectangle showing the part
    // currently on screen. Repaints on pan/zoom/layout. Click recenters the
    // main view. No extra libs - just the model's positions + a 2D context.
    var miniEl = document.getElementById('systemMapMinimap');
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
    // Map a model point -> minimap pixel using the cached whole-graph bbox + fit.
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

      // Edges first (thin grey), then node dots in their stage colour.
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

      // Viewport rectangle: convert the on-screen extent to model coords.
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
    // Click on the minimap pans the main view so the clicked model point is centred.
    if (miniEl) {
      miniEl.addEventListener('click', function (ev) {
        if (!ensureMiniCanvas()) return;
        var rect = miniEl.getBoundingClientRect();
        var px = ev.clientX - rect.left, py = ev.clientY - rect.top;
        var w = miniEl.clientWidth, h = miniEl.clientHeight;
        var t = miniTransform(w, h);
        if (!t || !_miniBB) return;
        // invert the transform back to model coords
        var s = t.s;
        var offX = (w - _miniBB.w * s) / 2;
        var offY = (h - _miniBB.h * s) / 2;
        var mx = _miniBB.x1 + (px - offX) / s;
        var my = _miniBB.y1 + (py - offY) / s;
        // Pan (keeping zoom) so the clicked model point sits at the canvas centre.
        var z = cy.zoom();
        var targetPan = { x: container.clientWidth / 2 - mx * z, y: container.clientHeight / 2 - my * z };
        try { cy.animate({ pan: targetPan, duration: 200 }); }
        catch (e) { cy.pan(targetPan); }
      });
    }
    // Repaint the viewport rectangle as the user pans / zooms / re-lays-out.
    cy.on('pan zoom render', scheduleMinimap);

    // ---- initial render ----
    applyVisibility();
    relayout(true);
    renderBread();
    syncChipUI();
    refreshStageCount();
    showMinimap();

    // The interactive canvas painted successfully - hide the static outline.
    hideFallback();

    // ---- keep the canvas sized to its container. Fixes the blank/empty map on mobile,
    //      where the container's real size settles AFTER Cytoscape first measures it (CSS/
    //      font reflow, address bar), and re-fits on rotate / window resize. ----
    function refit() { try { ensureCanvasSize(); cy.resize(); cy.fit(cy.elements(':visible'), 40); } catch (e) {} }
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
