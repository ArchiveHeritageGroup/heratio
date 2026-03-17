@extends('theme::layouts.1col')
@section('title', 'Backup & Restore')
@section('body-class', 'admin backup')

@section('content')
<div class="multiline-header d-flex align-items-center mb-3">
  <i class="fas fa-3x fa-database me-3" aria-hidden="true"></i>
  <div class="d-flex flex-column">
    <h1 class="mb-0">Backup & Restore</h1>
    <span class="small text-muted">Manage database and file backups</span>
  </div>
</div>

{{-- Info Cards --}}
<div class="row g-3 mb-4">
  {{-- Database Info --}}
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-server me-1"></i> Database Info</div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-2">
          <tr>
            <td class="text-muted" style="width:90px;">Host</td>
            <td><code>{{ $dbConfig['host'] ?? $dbConfig['unix_socket'] ?? 'localhost' }}</code></td>
          </tr>
          <tr>
            <td class="text-muted">Database</td>
            <td><code>{{ $dbConfig['database'] ?? 'N/A' }}</code></td>
          </tr>
          <tr>
            <td class="text-muted">User</td>
            <td><code>{{ $dbConfig['username'] ?? 'N/A' }}</code></td>
          </tr>
          <tr>
            <td class="text-muted">Status</td>
            <td>
              @if($dbConnected)
                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Connected</span>
              @else
                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Disconnected</span>
              @endif
            </td>
          </tr>
        </table>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-test-connection" onclick="testConnection()">
          <i class="fas fa-plug me-1"></i> Test Connection
        </button>
      </div>
    </div>
  </div>

  {{-- Storage Info --}}
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-hdd me-1"></i> Storage Info</div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <td class="text-muted" style="width:90px;">Path</td>
            <td><code class="small">{{ $backupPath }}</code></td>
          </tr>
          <tr>
            <td class="text-muted">Backups</td>
            <td><strong>{{ $backupCount }}</strong></td>
          </tr>
          <tr>
            <td class="text-muted">Total Size</td>
            <td><strong>{{ $totalSize }}</strong></td>
          </tr>
          <tr>
            <td class="text-muted">Max Keep</td>
            <td>{{ $maxBackups }}</td>
          </tr>
          <tr>
            <td class="text-muted">Retention</td>
            <td>{{ $retentionDays }} days</td>
          </tr>
        </table>
      </div>
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="fas fa-bolt me-1"></i> Quick Actions</div>
      <div class="card-body d-flex flex-column gap-2">
        <button type="button" class="btn btn-primary" onclick="quickBackup('database')">
          <i class="fas fa-database me-1"></i> Database Backup
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createBackupModal">
          <i class="fas fa-archive me-1"></i> Full Backup
        </button>
        <a href="{{ route('backup.restore') }}" class="btn btn-warning">
          <i class="fas fa-undo me-1"></i> Restore
        </a>
        <a href="{{ route('backup.settings') }}" class="btn btn-outline-secondary">
          <i class="fas fa-cog me-1"></i> Settings
        </a>
      </div>
    </div>
  </div>
</div>

{{-- Backups Table --}}
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list me-1"></i> Existing Backups</span>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
      <i class="fas fa-plus me-1"></i> Create Backup
    </button>
  </div>
  <div class="card-body p-0">
    @if(count($backups) > 0)
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Components</th>
              <th>Size</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($backups as $backup)
              <tr id="backup-row-{{ $backup['id'] }}">
                <td>
                  <i class="fas fa-clock text-muted me-1"></i>
                  {{ $backup['date'] }}
                </td>
                <td>
                  @switch($backup['type'])
                    @case('full')
                      <span class="badge bg-success">Full</span>
                      @break
                    @case('database')
                      <span class="badge bg-primary">Database</span>
                      @break
                    @case('uploads')
                      <span class="badge bg-info">Uploads</span>
                      @break
                    @case('plugins')
                      <span class="badge bg-warning text-dark">Plugins</span>
                      @break
                    @case('framework')
                      <span class="badge bg-secondary">Framework</span>
                      @break
                    @default
                      <span class="badge bg-dark">Unknown</span>
                  @endswitch
                </td>
                <td>
                  @foreach($backup['components'] as $comp)
                    @switch($comp)
                      @case('database')
                        <span class="badge bg-primary bg-opacity-25 text-primary" title="Database"><i class="fas fa-database"></i> DB</span>
                        @break
                      @case('uploads')
                        <span class="badge bg-info bg-opacity-25 text-info" title="Uploads"><i class="fas fa-upload"></i> Uploads</span>
                        @break
                      @case('plugins')
                        <span class="badge bg-warning bg-opacity-25 text-warning" title="Plugins"><i class="fas fa-puzzle-piece"></i> Plugins</span>
                        @break
                      @case('framework')
                        <span class="badge bg-secondary bg-opacity-25 text-secondary" title="Framework"><i class="fas fa-code"></i> Framework</span>
                        @break
                    @endswitch
                  @endforeach
                </td>
                <td>{{ $backup['size_human'] }}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('backup.download', $backup['id']) }}" class="btn btn-outline-primary" title="Download">
                      <i class="fas fa-download"></i>
                    </a>
                    <button type="button" class="btn btn-outline-danger" title="Delete" onclick="deleteBackup('{{ $backup['id'] }}', '{{ $backup['filename'] }}')">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="text-center py-5 text-muted">
        <i class="fas fa-3x fa-box-open mb-3 d-block"></i>
        <p class="mb-0">No backups found. Create your first backup using the button above.</p>
      </div>
    @endif
  </div>
