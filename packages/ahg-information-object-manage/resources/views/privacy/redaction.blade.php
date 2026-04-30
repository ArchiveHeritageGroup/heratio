@extends('theme::layouts.1col')
@section('title', 'Visual Redaction Editor — ' . ($io->title ?? ''))

@push('styles')
<style>
  /* Redaction Editor Styles */
  .redaction-toolbar .btn.active {
    box-shadow: inset 0 2px 4px rgba(0,0,0,.2);
  }
  .redaction-viewer-container {
    position: relative;
    background: #1a1a2e;
    border-radius: 8px;
    overflow: hidden;
    min-height: 600px;
  }
  .redaction-canvas-wrapper {
    position: relative;
    width: 100%;
    height: 600px;
  }
  #pdf-canvas {
    display: block;
    margin: 0 auto;
  }
  #fabric-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }
  #image-viewer {
    width: 100%;
    height: 600px;
  }
  .page-nav {
    background: rgba(0,0,0,0.7);
    padding: 8px 16px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  .page-nav .btn {
    border-radius: 50%;
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .zoom-controls {
    background: rgba(0,0,0,0.7);
    padding: 4px 8px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .zoom-controls .btn {
    border-radius: 50%;
    width: 28px;
    height: 28px;
    padding: 0;
    font-size: 0.8rem;
  }
  .region-item {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
    transition: background 0.2s;
  }
  .region-item:hover {
    background: rgba(255,255,255,0.1);
  }
  .region-item .region-label {
    font-size: 0.85rem;
    color: #e0e0e0;
  }
  .region-item .region-coords {
    font-size: 0.7rem;
    color: #888;
    font-family: monospace;
  }
  .region-item .btn-delete-region {
    opacity: 0.6;
    transition: opacity 0.2s;
  }
  .region-item:hover .btn-delete-region {
    opacity: 1;
  }
  .redaction-rect {
    fill: rgba(0, 0, 0, 0.8);
    stroke: #ff4444;
    stroke-width: 2;
    stroke-dasharray: 5,3;
  }
  .drawing-active {
    cursor: crosshair !important;
  }
  .help-card {
    background: #f8f9fa;
    border: 1px dashed #dee2e6;
  }
  .doc-info-card {
    background: #1a1a2e;
    color: #e0e0e0;
  }
  .doc-info-card .label {
    color: #888;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .doc-info-card .value {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
  }
</style>
@endpush

@section('content')
<div class="container py-4">

  {{-- Flash Messages --}}
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
  @endif

  {{-- Header with Breadcrumb --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-1">
        <i class="fas fa-eraser me-2"></i>Visual Redaction Editor
      </h4>
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
          @if(isset($io->slug))
            <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? 'Record' }}</a></li>
          @endif
          <li class="breadcrumb-item active">Visual Redaction</li>
        </ol>
      </nav>
    </div>
    <div class="btn-group">
      <a href="{{ isset($io->slug) ? route('informationobject.show', $io->slug) : '#' }}" class="btn atom-btn-outline-light btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
      </a>
    </div>
  </div>

  @php
    $documentType = $documentType ?? null;
    $documentUrl = $documentUrl ?? null;
    $totalPages = $totalPages ?? 1;
    $existingRedactions = $existingRedactions ?? [];
    $redactionCount = is_countable($existingRedactions) ? count($existingRedactions) : 0;

    // Determine viewer type based on document
    $isPdf = $documentType === 'pdf' || (isset($documentUrl) && str_ends_with(strtolower($documentUrl ?? ''), '.pdf'));
    $isImage = $documentType === 'image';
    $isUnsupported = in_array($documentType, ['3d', 'unsupported', null]);
  @endphp

  @if($isUnsupported)
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong>Visual redaction is not available for this object type.</strong>
      @if($documentType === '3d')
        This is a 3D model — redaction only works on images and PDFs.
      @elseif(!$documentUrl)
        No digital object is attached to this record.
      @else
        The file type ({{ $digitalObject->mime_type ?? 'unknown' }}) is not supported for visual redaction.
      @endif
    </div>
    <a href="{{ route('informationobject.show', $io->slug) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    @php return; @endphp
  @endif

  {{-- Document Info Card --}}
  <div class="card doc-info-card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <div class="row text-center">
        <div class="col-md-4">
          <div class="label">Document Type</div>
          <div class="value">
            @if($isPdf)
              <i class="fas fa-file-pdf text-danger me-1"></i> PDF Document
            @else
              <i class="fas fa-file-image text-info me-1"></i> Image
            @endif
          </div>
        </div>
        <div class="col-md-4">
          <div class="label">Pages</div>
          <div class="value">{{ $totalPages }}</div>
        </div>
        <div class="col-md-4">
          <div class="label">Redactions</div>
          <div class="value">
            <span id="redaction-count">{{ $redactionCount }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Redaction Toolbar --}}
  <div class="card border-0 shadow-sm mb-3 redaction-toolbar">
    <div class="card-body py-2">
      <div class="d-flex align-items-center justify-content-between">
        <div class="btn-group" role="group" aria-label="{{ __('Redaction tools') }}">
          <button type="button" class="btn btn-outline-light btn-sm active" id="tool-select" data-tool="select" title="{{ __('Select tool') }}">
            <i class="fas fa-mouse-pointer me-1"></i> Select
          </button>
          <button type="button" class="btn atom-atom-btn-outline-danger btn-sm" id="tool-draw" data-tool="draw" title="{{ __('Draw redaction rectangle') }}">
            <i class="fas fa-vector-square me-1"></i> Draw
          </button>
        </div>

        <div class="d-flex align-items-center gap-2">
          <div class="zoom-controls">
            <button type="button" class="btn btn-outline-light btn-sm" id="zoom-out" title="{{ __('Zoom out') }}">
              <i class="fas fa-search-minus"></i>
            </button>
            <span class="text-white small" id="zoom-level">100%</span>
            <button type="button" class="btn btn-outline-light btn-sm" id="zoom-in" title="{{ __('Zoom in') }}">
              <i class="fas fa-search-plus"></i>
            </button>
            <button type="button" class="btn btn-outline-light btn-sm" id="zoom-fit" title="{{ __('Fit to page') }}">
              <i class="fas fa-expand"></i>
            </button>
          </div>

          <div class="btn-group">
            <button type="button" class="btn atom-atom-btn-outline-success btn-sm" id="save-redactions" title="{{ __('Save redactions') }}">
              <i class="fas fa-save me-1"></i> Save
            </button>
            <button type="button" class="btn atom-btn-outline-light btn-sm" id="apply-redactions" title="{{ __('Apply redactions permanently') }}">
              <i class="fas fa-stamp me-1"></i> Apply
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Main Content: Viewer + Region List --}}
  <div class="row g-3">

    {{-- Left: Document Viewer (9 cols) --}}
    <div class="col-md-9">
      <div class="redaction-viewer-container" id="viewer-container">

        {{-- PDF Viewer --}}
        <div id="pdf-viewer-wrapper" style="display: {{ $isPdf ? 'block' : 'none' }};">
          <div class="redaction-canvas-wrapper">
            <canvas id="pdf-canvas"></canvas>
            <canvas id="fabric-overlay"></canvas>
          </div>
          {{-- Page Navigation --}}
          <div class="text-center py-2">
            <div class="page-nav">
              <button class="btn btn-outline-light btn-sm" id="prev-page" title="{{ __('Previous page') }}">
                <i class="fas fa-chevron-left"></i>
              </button>
              <span class="text-white small">
                Page <span id="current-page">1</span> of <span id="total-pages">{{ $totalPages }}</span>
              </span>
              <button class="btn btn-outline-light btn-sm" id="next-page" title="{{ __('Next page') }}">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>

        {{-- Image Viewer (OpenSeadragon + Annotorious) --}}
        <div id="image-viewer-wrapper" style="display: {{ $isImage ? 'block' : 'none' }};">
          <div id="image-viewer"></div>
        </div>

      </div>
    </div>

    {{-- Right: Redaction List (3 cols) --}}
    <div class="col-md-3">
      <div class="card border-0 shadow-sm" style="background: #1a1a2e;">
        <div class="card-header border-0" style="background:var(--ahg-primary);color:#fff">
          <div class="d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-1"></i> Redaction Regions</span>
            <span class="badge bg-danger" id="region-count-badge">{{ $redactionCount }}</span>
          </div>
        </div>
        <div class="card-body p-2" id="region-list" style="max-height: 500px; overflow-y: auto;">
          {{-- Regions populated by JavaScript --}}
          @if($redactionCount === 0)
            <div class="text-center py-4" id="no-regions-msg">
              <i class="fas fa-vector-square text-muted" style="font-size: 2rem;"></i>
              <p class="text-muted small mt-2 mb-0">No redaction regions yet.<br>Use the Draw tool to add regions.</p>
            </div>
          @endif
        </div>
        <div class="card-footer border-0 p-2" style="background: #16213e;">
          <button class="btn atom-atom-btn-outline-danger btn-sm w-100" id="clear-all-regions" title="{{ __('Remove all regions') }}">
            <i class="fas fa-trash me-1"></i> Clear All
          </button>
        </div>
      </div>

      {{-- How to Use --}}
      <div class="card help-card mt-3">
        <div class="card-header bg-transparent fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-question-circle me-1"></i> How to Use
        </div>
        <div class="card-body small">
          <ol class="ps-3 mb-0">
            <li class="mb-2">Click <strong>Draw</strong> to activate the drawing tool.</li>
            <li class="mb-2">Click and drag on the document to draw a redaction rectangle.</li>
            <li class="mb-2">Use <strong>Select</strong> to move or resize existing redactions.</li>
            <li class="mb-2">Click the <i class="fas fa-trash-alt text-danger"></i> icon on a region to remove it.</li>
            <li class="mb-2">Click <strong>Save</strong> to store redactions without applying them.</li>
            <li class="mb-0">Click <strong>Apply</strong> to permanently redact the document (irreversible).</li>
          </ol>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection

