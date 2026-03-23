@extends('theme::layouts.1col')
@section('title', 'HTR Extraction Results')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Results</li></ol></nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-clipboard-check me-2"></i>Extraction Results</h1>

<div class="row">
  {{-- Image with field overlays --}}
  <div class="col-md-8">
    <div class="card mb-3">
      <div class="card-header py-2 d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff;">
        <span><i class="fas fa-image me-1"></i>Detected Fields</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-sm btn-outline-light" id="btn-zin"><i class="fas fa-search-plus"></i></button>
          <button class="btn btn-sm btn-outline-light" id="btn-zout"><i class="fas fa-search-minus"></i></button>
          <button class="btn btn-sm btn-outline-light" id="btn-zfit"><i class="fas fa-expand"></i></button>
        </div>
      </div>
      <div class="card-body p-0" style="overflow:auto;max-height:70vh;cursor:grab;" id="img-wrap">
        @if(!empty($results['image_name']))
          <canvas id="result-canvas"></canvas>
        @else
          <div class="text-center text-muted py-5">No image available</div>
        @endif
      </div>
    </div>
  </div>

  {{-- ILM Output + Fields --}}
  <div class="col-md-4">
    {{-- ILM Card --}}
    <div class="card mb-3">
      <div class="card-header py-2" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-tree me-1"></i>FamilySearch ILM Output
      </div>
      <div class="card-body">
        @php
          $fields = $results['fields'] ?? [];
          $year = ''; $place = '';
          foreach ($fields as $f) {
              if (($f['label'] ?? '') === 'EVENT_YEAR_ORIG') $year = $f['text'] ?? '';
              if (($f['label'] ?? '') === 'EVENT_PLACE_ORIG') $place = $f['text'] ?? '';
          }
          $docType = $results['doc_type'] ?? 'type_a';
          $rtMap = ['type_a' => ['Death Records','1000015'], 'type_b' => ['Church Records','1000004'], 'type_c' => ['Other Records','1000000']];
          $rt = $rtMap[$docType] ?? $rtMap['type_a'];
        @endphp
        <table class="table table-sm mb-0">
          <tr><td class="fw-bold">EVENT_YEAR_ORIG</td><td>{{ $year ?: '—' }}</td></tr>
          <tr><td class="fw-bold">FS_RECORD_TYPE</td><td>{{ $rt[0] }}</td></tr>
          <tr><td class="fw-bold">FS_RECORD_TYPE_ID</td><td>{{ $rt[1] }}</td></tr>
          <tr><td class="fw-bold">EVENT_PLACE_ORIG</td><td>{{ $place ?: '—' }}</td></tr>
          <tr><td class="fw-bold">non_genealogical</td><td><span class="badge bg-success">false</span></td></tr>
        </table>
      </div>
    </div>

    {{-- Detected Fields --}}
    <div class="card mb-3">
      <div class="card-header py-2" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-list me-1"></i>Extracted Fields
      </div>
      <div class="card-body p-0">
        @forelse($fields as $i => $field)
          <div class="p-2 border-bottom" id="field-{{ $i }}">
            <div class="d-flex align-items-center mb-1">
              <span style="width:12px;height:12px;border-radius:2px;display:inline-block;background:{{ $i === 0 ? '#e74c3c' : '#3498db' }};" class="me-2"></span>
              <strong class="small">{{ $field['form_label'] ?? $field['label'] ?? 'Field' }}</strong>
              @if(!empty($field['confidence']))
                @php $c = ($field['confidence'] ?? 0) * 100; @endphp
                <span class="badge {{ $c > 70 ? 'bg-success' : ($c > 40 ? 'bg-warning' : 'bg-danger') }} ms-auto">{{ number_format($c, 0) }}%</span>
              @endif
            </div>
            <div class="small">
              <strong>{{ $field['text'] ?? '—' }}</strong>
              @if(!empty($field['text_original']) && ($field['text_original'] ?? '') !== ($field['text'] ?? ''))
                <br><span class="text-muted">Original: {{ $field['text_original'] }}</span>
              @endif
            </div>
            @if(!empty($field['bbox']))
              <span class="text-muted" style="font-size:.65rem;">{{ $field['bbox']['x'] ?? 0 }},{{ $field['bbox']['y'] ?? 0 }} {{ $field['bbox']['w'] ?? $field['bbox']['width'] ?? 0 }}×{{ $field['bbox']['h'] ?? $field['bbox']['height'] ?? 0 }}px — {{ $field['sample_count'] ?? 0 }} samples</span>
            @endif
          </div>
        @empty
          <div class="text-center text-muted py-3">No fields detected</div>
        @endforelse
      </div>
    </div>

    {{-- Actions --}}
    <div class="d-flex flex-column gap-2">
      <a href="{{ route('admin.ai.htr.extract') }}" class="btn atom-btn-outline-success"><i class="fas fa-redo me-1"></i>Extract Another</a>
      <a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-white"><i class="fas fa-pen me-1"></i>Annotate</a>
    </div>
  </div>
</div>
@endsection

