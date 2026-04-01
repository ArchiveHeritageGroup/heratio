@extends('theme::layouts.1col')
@section('title', 'Condition Report — ' . ($io->title ?? ''))
@section('body-class', 'condition show')

@php
  $photoTypes = [
      'overall' => 'Overall View',
      'detail'  => 'Detail',
      'damage'  => 'Damage',
      'before'  => 'Before Treatment',
      'after'   => 'After Treatment',
      'other'   => 'Other',
  ];
  $assessor = $report->assessor_user_id
      ? (\Illuminate\Support\Facades\DB::table('user')->where('id', $report->assessor_user_id)->value('username') ?? 'User #' . $report->assessor_user_id)
      : '—';
  $ratingColors = [
      'excellent' => 'success', 'good' => 'success',
      'fair' => 'warning', 'poor' => 'danger', 'critical' => 'dark',
  ];
  $ratingColor = $ratingColors[strtolower($report->overall_rating ?? '')] ?? 'secondary';
@endphp

@section('content')
<div class="container py-3">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? '' }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('io.condition', $io->slug) }}">Condition</a></li>
      <li class="breadcrumb-item active">Report #{{ $report->id }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="fas fa-clipboard-check me-2"></i>Condition Report</h1>
    <a href="{{ route('io.condition', $io->slug) }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i>Back
    </a>
  </div>

  {{-- Report Summary --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-star me-1"></i> Report Summary
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <strong>Assessment Date</strong>
          <p class="mb-0">{{ $report->assessment_date ?? '—' }}</p>
        </div>
        <div class="col-md-3">
          <strong>Overall Rating</strong>
          <p class="mb-0"><span class="badge bg-{{ $ratingColor }} fs-6">{{ ucfirst($report->overall_rating ?? '—') }}</span></p>
        </div>
        <div class="col-md-3">
          <strong>Context</strong>
          <p class="mb-0">{{ ucfirst($report->context ?? '—') }}</p>
        </div>
        <div class="col-md-3">
          <strong>Assessor</strong>
          <p class="mb-0">{{ $assessor }}</p>
        </div>
      </div>
      <div class="row g-3 mt-2">
        <div class="col-md-3">
          <strong>Priority</strong>
          <p class="mb-0">{{ ucfirst($report->priority ?? '—') }}</p>
        </div>
        <div class="col-md-3">
          <strong>Next Check</strong>
          <p class="mb-0">{{ $report->next_check_date ?? '—' }}</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Notes --}}
  @if($report->summary || $report->recommendations || $report->environmental_notes || $report->handling_notes || $report->display_notes || $report->storage_notes)
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-sticky-note me-1"></i> Notes
      </div>
      <div class="card-body">
        @if($report->summary)
          <div class="mb-3">
            <strong>Summary</strong>
            <p>{{ $report->summary }}</p>
          </div>
        @endif
        @if($report->recommendations)
          <div class="mb-3">
            <strong>Treatment Recommendations</strong>
            <p>{{ $report->recommendations }}</p>
          </div>
        @endif
        <div class="row">
          @foreach(['environmental_notes' => 'Environmental', 'handling_notes' => 'Handling', 'display_notes' => 'Display', 'storage_notes' => 'Storage'] as $field => $label)
            @if($report->$field)
              <div class="col-md-6 mb-3">
                <strong>{{ $label }}</strong>
                <p class="mb-0">{{ $report->$field }}</p>
              </div>
            @endif
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Damages --}}
  @if($damages->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-exclamation-triangle me-1"></i> Damage Records ({{ $damages->count() }})
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr><th>Type</th><th>Location</th><th>Severity</th><th>Description</th><th>Treatment</th></tr>
            </thead>
            <tbody>
              @foreach($damages as $dmg)
                <tr>
                  <td>{{ $dmg->damage_type ?? '—' }}</td>
                  <td>{{ $dmg->location ?? '—' }}</td>
                  <td>
                    @php $sevColor = ['minor'=>'info','moderate'=>'warning','severe'=>'danger','critical'=>'dark'][$dmg->severity ?? ''] ?? 'secondary'; @endphp
                    <span class="badge bg-{{ $sevColor }}">{{ ucfirst($dmg->severity ?? '—') }}</span>
                  </td>
                  <td>{{ $dmg->description ?? '' }}</td>
                  <td>{{ $dmg->treatment_notes ?? '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Photos --}}
  <div class="card mb-4" id="photos">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-images me-2"></i>Photos</h5>
      <span class="badge bg-light text-dark">{{ $photos->count() }}</span>
    </div>
    <div class="card-body">

      {{-- Upload form --}}
      @auth
        <div class="border rounded p-3 mb-4 bg-light">
          <h6><i class="fas fa-upload me-1"></i>Upload Photo</h6>
          <form method="POST" action="{{ route('io.condition.photo.upload', $report->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-2 align-items-end">
              <div class="col-md-3">
                <label class="form-label small">Photo Type</label>
                <select name="image_type" class="form-select form-select-sm">
                  @foreach($photoTypes as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small">Caption</label>
                <input type="text" name="caption" class="form-control form-control-sm" placeholder="Brief description">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Photo</label>
                <input type="file" name="photo" class="form-control form-control-sm" accept="image/*" required>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-sm atom-btn-outline-success w-100">
                  <i class="fas fa-upload me-1"></i>Upload
                </button>
              </div>
            </div>
          </form>
        </div>
      @endauth

      {{-- Photo grid --}}
      @if($photos->count())
        <div class="row">
          @foreach($photos as $photo)
            @php
              $imgSrc = $photo->file_path
                  ? (str_starts_with($photo->file_path, '/') ? $photo->file_path : '/uploads/condition_photos/' . $photo->file_path)
                  : '#';
              $annCount = 0;
              if ($photo->annotations) {
                  $annData = json_decode($photo->annotations, true);
                  $annCount = is_array($annData) ? count($annData) : 0;
              }
            @endphp
            <div class="col-md-3 col-sm-6 mb-4">
              <div class="card h-100">
                <div class="position-relative">
                  <img src="{{ $imgSrc }}" class="card-img-top" alt="{{ $photo->caption ?? '' }}" style="height:180px;object-fit:cover;">
                  <span class="badge bg-info position-absolute top-0 end-0 m-2">{{ $photoTypes[$photo->image_type] ?? 'Other' }}</span>
                  @if($annCount > 0)
                    <span class="badge bg-warning position-absolute top-0 start-0 m-2">
                      <i class="fas fa-draw-polygon"></i> {{ $annCount }}
                    </span>
                  @endif
                </div>
                <div class="card-body p-2">
                  @if($photo->caption)
                    <p class="card-text small mb-1">{{ $photo->caption }}</p>
                  @endif
                  <small class="text-muted">{{ \Carbon\Carbon::parse($photo->created_at)->format('d M Y') }}</small>
                </div>
                @auth
                  <div class="card-footer p-2">
                    <div class="btn-group btn-group-sm w-100">
                      <button class="btn btn-outline-info" title="Annotate"
                              data-action="annotate"
                              data-photo-id="{{ $photo->id }}"
                              data-image-src="{{ $imgSrc }}">
                        <i class="fas fa-draw-polygon"></i>
                      </button>
                      <button class="btn btn-outline-success" title="AI Scan"
                              data-action="ai-scan"
                              data-photo-id="{{ $photo->id }}"
                              data-image-src="{{ $imgSrc }}">
                        <i class="fas fa-robot"></i>
                      </button>
                      <form method="POST" action="{{ route('io.condition.photo.delete', $photo->id) }}" class="d-inline"
                            onsubmit="return confirm('Delete this photo?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                      </form>
                    </div>
                  </div>
                @endauth
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="text-center py-5">
          <i class="fas fa-camera fa-4x text-muted mb-3"></i>
          <p class="text-muted">No photos uploaded yet.</p>
        </div>
      @endif
    </div>
  </div>

</div>

{{-- Annotation Modal --}}
<div class="modal fade" id="annotatorModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-draw-polygon me-2"></i>Annotate Photo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-light border-bottom">
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary" id="ann-tool-rect"><i class="fas fa-vector-square me-1"></i>Rectangle</button>
            <button type="button" class="btn btn-outline-primary" id="ann-tool-circle"><i class="fas fa-circle me-1"></i>Circle</button>
            <button type="button" class="btn btn-outline-primary" id="ann-tool-arrow"><i class="fas fa-arrow-right me-1"></i>Arrow</button>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <label class="small me-1">Label:</label>
            <select id="ann-damage-type" class="form-select form-select-sm" style="width:150px;">
              <option value="tear">Tear</option>
              <option value="stain">Stain</option>
              <option value="foxing">Foxing</option>
              <option value="fading">Fading</option>
              <option value="mould">Mould</option>
              <option value="insect">Insect damage</option>
              <option value="water">Water damage</option>
              <option value="abrasion">Abrasion</option>
              <option value="crack">Crack</option>
              <option value="loss">Loss/Missing</option>
              <option value="other">Other</option>
            </select>
            <label class="small me-1">Color:</label>
            <input type="color" id="ann-color" value="#ff0000" style="width:30px;height:30px;padding:0;border:none;">
          </div>
        </div>
        <div id="annotator-container" style="width:100%;height:500px;background:#1a1a2e;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn atom-btn-outline-success" id="ann-save">
          <i class="fas fa-save me-1"></i>Save Annotations
        </button>
      </div>
    </div>
  </div>
</div>

{{-- AI Scan Modal --}}
<div class="modal fade" id="aiScanModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-robot me-2"></i>AI Condition Scan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-center py-4" id="aiScanLoading">
          <i class="fas fa-spinner fa-spin fa-2x text-success mb-3 d-block"></i>
          <p class="text-muted">Analyzing image for damage...</p>
        </div>
        <div id="aiScanResult" style="display:none"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var canvas = null;
  var currentPhotoId = null;
  var annotatorModal = null;

  // Annotate button handler
  document.querySelectorAll('[data-action="annotate"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      currentPhotoId = this.dataset.photoId;
      var imgSrc = this.dataset.imageSrc;

      if (!annotatorModal) {
        annotatorModal = new bootstrap.Modal(document.getElementById('annotatorModal'));
      }
      annotatorModal.show();

      setTimeout(function() {
        var container = document.getElementById('annotator-container');
        container.innerHTML = '<canvas id="ann-canvas"></canvas>';

        fabric.Image.fromURL(imgSrc, function(img) {
          var scale = Math.min(container.clientWidth / img.width, container.clientHeight / img.height);
          canvas = new fabric.Canvas('ann-canvas', {
            width: container.clientWidth,
            height: container.clientHeight,
            backgroundColor: '#1a1a2e',
          });
          img.set({ left: 0, top: 0, scaleX: scale, scaleY: scale, selectable: false, evented: false });
          canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));

          // Load existing annotations
          fetch('/condition/api/annotation/' + currentPhotoId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data.annotations) {
                var objects = typeof data.annotations === 'string' ? JSON.parse(data.annotations) : data.annotations;
                if (Array.isArray(objects)) {
                  objects.forEach(function(obj) {
                    var rect = new fabric.Rect({
                      left: obj.left || 0, top: obj.top || 0,
                      width: obj.width || 50, height: obj.height || 50,
                      fill: 'transparent', stroke: obj.stroke || '#ff0000', strokeWidth: 2,
                      damageType: obj.damageType || 'other',
                    });
                    canvas.add(rect);
                  });
                  canvas.renderAll();
                }
              }
            }).catch(function() {});
        }, { crossOrigin: 'anonymous' });
      }, 300);
    });
  });

  // Add rectangle annotation
  document.getElementById('ann-tool-rect').addEventListener('click', function() {
    if (!canvas) return;
    var color = document.getElementById('ann-color').value;
    var label = document.getElementById('ann-damage-type').value;
    var rect = new fabric.Rect({
      left: 100, top: 100, width: 80, height: 60,
      fill: 'transparent', stroke: color, strokeWidth: 2,
      damageType: label,
    });
    canvas.add(rect);
    canvas.setActiveObject(rect);
  });

  // Add circle annotation
  document.getElementById('ann-tool-circle').addEventListener('click', function() {
    if (!canvas) return;
    var color = document.getElementById('ann-color').value;
    var label = document.getElementById('ann-damage-type').value;
    var circle = new fabric.Circle({
      left: 100, top: 100, radius: 40,
      fill: 'transparent', stroke: color, strokeWidth: 2,
      damageType: label,
    });
    canvas.add(circle);
    canvas.setActiveObject(circle);
  });

  // Save annotations
  document.getElementById('ann-save').addEventListener('click', function() {
    if (!canvas || !currentPhotoId) return;
    var annotations = [];
    canvas.getObjects().forEach(function(obj) {
      if (obj === canvas.backgroundImage) return;
      annotations.push({
        type: obj.type, left: Math.round(obj.left), top: Math.round(obj.top),
        width: Math.round(obj.width * (obj.scaleX || 1)),
        height: Math.round(obj.height * (obj.scaleY || 1)),
        stroke: obj.stroke, damageType: obj.damageType || 'other',
      });
    });

    fetch('/condition/api/annotation/' + currentPhotoId, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: JSON.stringify({ annotations: annotations }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        annotatorModal.hide();
        location.reload();
      } else {
        alert(data.error || 'Failed to save');
      }
    })
    .catch(function(err) { alert('Error: ' + err.message); });
  });

  // AI Scan handler
  document.querySelectorAll('[data-action="ai-scan"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var photoId = this.dataset.photoId;
      var modal = new bootstrap.Modal(document.getElementById('aiScanModal'));
      document.getElementById('aiScanLoading').style.display = '';
      document.getElementById('aiScanResult').style.display = 'none';
      modal.show();

      fetch('/admin/ai/condition/assess?photo_id=' + photoId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.getElementById('aiScanLoading').style.display = 'none';
        var el = document.getElementById('aiScanResult');
        el.style.display = '';
        if (!data.success) {
          el.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Scan failed') + '</div>';
          return;
        }
        var html = '<div class="text-center mb-3"><span class="badge bg-success fs-5">' + (data.overall_rating || '—') + '</span></div>';
        if (data.description) html += '<p>' + data.description + '</p>';
        if (data.damage_types && data.damage_types.length) {
          html += '<h6>Damages (' + data.damage_types.length + ')</h6><ul class="list-group mb-2">';
          data.damage_types.forEach(function(d) {
            html += '<li class="list-group-item py-1 d-flex justify-content-between"><span>' + (d.type || '').replace(/_/g,' ') + '</span><small class="text-muted">' + (d.severity || '') + '</small></li>';
          });
          html += '</ul>';
        }
        if (data.recommendations) html += '<p class="small text-muted">' + data.recommendations + '</p>';
        el.innerHTML = html;
      })
      .catch(function(err) {
        document.getElementById('aiScanLoading').style.display = 'none';
        document.getElementById('aiScanResult').style.display = '';
        document.getElementById('aiScanResult').innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
      });
    });
  });
});
</script>
@endsection
