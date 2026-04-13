{{--
  Heratio — AI Condition / Model Training page
  Copyright (c) 2026 Johan Pieterse / Plain Sailing (Pty) Ltd
  Licensed under the GNU Affero General Public License v3.0 (AGPL-3.0)
  Cloned from PSIS ahgAiConditionPlugin/templates/trainingSuccess.php
--}}
@extends('theme::layouts.2col')

@section('title', __('Model Training'))
@section('body-class', 'ai ai-condition ai-condition-training')

@section('sidebar')
  <div class="sidebar-content">
    <div class="card mb-3">
      <div class="card-header bg-info text-white py-2">
        <h6 class="mb-0"><i class="fas fa-brain me-1"></i>{{ __('Model Training') }}</h6>
      </div>
      <div class="card-body py-2 small">
        <p class="text-muted mb-2">{{ __('Upload labeled training data and train the damage detection model to improve accuracy for your collections.') }}</p>
        <a href="{{ route('admin.ai.condition.dashboard') }}" class="btn btn-sm btn-outline-secondary w-100 mb-2">
          <i class="fas fa-cog me-1"></i>{{ __('Back to Settings') }}
        </a>
        <a href="{{ route('admin.ai.condition.browse') }}" class="btn btn-sm btn-outline-primary w-100">
          <i class="fas fa-list me-1"></i>{{ __('Browse Assessments') }}
        </a>
      </div>
    </div>
  </div>
@endsection

@section('title-block')
  <h1 class="h3 mb-0"><i class="fas fa-brain me-2"></i>{{ __('Model Training') }}</h1>
  <p class="text-muted small mb-3">{{ __('Upload labeled images and train the damage detection model') }}</p>
@endsection