</div>

{{-- Scheduled Backups --}}
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-calendar-alt me-1"></i> Scheduled Backups
  </div>
  <div class="card-body">
    @if(count($schedules) > 0)
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>Frequency</th>
            <th>Time</th>
            <th>Components</th>
            <th>Enabled</th>
          </tr>
        </thead>
        <tbody>
          @foreach($schedules as $schedule)
            <tr>
              <td>{{ $schedule['frequency'] ?? 'N/A' }}</td>
              <td>{{ $schedule['time'] ?? 'N/A' }}</td>
              <td>{{ implode(', ', $schedule['components'] ?? []) }}</td>
              <td>
                @if(!empty($schedule['enabled']))
                  <span class="badge bg-success">Enabled</span>
                @else
                  <span class="badge bg-secondary">Disabled</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="text-muted mb-0">No scheduled backups configured. Use the <a href="{{ route('backup.settings') }}">settings</a> page to configure scheduled backups.</p>
    @endif
  </div>
</div>

{{-- Create Backup Modal --}}
<div class="modal fade" id="createBackupModal" tabindex="-1" aria-labelledby="createBackupModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createBackupModalLabel"><i class="fas fa-archive me-1"></i> Create Backup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3">Select the components to include in the backup:</p>
        <div class="mb-3">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="comp-database" value="database" checked disabled>
            <label class="form-check-label" for="comp-database">
              <i class="fas fa-database text-primary me-1"></i> Database <span class="badge bg-secondary">Required</span>
            </label>
            <div class="form-text">MySQL database dump (compressed)</div>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="comp-uploads" value="uploads">
            <label class="form-check-label" for="comp-uploads">
              <i class="fas fa-upload text-info me-1"></i> Uploads
            </label>
            <div class="form-text">Digital object files and uploads</div>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="comp-plugins" value="plugins">
            <label class="form-check-label" for="comp-plugins">
              <i class="fas fa-puzzle-piece text-warning me-1"></i> Plugins
            </label>
            <div class="form-text">All packages in the packages/ directory</div>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="comp-framework" value="framework">
            <label class="form-check-label" for="comp-framework">
              <i class="fas fa-code text-secondary me-1"></i> Framework
            </label>
            <div class="form-text">Application framework files (excludes vendor, node_modules)</div>
          </div>
        </div>
        {{-- Progress --}}
        <div id="backup-progress" class="d-none">
          <div class="progress mb-2">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="backup-progress-bar">0%</div>
          </div>
          <div id="backup-status" class="small text-muted"></div>
        </div>
        {{-- Result --}}
        <div id="backup-result" class="d-none"></div>
      </div>
      <div class="modal-footer" id="backup-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-start-backup" onclick="startBackup()">
          <i class="fas fa-play me-1"></i> Start Backup
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
function testConnection() {
  var btn = document.getElementById('btn-test-connection');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing...';

  fetch('{{ route("backup.create") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ components: [], _test_connection: true }),
  })
  .then(function() {
    btn.innerHTML = '<i class="fas fa-check text-success me-1"></i> Connected';
    setTimeout(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-plug me-1"></i> Test Connection';
    }, 2000);
  })
  .catch(function() {
    btn.innerHTML = '<i class="fas fa-times text-danger me-1"></i> Failed';
    setTimeout(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-plug me-1"></i> Test Connection';
    }, 2000);
  });
}

