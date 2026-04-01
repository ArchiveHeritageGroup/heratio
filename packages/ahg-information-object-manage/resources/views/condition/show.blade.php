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

{{-- Annotation Modal (AtoM ConditionAnnotator) --}}
<div class="modal fade" id="annotatorModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-draw-polygon me-2"></i>Annotate Photo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="annotator-container" class="condition-annotator-container"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" data-action="save-annotations">
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

{{-- CSS --}}
<link rel="stylesheet" href="{{ asset('vendor/ahg-theme-b5/css/condition-annotator.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/ahg-theme-b5/css/condition-photos.css') }}">

{{-- JS: Fabric.js + AtoM ConditionAnnotator + condition-photos --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="{{ asset('vendor/ahg-theme-b5/js/condition-annotator.js') }}"></script>

<script>
window.AHG_CONDITION = window.AHG_CONDITION || {};
window.AHG_CONDITION.checkId = {{ (int)$report->id }};
window.AHG_CONDITION.objectId = {{ (int)$report->information_object_id }};
window.AHG_CONDITION.confirmDelete = 'Are you sure you want to delete this photo?';

(function() {
  'use strict';
  var currentAnnotator = null;
  var annotatorModal = null;

  document.addEventListener('DOMContentLoaded', function() {
    var modalEl = document.getElementById('annotatorModal');
    if (modalEl) annotatorModal = new bootstrap.Modal(modalEl);

    // Handle all data-action clicks
    document.addEventListener('click', function(e) {
      var target = e.target.closest('[data-action]');
      if (!target) return;
      var action = target.dataset.action;
      var photoId = target.dataset.photoId;
      var imageSrc = target.dataset.imageSrc;

      if (action === 'annotate') openAnnotator(photoId, imageSrc);
      if (action === 'save-annotations') saveAnnotations();
      if (action === 'ai-scan') openAiScan(photoId);
    });
  });

  function openAnnotator(photoId, imageSrc) {
    if (!photoId || !imageSrc) return;
    if (currentAnnotator) {
      currentAnnotator.destroy();
      currentAnnotator = null;
    }
    var modalEl = document.getElementById('annotatorModal');
    if (!modalEl || !annotatorModal) return;

    var initOnce = function() {
      modalEl.removeEventListener('shown.bs.modal', initOnce);
      currentAnnotator = new ConditionAnnotator('annotator-container', {
        photoId: photoId,
        imageUrl: imageSrc,
        readonly: false,
        showToolbar: true,
        saveUrl: '/condition/api/annotation/' + photoId,
        getUrl: '/condition/api/annotation/' + photoId,
      });
    };
    modalEl.addEventListener('shown.bs.modal', initOnce);
    annotatorModal.show();
  }

  function saveAnnotations() {
    if (!currentAnnotator) return;
    currentAnnotator.save().then(function() {
      annotatorModal.hide();
      location.reload();
    }).catch(function(err) {
      alert('Save failed: ' + (err.message || err));
    });
  }

  function openAiScan(photoId) {
    var modal = new bootstrap.Modal(document.getElementById('aiScanModal'));
    document.getElementById('aiScanLoading').style.display = '';
    document.getElementById('aiScanResult').style.display = 'none';
    modal.show();

    fetch('/admin/ai/condition/assess?photo_id=' + photoId, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(function(r) {
      if (!r.ok || (r.headers.get('content-type') || '').indexOf('json') === -1) {
        throw new Error('AI Condition service unavailable (HTTP ' + r.status + ')');
      }
      return r.json();
    })
    .then(function(data) {
      document.getElementById('aiScanLoading').style.display = 'none';
      var el = document.getElementById('aiScanResult');
      el.style.display = '';
      if (!data.success) {
        el.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Scan failed') + '</div>';
        return;
      }
      var gradeColors = {excellent:'success',good:'info',fair:'warning',poor:'danger',critical:'dark'};
      var grade = data.overall_rating || 'unknown';
      var html = '<div class="text-center mb-3"><span class="badge bg-' + (gradeColors[grade]||'secondary') + ' fs-5">' + grade.charAt(0).toUpperCase() + grade.slice(1) + '</span></div>';
      if (data.description) html += '<p>' + data.description + '</p>';
      if (data.damage_types && data.damage_types.length) {
        html += '<h6>Damages (' + data.damage_types.length + ')</h6><ul class="list-group mb-2">';
        data.damage_types.forEach(function(d) {
          html += '<li class="list-group-item py-1 d-flex justify-content-between"><span>' + (d.type||'').replace(/_/g,' ') + '</span><small class="text-muted">' + (d.severity||'') + '</small></li>';
        });
        html += '</ul>';
      }
      if (data.recommendations) html += '<p class="small text-muted">' + data.recommendations + '</p>';
      el.innerHTML = html;
    })
    .catch(function(err) {
      document.getElementById('aiScanLoading').style.display = 'none';
      var el = document.getElementById('aiScanResult');
      el.style.display = '';
      el.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
  }
})();
</script>
@endsection
