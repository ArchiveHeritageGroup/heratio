@extends('theme::layouts.1col')
@section('title', 'Restore from Backup')
@section('body-class', 'admin backup restore')

@section('content')
<div class="multiline-header d-flex align-items-center mb-3">
  <i class="fas fa-3x fa-undo me-3" aria-hidden="true"></i>
  <div class="d-flex flex-column">
    <h1 class="mb-0">{{ __('Restore from Backup') }}</h1>
    <span class="small text-muted">{{ __('Restore data from an existing backup') }}</span>
  </div>
</div>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Admin</a></li>
    <li class="breadcrumb-item"><a href="{{ route('backup.index') }}">Backup &amp; Restore</a></li>
    <li class="breadcrumb-item active" aria-current="page">Restore</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('backup.index') }}" class="btn atom-btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Backups') }}
    </a>
    <a href="{{ route('backup.settings') }}" class="btn atom-btn-outline-secondary ms-2">
      <i class="fas fa-cog me-1"></i> {{ __('Settings') }}
    </a>
    <a href="{{ route('backup.index') }}#upload" class="btn atom-btn-outline-primary ms-2">
      <i class="fas fa-upload me-1"></i> {{ __('Upload Backup') }}
    </a>
  </div>
  <div>
    <a href="{{ route('backup.index') }}" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i> {{ __('Create Backup') }}
    </a>
  </div>
</div>

@isset($dbConfig)
<div class="row mb-4">
  <div class="col-md-4">
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Info</h5></div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li><strong>{{ __('Host:') }}</strong> {{ $dbConfig['host'] ?? 'localhost' }}</li>
          <li><strong>{{ __('Database:') }}</strong> {{ $dbConfig['database'] ?? '' }}</li>
          <li><strong>{{ __('User:') }}</strong> {{ $dbConfig['username'] ?? '' }}</li>
          <li><strong>{{ __('Port:') }}</strong> {{ $dbConfig['port'] ?? 3306 }}</li>
        </ul>
        <hr>
        <span class="badge bg-{{ ($dbConnected ?? false) ? 'success' : 'danger' }}">
          <i class="fas fa-{{ ($dbConnected ?? false) ? 'check' : 'times' }}"></i>
          {{ ($dbConnected ?? false) ? 'Connected' : 'Not connected' }}
        </span>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-folder me-2"></i>Storage</h5></div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li><strong>{{ __('Path:') }}</strong> <code class="small">{{ $backupPath ?? '' }}</code></li>
          <li><strong>{{ __('Backups:') }}</strong> {{ $backupCount ?? count($backups) }}</li>
          <li><strong>{{ __('Total Size:') }}</strong> {{ $totalSize ?? '' }}</li>
          <li><strong>{{ __('Max Backups:') }}</strong> {{ $maxBackups ?? '' }}</li>
          <li><strong>{{ __('Retention:') }}</strong> {{ $retentionDays ?? '' }} days</li>
        </ul>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5></div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="{{ route('backup.index') }}?quick=database" class="btn atom-btn-outline-primary btn-sm">
            <i class="fas fa-database me-1"></i> {{ __('Database Only') }}
          </a>
          <a href="{{ route('backup.index') }}?quick=full" class="btn atom-btn-outline-secondary btn-sm">
            <i class="fas fa-archive me-1"></i> {{ __('Full Backup') }}
          </a>
          <a href="{{ route('backup.index') }}?quick=incremental" class="btn atom-btn-outline-info btn-sm">
            <i class="fas fa-layer-group me-1"></i> {{ __('Incremental Backup') }}
          </a>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Schedules</h5>
        <a href="{{ route('backup.settings') }}" class="btn btn-sm atom-btn-outline-secondary"><i class="fas fa-plus"></i></a>
      </div>
      <div class="card-body p-0">
        @if (empty($schedules))
          <p class="text-muted text-center py-3 mb-0">No schedules configured</p>
        @else
          <ul class="list-group list-group-flush">
            @foreach ($schedules as $sched)
              <li class="list-group-item py-2">
                <strong>{{ $sched->name ?? 'Schedule' }}</strong>
                <br><small class="text-muted">
                  {{ ucfirst($sched->frequency ?? 'daily') }} @ {{ substr($sched->time ?? '02:00', 0, 5) }}
                  · {{ (int) ($sched->retention_days ?? 30) }}d retention
                </small>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  </div>

  <div class="col-md-8">
@endisset

<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle me-1"></i>
  <strong>{{ __('Warning:') }}</strong> Restoring will overwrite existing data. This cannot be undone. Make sure you have a current backup before proceeding.
</div>