@push('js')
{{-- PDF.js --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
{{-- OpenSeadragon --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/openseadragon/4.1.0/openseadragon.min.js"></script>
{{-- Fabric.js --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

  // =========================================================================
  // Configuration
  // =========================================================================
  const CONFIG = {
    objectId: @json($io->id ?? null),
    documentUrl: @json($documentUrl ?? ''),
    isPdf: @json($isPdf),
    totalPages: @json($totalPages),
    existingRedactions: @json($existingRedactions),
    csrfToken: @json(csrf_token()),
    saveUrl: '#',
    applyUrl: '#',
  };

  // =========================================================================
  // State
  // =========================================================================
  let currentTool = 'select';
  let currentPage = 1;
  let zoomLevel = 1.0;
  let regions = [];
  let regionIdCounter = 0;

  // Viewer instances
  let pdfDoc = null;
  let fabricCanvas = null;
  let osdViewer = null;
  let isDrawing = false;
  let drawStartX = 0;
  let drawStartY = 0;
  let activeRect = null;

  // =========================================================================
  // Tool Selection
  // =========================================================================
  const toolSelectBtn = document.getElementById('tool-select');
  const toolDrawBtn = document.getElementById('tool-draw');
  const viewerContainer = document.getElementById('viewer-container');

  function setTool(tool) {
    currentTool = tool;
    // Update button states
    toolSelectBtn.classList.toggle('active', tool === 'select');
    toolDrawBtn.classList.toggle('active', tool === 'draw');
    // Update cursor
    if (tool === 'draw') {
      viewerContainer.classList.add('drawing-active');
      if (fabricCanvas) {
        fabricCanvas.isDrawingMode = false;
        fabricCanvas.selection = false;
        fabricCanvas.defaultCursor = 'crosshair';
        fabricCanvas.hoverCursor = 'crosshair';
        fabricCanvas.forEachObject(function(obj) {
          obj.selectable = false;
          obj.evented = false;
        });
      }
    } else {
      viewerContainer.classList.remove('drawing-active');
      if (fabricCanvas) {
        fabricCanvas.isDrawingMode = false;
        fabricCanvas.selection = true;
        fabricCanvas.defaultCursor = 'default';
        fabricCanvas.hoverCursor = 'move';
        fabricCanvas.forEachObject(function(obj) {
          obj.selectable = true;
          obj.evented = true;
        });
      }
    }
  }

  if (toolSelectBtn) {
    toolSelectBtn.addEventListener('click', function() { setTool('select'); });
  }
  if (toolDrawBtn) {
    toolDrawBtn.addEventListener('click', function() { setTool('draw'); });
  }

  // =========================================================================
  // PDF Viewer Initialization
  // =========================================================================
  function initPdfViewer() {
    if (!CONFIG.isPdf || !CONFIG.documentUrl) return;

    const pdfjsLib = window['pdfjs-dist/build/pdf'] || window.pdfjsLib;
    if (!pdfjsLib) {
      console.error('PDF.js not loaded');
      return;
    }
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const pdfCanvas = document.getElementById('pdf-canvas');
    const fabricOverlay = document.getElementById('fabric-overlay');

    pdfjsLib.getDocument(CONFIG.documentUrl).promise.then(function(pdf) {
      pdfDoc = pdf;
      document.getElementById('total-pages').textContent = pdf.numPages;
      CONFIG.totalPages = pdf.numPages;
      renderPdfPage(currentPage);
    }).catch(function(err) {
      console.error('Error loading PDF:', err);
    });

    function renderPdfPage(pageNum) {
      pdfDoc.getPage(pageNum).then(function(page) {
        const scale = zoomLevel * 1.5;
        const viewport = page.getViewport({ scale: scale });

        pdfCanvas.height = viewport.height;
        pdfCanvas.width = viewport.width;

        const ctx = pdfCanvas.getContext('2d');
        const renderContext = {
          canvasContext: ctx,
          viewport: viewport
        };

        page.render(renderContext).promise.then(function() {
          // Initialize or resize Fabric.js overlay
          fabricOverlay.width = viewport.width;
          fabricOverlay.height = viewport.height;

          if (!fabricCanvas) {
            fabricCanvas = new fabric.Canvas('fabric-overlay', {
              width: viewport.width,
              height: viewport.height,
              selection: currentTool === 'select',
            });
            initFabricEvents();
            loadExistingRedactions();
          } else {
            fabricCanvas.setWidth(viewport.width);
            fabricCanvas.setHeight(viewport.height);
            fabricCanvas.renderAll();
          }

          document.getElementById('current-page').textContent = pageNum;
        });
      });
    }

    // Page navigation
    document.getElementById('prev-page').addEventListener('click', function() {
      if (currentPage > 1) {
        currentPage--;
        renderPdfPage(currentPage);
      }
    });

    document.getElementById('next-page').addEventListener('click', function() {
      if (currentPage < CONFIG.totalPages) {
        currentPage++;
        renderPdfPage(currentPage);
      }
    });
  }

  // =========================================================================
  // Fabric.js Drawing Events
  // =========================================================================
  function initFabricEvents() {
    if (!fabricCanvas) return;

    fabricCanvas.on('mouse:down', function(opt) {
      if (currentTool !== 'draw') return;
      isDrawing = true;
      const pointer = fabricCanvas.getPointer(opt.e);
      drawStartX = pointer.x;
      drawStartY = pointer.y;

      activeRect = new fabric.Rect({
        left: drawStartX,
        top: drawStartY,
        width: 0,
        height: 0,
        fill: 'rgba(0, 0, 0, 0.6)',
        stroke: '#ff4444',
        strokeWidth: 2,
        strokeDashArray: [5, 3],
        selectable: false,
        evented: false,
      });
      fabricCanvas.add(activeRect);
    });

    fabricCanvas.on('mouse:move', function(opt) {
      if (!isDrawing || !activeRect) return;
      const pointer = fabricCanvas.getPointer(opt.e);

      const left = Math.min(drawStartX, pointer.x);
      const top = Math.min(drawStartY, pointer.y);
      const width = Math.abs(pointer.x - drawStartX);
      const height = Math.abs(pointer.y - drawStartY);

      activeRect.set({ left: left, top: top, width: width, height: height });
      fabricCanvas.renderAll();
    });

    fabricCanvas.on('mouse:up', function(opt) {
      if (!isDrawing || !activeRect) return;
      isDrawing = false;

      // Only add if the rectangle is big enough
      if (activeRect.width < 5 || activeRect.height < 5) {
        fabricCanvas.remove(activeRect);
        activeRect = null;
        return;
      }

      // Make the rect selectable now
      activeRect.set({
        selectable: currentTool === 'select',
        evented: currentTool === 'select',
      });

      const regionId = ++regionIdCounter;
      activeRect.regionId = regionId;

      addRegion({
        id: regionId,
        left: Math.round(activeRect.left),
        top: Math.round(activeRect.top),
        width: Math.round(activeRect.width),
        height: Math.round(activeRect.height),
        page: currentPage,
        fabricObj: activeRect,
      });

      activeRect = null;
      fabricCanvas.renderAll();
    });

    fabricCanvas.on('object:modified', function(opt) {
      const obj = opt.target;
      if (obj && obj.regionId) {
        updateRegion(obj.regionId, {
          left: Math.round(obj.left),
          top: Math.round(obj.top),
          width: Math.round(obj.width * obj.scaleX),
          height: Math.round(obj.height * obj.scaleY),
        });
      }
    });
  }

  // =========================================================================
  // Image Viewer Initialization (OpenSeadragon)
  // =========================================================================
  function initImageViewer() {
    if (CONFIG.isPdf || !CONFIG.documentUrl) return;

    osdViewer = OpenSeadragon({
      id: 'image-viewer',
      prefixUrl: 'https://cdnjs.cloudflare.com/ajax/libs/openseadragon/4.1.0/images/',
      tileSources: {
        type: 'image',
        url: CONFIG.documentUrl,
      },
      showNavigationControl: true,
      showZoomControl: true,
      showHomeControl: true,
      showFullPageControl: false,
      maxZoomPixelRatio: 4,
      minZoomImageRatio: 0.5,
      visibilityRatio: 0.5,
      constrainDuringPan: true,
    });

    // Create overlay canvas for redaction drawing on image viewer
    osdViewer.addHandler('open', function() {
      const overlayEl = document.createElement('canvas');
      overlayEl.id = 'osd-fabric-overlay';
      overlayEl.style.position = 'absolute';
      overlayEl.style.top = '0';
      overlayEl.style.left = '0';
      overlayEl.style.width = '100%';
      overlayEl.style.height = '100%';
      overlayEl.style.pointerEvents = 'none';

      const container = osdViewer.canvas;
      container.appendChild(overlayEl);

      // For image viewer, use Fabric.js overlay on top of OSD
      const rect = container.getBoundingClientRect();
      overlayEl.width = rect.width;
      overlayEl.height = rect.height;

      fabricCanvas = new fabric.Canvas('osd-fabric-overlay', {
        width: rect.width,
        height: rect.height,
        selection: currentTool === 'select',
      });

      // Enable pointer events on fabric overlay when in draw mode
      const fabricEl = fabricCanvas.upperCanvasEl.parentElement;

      function updateFabricPointerEvents() {
        if (currentTool === 'draw') {
          fabricEl.style.pointerEvents = 'auto';
          if (osdViewer) osdViewer.setMouseNavEnabled(false);
        } else {
          fabricEl.style.pointerEvents = 'none';
          if (osdViewer) osdViewer.setMouseNavEnabled(true);
        }
      }

      // Override setTool to also handle OSD
      const originalSetTool = setTool;
      setTool = function(tool) {
        originalSetTool(tool);
        updateFabricPointerEvents();
      };

      updateFabricPointerEvents();
      initFabricEvents();
      loadExistingRedactions();
    });
  }

  // =========================================================================
  // Region Management
  // =========================================================================
  function addRegion(region) {
    regions.push(region);
    renderRegionList();
    updateRegionCount();
  }

  function deleteRegion(regionId) {
    const idx = regions.findIndex(function(r) { return r.id === regionId; });
    if (idx !== -1) {
      const region = regions[idx];
      if (region.fabricObj && fabricCanvas) {
        fabricCanvas.remove(region.fabricObj);
        fabricCanvas.renderAll();
      }
      regions.splice(idx, 1);
      renderRegionList();
      updateRegionCount();
    }
  }

  function updateRegion(regionId, data) {
    const region = regions.find(function(r) { return r.id === regionId; });
    if (region) {
      Object.assign(region, data);
      renderRegionList();
    }
  }

  function updateRegionCount() {
    const count = regions.length;
    document.getElementById('redaction-count').textContent = count;
    document.getElementById('region-count-badge').textContent = count;
  }

  function renderRegionList() {
    const container = document.getElementById('region-list');
    const noMsg = document.getElementById('no-regions-msg');

    if (regions.length === 0) {
      container.innerHTML = '<div class="text-center py-4" id="no-regions-msg">' +
        '<i class="fas fa-vector-square text-muted" style="font-size: 2rem;"></i>' +
        '<p class="text-muted small mt-2 mb-0">No redaction regions yet.<br>Use the Draw tool to add regions.</p>' +
        '</div>';
      return;
    }

    let html = '';
    regions.forEach(function(region) {
      html += '<div class="region-item" data-region-id="' + region.id + '">' +
        '<div class="d-flex justify-content-between align-items-start">' +
          '<div>' +
            '<div class="region-label"><i class="fas fa-vector-square text-danger me-1"></i> Region #' + region.id + '</div>' +
            '<div class="region-coords">x:' + region.left + ' y:' + region.top + ' w:' + region.width + ' h:' + region.height +
            (CONFIG.isPdf ? ' p:' + region.page : '') + '</div>' +
          '</div>' +
          '<button type="button" class="btn btn-sm atom-atom-btn-outline-danger btn-delete-region" data-region-id="' + region.id + '" title="Delete region">' +
            '<i class="fas fa-trash-alt"></i>' +
          '</button>' +
        '</div>' +
      '</div>';
    });
    container.innerHTML = html;

    // Attach delete handlers
    container.querySelectorAll('.btn-delete-region').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const id = parseInt(this.getAttribute('data-region-id'), 10);
        deleteRegion(id);
      });
    });
  }

  function loadExistingRedactions() {
    if (!CONFIG.existingRedactions || !Array.isArray(CONFIG.existingRedactions)) return;

    CONFIG.existingRedactions.forEach(function(r) {
      if (!fabricCanvas) return;

      const rect = new fabric.Rect({
        left: r.left || r.x || 0,
        top: r.top || r.y || 0,
        width: r.width || 100,
        height: r.height || 50,
        fill: 'rgba(0, 0, 0, 0.6)',
        stroke: '#ff4444',
        strokeWidth: 2,
        strokeDashArray: [5, 3],
        selectable: currentTool === 'select',
        evented: currentTool === 'select',
      });

      const regionId = ++regionIdCounter;
      rect.regionId = regionId;
      fabricCanvas.add(rect);

      addRegion({
        id: regionId,
        left: Math.round(rect.left),
        top: Math.round(rect.top),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        page: r.page || 1,
        fabricObj: rect,
      });
    });

    if (fabricCanvas) fabricCanvas.renderAll();
  }

  // =========================================================================
  // Clear All Regions
  // =========================================================================
  document.getElementById('clear-all-regions').addEventListener('click', function() {
    if (regions.length === 0) return;
    if (!confirm('Are you sure you want to remove all redaction regions?')) return;

    // Remove all fabric objects
    regions.forEach(function(region) {
      if (region.fabricObj && fabricCanvas) {
        fabricCanvas.remove(region.fabricObj);
      }
    });
    if (fabricCanvas) fabricCanvas.renderAll();

    regions = [];
    regionIdCounter = 0;
    renderRegionList();
    updateRegionCount();
  });

  // =========================================================================
  // Zoom Controls
  // =========================================================================
  document.getElementById('zoom-in').addEventListener('click', function() {
    zoomLevel = Math.min(zoomLevel + 0.25, 4.0);
    document.getElementById('zoom-level').textContent = Math.round(zoomLevel * 100) + '%';
    if (CONFIG.isPdf && pdfDoc) {
      pdfDoc.getPage(currentPage).then(function(page) {
        const scale = zoomLevel * 1.5;
        const viewport = page.getViewport({ scale: scale });
        const pdfCanvas = document.getElementById('pdf-canvas');
        pdfCanvas.height = viewport.height;
        pdfCanvas.width = viewport.width;
        const ctx = pdfCanvas.getContext('2d');
        page.render({ canvasContext: ctx, viewport: viewport });
        if (fabricCanvas) {
          fabricCanvas.setWidth(viewport.width);
          fabricCanvas.setHeight(viewport.height);
          fabricCanvas.renderAll();
        }
      });
    }
    if (osdViewer) {
      osdViewer.viewport.zoomBy(1.25);
    }
  });

  document.getElementById('zoom-out').addEventListener('click', function() {
    zoomLevel = Math.max(zoomLevel - 0.25, 0.25);
    document.getElementById('zoom-level').textContent = Math.round(zoomLevel * 100) + '%';
    if (CONFIG.isPdf && pdfDoc) {
      pdfDoc.getPage(currentPage).then(function(page) {
        const scale = zoomLevel * 1.5;
        const viewport = page.getViewport({ scale: scale });
        const pdfCanvas = document.getElementById('pdf-canvas');
        pdfCanvas.height = viewport.height;
        pdfCanvas.width = viewport.width;
        const ctx = pdfCanvas.getContext('2d');
        page.render({ canvasContext: ctx, viewport: viewport });
        if (fabricCanvas) {
          fabricCanvas.setWidth(viewport.width);
          fabricCanvas.setHeight(viewport.height);
          fabricCanvas.renderAll();
        }
      });
    }
    if (osdViewer) {
      osdViewer.viewport.zoomBy(0.8);
    }
  });

  document.getElementById('zoom-fit').addEventListener('click', function() {
    zoomLevel = 1.0;
    document.getElementById('zoom-level').textContent = '100%';
    if (CONFIG.isPdf && pdfDoc) {
      pdfDoc.getPage(currentPage).then(function(page) {
        const scale = zoomLevel * 1.5;
        const viewport = page.getViewport({ scale: scale });
        const pdfCanvas = document.getElementById('pdf-canvas');
        pdfCanvas.height = viewport.height;
        pdfCanvas.width = viewport.width;
        const ctx = pdfCanvas.getContext('2d');
        page.render({ canvasContext: ctx, viewport: viewport });
        if (fabricCanvas) {
          fabricCanvas.setWidth(viewport.width);
          fabricCanvas.setHeight(viewport.height);
          fabricCanvas.renderAll();
        }
      });
    }
    if (osdViewer) {
      osdViewer.viewport.goHome();
    }
  });

  // =========================================================================
  // Save Redactions (AJAX)
  // =========================================================================
  document.getElementById('save-redactions').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    const payload = {
      information_object_id: CONFIG.objectId,
      regions: regions.map(function(r) {
        return {
          left: r.left,
          top: r.top,
          width: r.width,
          height: r.height,
          page: r.page || 1,
        };
      }),
    };

    fetch(CONFIG.saveUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-1"></i> Save';
      if (data.success) {
        showToast('Redactions saved successfully.', 'success');
      } else {
        showToast(data.message || 'Failed to save redactions.', 'danger');
      }
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-1"></i> Save';
      console.error('Save error:', err);
      showToast('An error occurred while saving.', 'danger');
    });
  });

  // =========================================================================
  // Apply Redactions (AJAX - Permanent)
  // =========================================================================
  document.getElementById('apply-redactions').addEventListener('click', function() {
    if (regions.length === 0) {
      showToast('No redaction regions to apply.', 'warning');
      return;
    }
    if (!confirm('WARNING: This will permanently redact the selected areas from the document. This action cannot be undone. Continue?')) {
      return;
    }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Applying...';

    const payload = {
      information_object_id: CONFIG.objectId,
      regions: regions.map(function(r) {
        return {
          left: r.left,
          top: r.top,
          width: r.width,
          height: r.height,
          page: r.page || 1,
        };
      }),
    };

    fetch(CONFIG.applyUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CONFIG.csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-stamp me-1"></i> Apply';
      if (data.success) {
        showToast('Redactions applied successfully. Reloading...', 'success');
        setTimeout(function() { window.location.reload(); }, 1500);
      } else {
        showToast(data.message || 'Failed to apply redactions.', 'danger');
      }
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-stamp me-1"></i> Apply';
      console.error('Apply error:', err);
      showToast('An error occurred while applying redactions.', 'danger');
    });
  });

  // =========================================================================
  // Toast Helper
  // =========================================================================
  function showToast(message, type) {
    type = type || 'info';
    const toastHtml = '<div class="toast align-items-center text-bg-' + type + ' border-0 show" role="alert">' +
      '<div class="d-flex">' +
        '<div class="toast-body">' + message + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
      '</div>' +
    '</div>';

    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '9999';
      document.body.appendChild(container);
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = toastHtml;
    const toastEl = wrapper.firstChild;
    container.appendChild(toastEl);

    setTimeout(function() {
      toastEl.classList.remove('show');
      setTimeout(function() { toastEl.remove(); }, 300);
    }, 4000);
  }

  // =========================================================================
  // Initialize
  // =========================================================================
  if (CONFIG.isPdf) {
    initPdfViewer();
  } else {
    initImageViewer();
  }

});
</script>
@endpush