function quickBackup(component) {
  var components = [component];
  runBackup(components);
}

function startBackup() {
  var components = ['database']; // Always included
  if (document.getElementById('comp-uploads').checked) components.push('uploads');
  if (document.getElementById('comp-plugins').checked) components.push('plugins');
  if (document.getElementById('comp-framework').checked) components.push('framework');

  runBackup(components);
}

function runBackup(components) {
  var progressDiv = document.getElementById('backup-progress');
  var progressBar = document.getElementById('backup-progress-bar');
  var statusDiv = document.getElementById('backup-status');
  var resultDiv = document.getElementById('backup-result');
  var startBtn = document.getElementById('btn-start-backup');

  if (progressDiv) progressDiv.classList.remove('d-none');
  if (resultDiv) {
    resultDiv.classList.add('d-none');
    resultDiv.innerHTML = '';
  }
  if (startBtn) startBtn.disabled = true;

  // Simulate progress
  var progress = 0;
  var interval = setInterval(function() {
    progress += Math.random() * 15;
    if (progress > 90) progress = 90;
    if (progressBar) {
      progressBar.style.width = Math.round(progress) + '%';
      progressBar.textContent = Math.round(progress) + '%';
    }
  }, 500);

  if (statusDiv) statusDiv.textContent = 'Creating backup for: ' + components.join(', ') + '...';

  fetch('{{ route("backup.create") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ components: components }),
  })
  .then(function(response) { return response.json(); })
  .then(function(data) {
    clearInterval(interval);
    if (progressBar) {
      progressBar.style.width = '100%';
      progressBar.textContent = '100%';
      progressBar.classList.remove('progress-bar-animated');
    }

    if (data.success) {
      if (progressBar) progressBar.classList.add('bg-success');
      if (statusDiv) statusDiv.textContent = data.message;

      var html = '<div class="alert alert-success mt-2 mb-0"><strong>Backup created successfully!</strong><ul class="mb-0 mt-1">';
      if (data.files) {
        data.files.forEach(function(f) {
          html += '<li>' + f.component + ': ' + f.filename + ' (' + f.size + ')</li>';
        });
      }
      html += '</ul>';
      if (data.errors && data.errors.length > 0) {
        html += '<hr><strong>Warnings:</strong><ul class="mb-0">';
        data.errors.forEach(function(e) { html += '<li class="text-warning">' + e + '</li>'; });
        html += '</ul>';
      }
      html += '</div>';
      if (resultDiv) {
        resultDiv.innerHTML = html;
        resultDiv.classList.remove('d-none');
      }
      setTimeout(function() { location.reload(); }, 3000);
    } else {
      if (progressBar) progressBar.classList.add('bg-danger');
      if (statusDiv) statusDiv.textContent = 'Backup failed.';

      var html = '<div class="alert alert-danger mt-2 mb-0"><strong>Backup failed.</strong>';
      if (data.errors && data.errors.length > 0) {
        html += '<ul class="mb-0 mt-1">';
        data.errors.forEach(function(e) { html += '<li>' + e + '</li>'; });
        html += '</ul>';
      }
      html += '</div>';
      if (resultDiv) {
        resultDiv.innerHTML = html;
        resultDiv.classList.remove('d-none');
      }
    }
    if (startBtn) startBtn.disabled = false;
  })
  .catch(function(err) {
    clearInterval(interval);
    if (progressBar) {
      progressBar.style.width = '100%';
      progressBar.classList.add('bg-danger');
    }
    if (statusDiv) statusDiv.textContent = 'An error occurred.';
    if (resultDiv) {
      resultDiv.innerHTML = '<div class="alert alert-danger mt-2 mb-0">An unexpected error occurred. Please check the server logs.</div>';
      resultDiv.classList.remove('d-none');
    }
    if (startBtn) startBtn.disabled = false;
  });
}

function deleteBackup(id, filename) {
  if (!confirm('Are you sure you want to delete backup "' + filename + '"? This cannot be undone.')) {
    return;
  }

  fetch('/admin/backup/' + id, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
  })
  .then(function(response) { return response.json(); })
  .then(function(data) {
    if (data.success) {
      var row = document.getElementById('backup-row-' + id);
      if (row) row.remove();
    } else {
      alert(data.message || 'Failed to delete backup.');
    }
  })
  .catch(function() {
    alert('An error occurred while deleting the backup.');
  });
}
</script>
@endpush