@push('css')
<style>
  #img-wrap { cursor: grab; }
  #img-wrap:active { cursor: grabbing; }
</style>
@endpush

@push('js')
@if(!empty($results['image_name']))
<script>
(function() {
  const cvs = document.getElementById('result-canvas');
  const ctx = cvs.getContext('2d');
  const wrap = document.getElementById('img-wrap');
  const fields = @json($fields);
  const COLORS = ['#e74c3c','#3498db','#2ecc71','#f39c12'];

  let img = null, scale = 1;
  let panning = false, px = 0, py = 0, psx = 0, psy = 0;

  // Load image
  img = new Image();
  img.onload = function() {
    scale = Math.max(wrap.clientWidth / img.width, 1.0);
    cvs.width = img.width * scale;
    cvs.height = img.height * scale;
    redraw();
  };
  img.src = '{{ route("admin.ai.htr.extractImage", $jobId) }}';

  function redraw() {
    if (!img) return;
    ctx.clearRect(0, 0, cvs.width, cvs.height);
    ctx.save();
    ctx.scale(scale, scale);
    ctx.drawImage(img, 0, 0);

    // Draw detected field boxes
    fields.forEach(function(f, i) {
      const b = f.bbox;
      if (!b) return;
      const color = COLORS[i % COLORS.length];

      // Fill
      ctx.fillStyle = color + '22';
      ctx.fillRect(b.x, b.y, b.w, b.h);

      // Border
      ctx.strokeStyle = color;
      ctx.lineWidth = 3 / scale;
      ctx.setLineDash([]);
      ctx.strokeRect(b.x, b.y, b.w, b.h);

      // Label
      const label = (f.form_label || f.label || '') + (f.text ? ': ' + f.text : '');
      ctx.font = 'bold ' + (14 / scale) + 'px sans-serif';
      const tw = ctx.measureText(label).width;
      ctx.fillStyle = color;
      ctx.fillRect(b.x, b.y - 20 / scale, tw + 10 / scale, 20 / scale);
      ctx.fillStyle = '#fff';
      ctx.fillText(label, b.x + 5 / scale, b.y - 5 / scale);
    });

    ctx.restore();
  }

  // Pan
  wrap.addEventListener('mousedown', function(e) {
    panning = true; px = e.clientX; py = e.clientY;
    psx = wrap.scrollLeft; psy = wrap.scrollTop;
  });
  wrap.addEventListener('mousemove', function(e) {
    if (!panning) return;
    wrap.scrollLeft = psx - (e.clientX - px);
    wrap.scrollTop = psy - (e.clientY - py);
  });
  document.addEventListener('mouseup', function() { panning = false; });

  // Wheel zoom
  wrap.addEventListener('wheel', function(e) {
    if (!img) return;
    e.preventDefault();
    const oldScale = scale;
    scale = e.deltaY < 0 ? Math.min(scale * 1.15, 6) : Math.max(scale / 1.15, 0.3);
    const r = wrap.getBoundingClientRect();
    const mx = e.clientX - r.left + wrap.scrollLeft;
    const my = e.clientY - r.top + wrap.scrollTop;
    const ratio = scale / oldScale;
    cvs.width = img.width * scale; cvs.height = img.height * scale;
    redraw();
    wrap.scrollLeft = mx * ratio - (e.clientX - r.left);
    wrap.scrollTop = my * ratio - (e.clientY - r.top);
  }, { passive: false });

  // Zoom buttons
  function zoom(ns) {
    const cx = wrap.scrollLeft + wrap.clientWidth / 2;
    const cy = wrap.scrollTop + wrap.clientHeight / 2;
    const r = ns / scale; scale = ns;
    cvs.width = img.width * scale; cvs.height = img.height * scale;
    redraw();
    wrap.scrollLeft = cx * r - wrap.clientWidth / 2;
    wrap.scrollTop = cy * r - wrap.clientHeight / 2;
  }
  document.getElementById('btn-zin').addEventListener('click', () => zoom(Math.min(scale * 1.3, 6)));
  document.getElementById('btn-zout').addEventListener('click', () => zoom(Math.max(scale / 1.3, 0.3)));
  document.getElementById('btn-zfit').addEventListener('click', function() {
    scale = Math.max(wrap.clientWidth / img.width, 1.0);
    cvs.width = img.width * scale; cvs.height = img.height * scale;
    redraw(); wrap.scrollLeft = 0; wrap.scrollTop = 0;
  });

  // Click field in sidebar to highlight and scroll to it on canvas
  document.querySelectorAll('[id^="field-"]').forEach(function(el, i) {
    el.style.cursor = 'pointer';
    el.addEventListener('click', function() {
      const f = fields[i];
      if (!f || !f.bbox) return;
      const b = f.bbox;
      // Scroll canvas to show this field centered
      wrap.scrollLeft = b.x * scale - wrap.clientWidth / 2 + (b.w * scale) / 2;
      wrap.scrollTop = b.y * scale - wrap.clientHeight / 2 + (b.h * scale) / 2;
    });
  });
})();
</script>
@endif
@endpush