@if(count($backups) > 0)
<div class="card mb-4">
  <div class="card-header" ><i class="fas fa-file-archive me-1"></i> Select Backup</div>
  <div class="card-body">
    <div class="mb-3">
      <label for="backup-select" class="form-label">Available Backups <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
      <select class="form-select" id="backup-select" onchange="onBackupSelected()">
        <option value="">-- Select a backup --</option>
        @foreach($backups as $backup)
          <option value="{{ $backup['id'] }}"
                  data-components="{{ implode(',', $backup['components']) }}"
                  data-filename="{{ $backup['filename'] }}"
                  data-size="{{ $backup['size_human'] }}"
                  data-date="{{ $backup['date'] }}">
            {{ $backup['date'] }} - {{ $backup['filename'] }} ({{ $backup['size_human'] }})
          </option>
        @endforeach
      </select>
    </div>

    {{-- Backup details --}}
    <div id="backup-details" class="d-none">
      <div class="card bg-light mb-3">
        <div class="card-body">
          <h6 class="card-title">{{ __('Backup Details') }}</h6>
          <table class="table table-bordered table-sm table-borderless mb-0">
            <tr>
              <td class="text-muted" style="width:100px;">File</td>
              <td id="detail-filename"></td>
            </tr>
            <tr>
              <td class="text-muted">Date</td>
              <td id="detail-date"></td>
            </tr>
            <tr>
              <td class="text-muted">Size</td>
              <td id="detail-size"></td>
            </tr>
            <tr>
              <td class="text-muted">Contains</td>
              <td id="detail-components"></td>
            </tr>
          </table>
        </div>
      </div>

      <h6>{{ __('Select Components to Restore') }}</h6>
      <div class="mb-3">
        <div class="form-check mb-2" id="restore-comp-database-wrap" style="display:none;">
          <input class="form-check-input restore-component" type="checkbox" id="restore-comp-database" value="database">
          <label class="form-check-label" for="restore-comp-database">
            <i class="fas fa-database text-primary me-1"></i> Database
           <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <div class="form-text">Restore the MySQL database from the backup dump.</div>
        </div>
        <div class="form-check mb-2" id="restore-comp-uploads-wrap" style="display:none;">
          <input class="form-check-input restore-component" type="checkbox" id="restore-comp-uploads" value="uploads">
          <label class="form-check-label" for="restore-comp-uploads">
            <i class="fas fa-upload text-info me-1"></i> Uploads
           <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <div class="form-text">Restore uploaded digital objects and files.</div>
        </div>
        <div class="form-check mb-2" id="restore-comp-plugins-wrap" style="display:none;">
          <input class="form-check-input restore-component" type="checkbox" id="restore-comp-plugins" value="plugins">
          <label class="form-check-label" for="restore-comp-plugins">
            <i class="fas fa-puzzle-piece text-warning me-1"></i> Plugins
           <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <div class="form-text">Restore all packages from the backup.</div>
        </div>
        <div class="form-check mb-2" id="restore-comp-framework-wrap" style="display:none;">
          <input class="form-check-input restore-component" type="checkbox" id="restore-comp-framework" value="framework">
          <label class="form-check-label" for="restore-comp-framework">
            <i class="fas fa-code text-secondary me-1"></i> Framework
           <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <div class="form-text">Restore application framework files.</div>
        </div>
      </div>

      {{-- Progress --}}
      <div id="restore-progress" class="d-none mb-3">
        <div class="progress mb-2">
          <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="restore-progress-bar">0%</div>
        </div>
        <div id="restore-status" class="small text-muted"></div>
      </div>

      {{-- Result --}}
      <div id="restore-result" class="d-none mb-3"></div>

      <button type="button" class="btn btn-outline-danger" id="btn-start-restore" onclick="confirmRestore()" disabled>
        <i class="fas fa-undo me-1"></i> {{ __('Restore Selected Components') }}
      </button>
    </div>
  </div>
</div>
@else
  <div class="card">
    <div class="card-body text-center py-5 text-muted">
      <i class="fas fa-3x fa-box-open mb-3 d-block"></i>
      <p class="mb-2">No backups available for restore.</p>
      <a href="{{ route('backup.index') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> {{ __('Create a Backup First') }}
      </a>
    </div>
  </div>
@endif

@isset($dbConfig)
  </div>
</div>
@endisset

@endsection