@section('content')

  {{-- Row 1: Model Info + Training Status --}}
  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-cube me-1"></i>{{ __('Model Info') }}</h6></div>
        <div class="card-body" id="modelInfoBody">
          <div class="text-center text-muted small py-3">
            <i class="fas fa-spinner fa-spin me-1"></i>{{ __('Loading model info...') }}
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-tasks me-1"></i>{{ __('Training Status') }}</h6></div>
        <div class="card-body" id="trainingStatusBody">
          <div class="text-center text-muted small py-3">
            <i class="fas fa-spinner fa-spin me-1"></i>{{ __('Loading status...') }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Row 2: Upload Training Data --}}
  <div class="card mb-3">
    <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-upload me-1"></i>{{ __('Upload Training Data') }}</h6></div>
    <div class="card-body">
      <div class="alert alert-info small py-2 mb-3">
        <i class="fas fa-info-circle me-1"></i>
        {!! __('Expected format: ZIP file containing an <code>images/</code> directory (JPG/PNG files) and an <code>annotations/</code> directory (JSON files with damage type and bounding box coordinates).') !!}
      </div>
      <div id="dropZone" class="border border-2 border-dashed rounded p-4 text-center mb-3" style="cursor:pointer;border-color:#adb5bd !important">
        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2 d-block"></i>
        <p class="text-muted mb-1">{{ __('Drag and drop a ZIP file here, or click to browse') }}</p>
        <input type="file" id="trainingFile" accept=".zip" style="display:none">
      </div>
      <div id="uploadProgress" style="display:none">
        <div class="progress mb-2" style="height:6px">
          <div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadProgressBar" style="width:0%"></div>
        </div>
        <p class="text-center small text-muted" id="uploadProgressText">{{ __('Uploading...') }}</p>
      </div>
      <div id="uploadResult" style="display:none"></div>
    </div>
  </div>

  {{-- Row 3: Available Datasets --}}
  <div class="card mb-3">
    <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-database me-1"></i>{{ __('Available Datasets') }}</h6></div>
    <div class="card-body p-0" id="datasetsBody">
      <div class="p-3 text-center text-muted small">
        <i class="fas fa-spinner fa-spin me-1"></i>{{ __('Loading datasets...') }}
      </div>
    </div>
  </div>

  {{-- Row 4: Training Configuration --}}
  <div class="card mb-3">
    <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-sliders-h me-1"></i>{{ __('Training Configuration') }}</h6></div>
    <div class="card-body">
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Epochs') }}</label>
        <div class="col-sm-9">
          <input type="number" class="form-control form-control-sm" id="trainEpochs" value="100" min="1" max="1000">
          <div class="form-text">{{ __('Number of training iterations (higher = longer but potentially more accurate)') }}</div>
        </div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Batch Size') }}</label>
        <div class="col-sm-9">
          <input type="number" class="form-control form-control-sm" id="trainBatchSize" value="16" min="1" max="128">
          <div class="form-text">{{ __('Images per training batch (reduce if running out of GPU memory)') }}</div>
        </div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Image Size') }}</label>
        <div class="col-sm-9">
          <select class="form-select form-select-sm" id="trainImageSize">
            <option value="320">320px</option>
            <option value="416">416px</option>
            <option value="512">512px</option>
            <option value="640" selected>640px ({{ __('default') }})</option>
            <option value="800">800px</option>
            <option value="1024">1024px</option>
          </select>
          <div class="form-text">{{ __('Input image resolution for training (larger = more detail but slower)') }}</div>
        </div>
      </div>
      <button type="button" class="btn btn-primary" id="startTrainingBtn" disabled>
        <i class="fas fa-play me-1"></i>{{ __('Start Training') }}
      </button>
      <span class="small text-muted ms-2" id="trainHint">{{ __('Select a dataset from the table above to enable training.') }}</span>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function() {
  var selectedDatasetId = null;
  var CSRF = '{{ csrf_token() }}';
  var URLS = {
    modelInfo: '{{ url('/admin/ai/condition/api/training/model-info') }}',
    status:    '{{ url('/admin/ai/condition/api/training/status') }}',
    upload:    '{{ url('/admin/ai/condition/api/training/upload') }}',
    datasets:  '{{ url('/admin/ai/condition/api/training/datasets') }}',
    start:     '{{ url('/admin/ai/condition/api/training/start') }}'
  };

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = str == null ? '' : String(str);
    return d.innerHTML;
  }

  // --- Load Model Info ---
  function loadModelInfo() {
    fetch(URLS.modelInfo, {headers: {'Accept': 'application/json'}})
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var el = document.getElementById('modelInfoBody');
        if (!data.success) {
          el.innerHTML = '<div class="alert alert-warning py-1 small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>' + esc(data.error || 'Could not load model info') + '</div>';
          return;
        }
        var d = data.data || {};
        var statusBadge = d.loaded
          ? '<span class="badge bg-success">{{ __('Loaded') }}</span>'
          : '<span class="badge bg-warning text-dark">{{ __('Not Found') }}</span>';

        el.innerHTML = '<table class="table table-sm table-borderless mb-0 small">'
          + '<tr><td class="text-muted">{{ __('Status') }}</td><td>' + statusBadge + '</td></tr>'
          + '<tr><td class="text-muted">{{ __('File Size') }}</td><td>' + esc(d.file_size || '--') + '</td></tr>'
          + '<tr><td class="text-muted">{{ __('Last Modified') }}</td><td>' + esc(d.last_modified || '--') + '</td></tr>'
          + '<tr><td class="text-muted">{{ __('Damage Classes') }}</td><td><span class="badge bg-secondary">15</span></td></tr>'
          + '</table>';
      })
      .catch(function() {
        document.getElementById('modelInfoBody').innerHTML = '<div class="alert alert-danger py-1 small mb-0"><i class="fas fa-times me-1"></i>{{ __('Network error') }}</div>';
      });
  }

  // --- Load Training Status ---
  function loadTrainingStatus() {
    fetch(URLS.status, {headers: {'Accept': 'application/json'}})
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var el = document.getElementById('trainingStatusBody');
        if (!data.success) {
          el.innerHTML = '<div class="alert alert-warning py-1 small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>' + esc(data.error || 'Could not load status') + '</div>';
          return;
        }
        var d = data.data || {};
        var statusColors = {idle:'secondary',preparing:'info',training:'primary',completed:'success',failed:'danger'};
        var status = d.status || 'idle';
        var html = '<table class="table table-sm table-borderless mb-0 small">';
        html += '<tr><td class="text-muted">{{ __('Status') }}</td><td><span class="badge bg-' + (statusColors[status] || 'secondary') + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span></td></tr>';

        if (status === 'training' && d.current_epoch != null && d.total_epochs) {
          var pct = Math.round((d.current_epoch / d.total_epochs) * 100);
          html += '<tr><td class="text-muted">{{ __('Progress') }}</td><td>'
            + '<div class="progress" style="height:6px"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:' + pct + '%"></div></div>'
            + '<span class="small">' + d.current_epoch + ' / ' + d.total_epochs + ' {{ __('epochs') }}</span>'
            + '</td></tr>';
        }

        if (status === 'completed' && d.metrics) {
          var m = d.metrics;
          html += '<tr><td class="text-muted">mAP</td><td><strong>' + (m.mAP != null ? parseFloat(m.mAP).toFixed(3) : '--') + '</strong></td></tr>';
          html += '<tr><td class="text-muted">{{ __('Precision') }}</td><td>' + (m.precision != null ? parseFloat(m.precision).toFixed(3) : '--') + '</td></tr>';
          html += '<tr><td class="text-muted">{{ __('Recall') }}</td><td>' + (m.recall != null ? parseFloat(m.recall).toFixed(3) : '--') + '</td></tr>';
        }

        if (d.started_at) {
          html += '<tr><td class="text-muted">{{ __('Started') }}</td><td>' + esc(d.started_at) + '</td></tr>';
        }
        if (d.completed_at) {
          html += '<tr><td class="text-muted">{{ __('Completed') }}</td><td>' + esc(d.completed_at) + '</td></tr>';
        }

        html += '</table>';
        el.innerHTML = html;

        if (status === 'training' || status === 'preparing') {
          setTimeout(loadTrainingStatus, 5000);
        }
      })
      .catch(function() {
        document.getElementById('trainingStatusBody').innerHTML = '<div class="alert alert-danger py-1 small mb-0"><i class="fas fa-times me-1"></i>{{ __('Network error') }}</div>';
      });
  }

  // --- Drag-and-drop upload ---
  var dropZone = document.getElementById('dropZone');
  var trainingFile = document.getElementById('trainingFile');

  dropZone.addEventListener('click', function() { trainingFile.click(); });
  dropZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('border-primary'); });
  dropZone.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('border-primary'); });
  dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-primary');
    if (e.dataTransfer.files.length) {
      trainingFile.files = e.dataTransfer.files;
      uploadTrainingData(e.dataTransfer.files[0]);
    }
  });
  trainingFile.addEventListener('change', function() {
    if (this.files[0]) { uploadTrainingData(this.files[0]); }
  });

  function uploadTrainingData(file) {
    if (!file.name.toLowerCase().endsWith('.zip')) {
      alert('{{ __('Please upload a ZIP file.') }}');
      return;
    }

    var formData = new FormData();
    formData.append('training_file', file);
    formData.append('_token', CSRF);

    document.getElementById('uploadProgress').style.display = '';
    document.getElementById('uploadResult').style.display = 'none';
    document.getElementById('uploadProgressBar').style.width = '0%';
    document.getElementById('uploadProgressText').textContent = '{{ __('Uploading...') }}';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', URLS.upload);
    xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
    xhr.setRequestHeader('Accept', 'application/json');

    xhr.upload.addEventListener('progress', function(e) {
      if (e.lengthComputable) {
        var pct = Math.round((e.loaded / e.total) * 100);
        document.getElementById('uploadProgressBar').style.width = pct + '%';
        document.getElementById('uploadProgressText').textContent = pct + '% {{ __('uploaded') }}';
      }
    });

    xhr.addEventListener('load', function() {
      document.getElementById('uploadProgressBar').style.width = '100%';
      var resultEl = document.getElementById('uploadResult');
      resultEl.style.display = '';

      try {
        var data = JSON.parse(xhr.responseText);
        if (data.success) {
          var ds = data.data || {};
          resultEl.innerHTML = '<div class="alert alert-success py-2 small">'
            + '<i class="fas fa-check me-1"></i>{{ __('Dataset uploaded successfully.') }}'
            + (ds.images != null ? ' {{ __('Images') }}: <strong>' + ds.images + '</strong>,' : '')
            + (ds.annotations != null ? ' {{ __('Annotations') }}: <strong>' + ds.annotations + '</strong>' : '')
            + '</div>';
          document.getElementById('uploadProgress').style.display = 'none';
          loadDatasets();
        } else {
          resultEl.innerHTML = '<div class="alert alert-danger py-2 small"><i class="fas fa-times me-1"></i>' + esc(data.error || 'Upload failed') + '</div>';
        }
      } catch (e) {
        resultEl.innerHTML = '<div class="alert alert-danger py-2 small"><i class="fas fa-times me-1"></i>{{ __('Invalid response from server') }}</div>';
      }
    });

    xhr.addEventListener('error', function() {
      document.getElementById('uploadProgressText').textContent = '{{ __('Upload failed') }}';
      var resultEl = document.getElementById('uploadResult');
      resultEl.style.display = '';
      resultEl.innerHTML = '<div class="alert alert-danger py-2 small"><i class="fas fa-times me-1"></i>{{ __('Network error during upload') }}</div>';
    });

    xhr.send(formData);
  }

  // --- Load Datasets ---
  function loadDatasets() {
    fetch(URLS.datasets, {headers: {'Accept': 'application/json'}})
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var el = document.getElementById('datasetsBody');
        if (!data.success || !data.data || !data.data.length) {
          el.innerHTML = '<div class="p-3 text-center text-muted small"><i class="fas fa-info-circle me-1"></i>{{ __('No training datasets available. Upload a ZIP file above.') }}</div>';
          return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">';
        html += '<thead class="table-light"><tr>'
          + '<th>{{ __('Dataset ID') }}</th>'
          + '<th class="text-center">{{ __('Images') }}</th>'
          + '<th class="text-center">{{ __('Annotations') }}</th>'
          + '<th>{{ __('Created') }}</th>'
          + '<th class="text-end">{{ __('Actions') }}</th>'
          + '</tr></thead><tbody>';

        data.data.forEach(function(ds) {
          var isSelected = selectedDatasetId === ds.id;
          html += '<tr' + (isSelected ? ' class="table-primary"' : '') + '>'
            + '<td><code class="small">' + esc(String(ds.id)) + '</code></td>'
            + '<td class="text-center"><span class="badge bg-secondary">' + (ds.images || 0) + '</span></td>'
            + '<td class="text-center"><span class="badge bg-secondary">' + (ds.annotations || 0) + '</span></td>'
            + '<td class="small">' + esc(ds.created_at || '--') + '</td>'
            + '<td class="text-end">'
            + '<button type="button" class="btn btn-sm ' + (isSelected ? 'btn-primary' : 'btn-outline-primary') + ' me-1" data-action="select" data-id="' + esc(String(ds.id)) + '">'
            + '<i class="fas fa-check me-1"></i>' + (isSelected ? '{{ __('Selected') }}' : '{{ __('Use for Training') }}')
            + '</button>'
            + '<button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-id="' + esc(String(ds.id)) + '">'
            + '<i class="fas fa-trash"></i>'
            + '</button>'
            + '</td></tr>';
        });

        html += '</tbody></table></div>';
        el.innerHTML = html;

        el.querySelectorAll('button[data-action="select"]').forEach(function(b) {
          b.addEventListener('click', function() { selectDataset(this.getAttribute('data-id')); });
        });
        el.querySelectorAll('button[data-action="delete"]').forEach(function(b) {
          b.addEventListener('click', function() { deleteDataset(this.getAttribute('data-id')); });
        });
      })
      .catch(function() {
        document.getElementById('datasetsBody').innerHTML = '<div class="p-3 text-center text-danger small"><i class="fas fa-times me-1"></i>{{ __('Failed to load datasets') }}</div>';
      });
  }

  function selectDataset(id) {
    selectedDatasetId = id;
    document.getElementById('startTrainingBtn').disabled = false;
    document.getElementById('trainHint').textContent = '{{ __('Dataset') }} #' + id + ' {{ __('selected.') }}';
    loadDatasets();
  }

  function deleteDataset(id) {
    if (!confirm('{{ __('Delete this dataset? This cannot be undone.') }}')) return;
    fetch(URLS.datasets + '?dataset_id=' + encodeURIComponent(id), {
      method: 'DELETE',
      headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          if (selectedDatasetId === id) {
            selectedDatasetId = null;
            document.getElementById('startTrainingBtn').disabled = true;
            document.getElementById('trainHint').textContent = '{{ __('Select a dataset from the table above to enable training.') }}';
          }
          loadDatasets();
        } else {
          alert(data.error || '{{ __('Failed to delete dataset') }}');
        }
      });
  }

  // --- Start Training ---
  document.getElementById('startTrainingBtn').addEventListener('click', function() {
    if (!selectedDatasetId) {
      alert('{{ __('Please select a dataset first.') }}');
      return;
    }

    var params = 'dataset_id=' + encodeURIComponent(selectedDatasetId)
      + '&epochs=' + encodeURIComponent(document.getElementById('trainEpochs').value)
      + '&batch_size=' + encodeURIComponent(document.getElementById('trainBatchSize').value)
      + '&image_size=' + encodeURIComponent(document.getElementById('trainImageSize').value)
      + '&_token=' + encodeURIComponent(CSRF);

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>{{ __('Starting...') }}';

    var btn = this;
    fetch(URLS.start, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      body: params
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play me-1"></i>{{ __('Start Training') }}';

        if (data.success) {
          loadTrainingStatus();
          loadModelInfo();
        } else {
          alert(data.error || '{{ __('Failed to start training') }}');
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play me-1"></i>{{ __('Start Training') }}';
        alert('{{ __('Network error') }}');
      });
  });

  // --- Initial load ---
  loadModelInfo();
  loadTrainingStatus();
  loadDatasets();
})();
</script>
@endpush