@push('js')
<script>
function onBackupSelected() {
  var select = document.getElementById('backup-select');
  var details = document.getElementById('backup-details');
  var option = select.options[select.selectedIndex];

  if (!option.value) {
    details.classList.add('d-none');
    return;
  }

  details.classList.remove('d-none');

  document.getElementById('detail-filename').textContent = option.dataset.filename;
  document.getElementById('detail-date').textContent = option.dataset.date;
  document.getElementById('detail-size').textContent = option.dataset.size;

  var components = option.dataset.components.split(',');
  var compHtml = '';
  components.forEach(function(c) {
    switch (c) {
      case 'database':
        compHtml += '<span class="badge bg-primary me-1"><i class="fas fa-database"></i> DB</span>';
        break;
      case 'uploads':
        compHtml += '<span class="badge bg-info me-1"><i class="fas fa-upload"></i> Uploads</span>';
        break;
      case 'plugins':
        compHtml += '<span class="badge bg-warning text-dark me-1"><i class="fas fa-puzzle-piece"></i> Plugins</span>';
        break;
      case 'framework':
        compHtml += '<span class="badge bg-secondary me-1"><i class="fas fa-code"></i> Framework</span>';
        break;
    }
  });
  document.getElementById('detail-components').innerHTML = compHtml;

  // Show/hide component checkboxes
  ['database', 'uploads', 'plugins', 'framework'].forEach(function(comp) {
    var wrap = document.getElementById('restore-comp-' + comp + '-wrap');
    var cb = document.getElementById('restore-comp-' + comp);
    if (components.indexOf(comp) !== -1) {
      wrap.style.display = '';
      cb.checked = false;
    } else {
      wrap.style.display = 'none';
      cb.checked = false;
    }
  });

  updateRestoreButton();
}

// Enable/disable restore button based on checkbox selection
document.querySelectorAll('.restore-component').forEach(function(cb) {
  cb.addEventListener('change', updateRestoreButton);
});

function updateRestoreButton() {
  var checked = document.querySelectorAll('.restore-component:checked');
  document.getElementById('btn-start-restore').disabled = (checked.length === 0);
}

function confirmRestore() {
  var checked = [];
  document.querySelectorAll('.restore-component:checked').forEach(function(cb) {
    checked.push(cb.value);
  });

  if (checked.length === 0) {
    alert('Please select at least one component to restore.');
    return;
  }

  var msg = 'You are about to restore the following components:\n\n' +
    checked.join(', ').toUpperCase() +
    '\n\nThis will OVERWRITE existing data and CANNOT be undone.\n\nAre you sure you want to proceed?';

  if (!confirm(msg)) {
    return;
  }

  startRestore(checked);
}

function startRestore(components) {
  var backupId = document.getElementById('backup-select').value;
  var progressDiv = document.getElementById('restore-progress');
  var progressBar = document.getElementById('restore-progress-bar');
  var statusDiv = document.getElementById('restore-status');
  var resultDiv = document.getElementById('restore-result');
  var restoreBtn = document.getElementById('btn-start-restore');

  progressDiv.classList.remove('d-none');
  resultDiv.classList.add('d-none');
  resultDiv.innerHTML = '';
  restoreBtn.disabled = true;

  // Simulate progress
  var progress = 0;
  var interval = setInterval(function() {
    progress += Math.random() * 10;
    if (progress > 85) progress = 85;
    progressBar.style.width = Math.round(progress) + '%';
    progressBar.textContent = Math.round(progress) + '%';
  }, 800);

  statusDiv.textContent = 'Restoring: ' + components.join(', ') + '...';

  fetch('{{ route("backup.doRestore") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ backup_id: backupId, components: components }),
  })
  .then(function(response) { return response.json(); })
  .then(function(data) {
    clearInterval(interval);
    progressBar.style.width = '100%';
    progressBar.textContent = '100%';
    progressBar.classList.remove('progress-bar-animated');

    if (data.success) {
      progressBar.classList.add('bg-success');
      statusDiv.textContent = data.message;

      var html = '<div class="alert alert-success"><strong>Restore completed!</strong>';
      if (data.restored && data.restored.length > 0) {
        html += '<ul class="mb-0 mt-1">';
        data.restored.forEach(function(r) { html += '<li>Restored: ' + r + '</li>'; });
        html += '</ul>';
      }
      if (data.errors && data.errors.length > 0) {
        html += '<hr><strong>Warnings:</strong><ul class="mb-0">';
        data.errors.forEach(function(e) { html += '<li class="text-warning">' + e + '</li>'; });
        html += '</ul>';
      }
      html += '</div>';
      resultDiv.innerHTML = html;
      resultDiv.classList.remove('d-none');
    } else {
      progressBar.classList.add('bg-danger');
      statusDiv.textContent = 'Restore failed.';

      var html = '<div class="alert alert-danger"><strong>Restore failed.</strong>';
      if (data.errors && data.errors.length > 0) {
        html += '<ul class="mb-0 mt-1">';
        data.errors.forEach(function(e) { html += '<li>' + e + '</li>'; });
        html += '</ul>';
      }
      html += '</div>';
      resultDiv.innerHTML = html;
      resultDiv.classList.remove('d-none');
    }
    restoreBtn.disabled = false;
  })
  .catch(function(err) {
    clearInterval(interval);
    progressBar.style.width = '100%';
    progressBar.classList.add('bg-danger');
    statusDiv.textContent = 'An error occurred.';
    resultDiv.innerHTML = '<div class="alert alert-danger">An unexpected error occurred. Please check the server logs.</div>';
    resultDiv.classList.remove('d-none');
    restoreBtn.disabled = false;
  });
}
</script>
@endpush
