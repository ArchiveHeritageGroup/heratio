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

{{-- Action buttons --}}
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="{{ route('backup.settings') }}" class="btn btn-outline-secondary">
      <i class="fas fa-cog me-1"></i>Settings
    </a>
  </div>
  <div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
      <i class="fas fa-plus me-1"></i>Create Backup
    </button>
  </div>
</div>

<div class="row">
  {{-- Left Column: Info Cards --}}
  <div class="col-md-4">
    {{-- Database Info --}}
    <div class="card mb-4">
      <div class="card-header" >
        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Info</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li><strong>Host:</strong> {{ $dbConfig['host'] ?? $dbConfig['unix_socket'] ?? 'localhost' }}</li>
          <li><strong>Database:</strong> {{ $dbConfig['database'] ?? 'N/A' }}</li>
          <li><strong>User:</strong> {{ $dbConfig['username'] ?? 'N/A' }}</li>
          <li><strong>Port:</strong> {{ $dbConfig['port'] ?? 3306 }}</li>
        </ul>
        <hr>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-test-connection" onclick="testConnection()">
          <i class="fas fa-plug me-1"></i>Test Connection
        </button>
        <span id="connection-status" class="ms-2">
          @if($dbConnected)
            <span class="text-success"><i class="fas fa-check"></i> Connected</span>
          @else
            <span class="text-danger"><i class="fas fa-times"></i> Disconnected</span>
          @endif
        </span>
      </div>
    </div>

    {{-- Storage Info --}}
    <div class="card mb-4">
      <div class="card-header" >
        <h5 class="mb-0"><i class="fas fa-folder me-2"></i>Storage</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li><strong>Path:</strong> <code class="small">{{ $backupPath }}</code></li>
          <li><strong>Backups:</strong> {{ $backupCount }}</li>
          <li><strong>Total Size:</strong> {{ $totalSize }}</li>
          <li><strong>Max Backups:</strong> {{ $maxBackups }}</li>
          <li><strong>Retention:</strong> {{ $retentionDays }} days</li>
        </ul>
      </div>
    </div>

    {{-- Quick Actions --}}
    <div class="card mb-4">
      <div class="card-header" >
        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dbBackupModal">
            <i class="fas fa-database me-1"></i>Database Only
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#fullBackupModal">
            <i class="fas fa-archive me-1"></i>Full Backup
          </button>
          <button type="button" class="btn btn-outline-info btn-sm" id="btn-incremental-backup">
            <i class="fas fa-layer-group me-1"></i>Incremental Backup
          </button>
        </div>
      </div>
    </div>

    {{-- Scheduled Backups --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center" >
        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Schedules</h5>
        <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#createScheduleModal">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      <div class="card-body p-0">
        @if(count($schedules) > 0)
          <ul class="list-group list-group-flush">
            @foreach($schedules as $schedule)
              <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <div>
                  <strong>{{ $schedule['name'] ?? 'Unnamed' }}</strong>
                  <br><small class="text-muted">
                    {{ ucfirst($schedule['frequency'] ?? 'daily') }}
                    @if(($schedule['frequency'] ?? '') === 'weekly' && isset($schedule['day_of_week']))
                      &mdash; {{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][(int)$schedule['day_of_week']] ?? '' }}
                    @elseif(($schedule['frequency'] ?? '') === 'monthly' && isset($schedule['day_of_month']))
                      &mdash; Day {{ (int)$schedule['day_of_month'] }}
                    @endif
                    @ {{ substr($schedule['time'] ?? '02:00', 0, 5) }}
                    &middot; {{ (int)($schedule['retention_days'] ?? 30) }}d retention
                  </small>
                  @if(!empty($schedule['last_run']))
                    <br><small class="text-muted">Last: {{ $schedule['last_run'] }}</small>
                  @endif
                </div>
                <div class="btn-group btn-group-sm">
                  <span class="btn btn-sm {{ !empty($schedule['enabled']) ? 'btn-success' : 'btn-outline-secondary' }}" title="{{ !empty($schedule['enabled']) ? 'Active' : 'Paused' }}">
                    <i class="fas {{ !empty($schedule['enabled']) ? 'fa-check' : 'fa-pause' }}"></i>
                  </span>
                </div>
              </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted text-center py-3 mb-0">No schedules configured</p>
        @endif
      </div>
      <div class="card-footer small text-muted">
        <i class="fas fa-info-circle me-1"></i>Cron: <code>0 * * * * cd {{ base_path() }} && php artisan backup:run-scheduled</code>
      </div>
    </div>
  </div>

  {{-- Right Column: Backups List --}}
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center" >
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Backups</h5>
        <span class="badge bg-secondary">{{ $backupCount }}</span>
      </div>
      <div class="card-body p-0">
        @if(count($backups) > 0)
          <div class="table-responsive">
            <table class="table table-hover mb-0">
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
                            <span class="badge bg-success me-1" title="Database"><i class="fas fa-database"></i></span>
                            @break
                          @case('uploads')
                            <span class="badge bg-warning text-dark me-1" title="Uploads"><i class="fas fa-images"></i></span>
                            @break
                          @case('plugins')
                            <span class="badge bg-info me-1" title="Plugins"><i class="fas fa-puzzle-piece"></i></span>
                            @break
                          @case('framework')
                            <span class="badge bg-secondary me-1" title="Framework"><i class="fas fa-code"></i></span>
                            @break
                        @endswitch
                      @endforeach
                    </td>
                    <td>{{ $backup['size_human'] }}</td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <a href="{{ route('backup.restore') }}?backup_id={{ $backup['id'] }}" class="btn btn-outline-success" title="Restore">
                          <i class="fas fa-undo"></i>
                        </a>
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
          <div class="text-center text-muted py-5">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p>No backups found</p>
            <button type="button" class="btn btn-primary btn-quick-backup" onclick="quickBackup('database')">
              <i class="fas fa-plus me-1"></i>Create First Backup
            </button>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Create Backup Modal --}}
<div class="modal fade" id="createBackupModal" tabindex="-1" aria-labelledby="createBackupModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createBackupModalLabel"><i class="fas fa-plus me-2"></i>Create Backup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Select components to include in this backup:</p>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="backup-database" checked>
          <label class="form-check-label" for="backup-database">
            <i class="fas fa-database me-1 text-success"></i>Database
            <small class="text-muted">(Required)</small>
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="backup-uploads">
          <label class="form-check-label" for="backup-uploads">
            <i class="fas fa-images me-1 text-warning"></i>Uploads
            <small class="text-muted">(Digital objects)</small>
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="backup-plugins">
          <label class="form-check-label" for="backup-plugins">
            <i class="fas fa-puzzle-piece me-1 text-info"></i>Custom Plugins
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="backup-framework">
          <label class="form-check-label" for="backup-framework">
            <i class="fas fa-code me-1 text-secondary"></i>Framework
          </label>
        </div>
        <div id="backup-progress" class="mt-3 d-none">
          <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="backup-progress-bar" style="width: 100%"></div>
          </div>
          <small class="text-muted mt-1 d-block" id="backup-status">Creating backup...</small>
        </div>
        <div id="backup-result" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-start-backup">
          <i class="fas fa-play me-1"></i>Start Backup
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Database Only Backup Modal --}}
<div class="modal fade" id="dbBackupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-database me-2"></i>Database Backup</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Select components to include:</p>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="db-opt-database" checked disabled>
          <label class="form-check-label" for="db-opt-database">
            <i class="fas fa-database me-1 text-success"></i>Database
            <small class="text-muted">(Required)</small>
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="db-opt-uploads">
          <label class="form-check-label" for="db-opt-uploads">
            <i class="fas fa-images me-1 text-warning"></i>Uploads / Digital Objects
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="db-opt-plugins">
          <label class="form-check-label" for="db-opt-plugins">
            <i class="fas fa-puzzle-piece me-1 text-info"></i>Custom Plugins
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="db-opt-framework">
          <label class="form-check-label" for="db-opt-framework">
            <i class="fas fa-code me-1 text-secondary"></i>Framework
          </label>
        </div>
        <div id="db-backup-progress" class="mt-3 d-none">
          <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
          </div>
          <small class="text-muted mt-1 d-block">Creating backup...</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-db-backup">
          <i class="fas fa-play me-1"></i>Start Backup
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Full Backup Modal --}}
<div class="modal fade" id="fullBackupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="fas fa-archive me-2"></i>Full Backup</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Select components to include:</p>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="full-opt-database" checked disabled>
          <label class="form-check-label" for="full-opt-database">
            <i class="fas fa-database me-1 text-success"></i>Database
            <small class="text-muted">(Required)</small>
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="full-opt-uploads" checked>
          <label class="form-check-label" for="full-opt-uploads">
            <i class="fas fa-images me-1 text-warning"></i>Uploads / Digital Objects
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="full-opt-plugins" checked>
          <label class="form-check-label" for="full-opt-plugins">
            <i class="fas fa-puzzle-piece me-1 text-info"></i>Custom Plugins
          </label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="full-opt-framework" checked>
          <label class="form-check-label" for="full-opt-framework">
            <i class="fas fa-code me-1 text-secondary"></i>Framework
          </label>
        </div>
        <hr>
        <div class="d-flex justify-content-between">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="full-select-all">Select All</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="full-select-none">Deselect All</button>
        </div>
        <div id="full-backup-progress" class="mt-3 d-none">
          <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-dark" style="width: 100%"></div>
          </div>
          <small class="text-muted mt-1 d-block">Creating backup...</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-dark" id="btn-full-backup">
          <i class="fas fa-play me-1"></i>Start Backup
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Create Schedule Modal --}}
<div class="modal fade" id="createScheduleModal" tabindex="-1" aria-labelledby="createScheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createScheduleModalLabel"><i class="fas fa-clock me-2"></i>Create Backup Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="sched-name">Name</label>
          <input type="text" class="form-control" id="sched-name" value="Daily Database Backup" required>
        </div>
        <div class="row mb-3">
          <div class="col-6">
            <label class="form-label" for="sched-frequency">Frequency</label>
            <select class="form-select" id="sched-frequency">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
              <option value="hourly">Hourly</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label" for="sched-time">Time</label>
            <input type="time" class="form-control" id="sched-time" value="02:00">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-6" id="sched-dow-group" style="display:none">
            <label class="form-label" for="sched-dow">Day of Week</label>
            <select class="form-select" id="sched-dow">
              <option value="0">Sunday</option>
              <option value="1">Monday</option>
              <option value="2">Tuesday</option>
              <option value="3">Wednesday</option>
              <option value="4">Thursday</option>
              <option value="5">Friday</option>
              <option value="6">Saturday</option>
            </select>
          </div>
          <div class="col-6" id="sched-dom-group" style="display:none">
            <label class="form-label" for="sched-dom">Day of Month</label>
            <input type="number" class="form-control" id="sched-dom" min="1" max="28" value="1">
          </div>
          <div class="col-6">
            <label class="form-label" for="sched-retention">Retention (days)</label>
            <input type="number" class="form-control" id="sched-retention" min="1" max="365" value="30">
          </div>
        </div>
        <hr>
        <p class="text-muted small mb-2">Components to include:</p>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" id="sched-db" value="1" checked>
          <label class="form-check-label" for="sched-db"><i class="fas fa-database text-success"></i> DB</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" id="sched-uploads" value="1">
          <label class="form-check-label" for="sched-uploads"><i class="fas fa-images text-warning"></i> Uploads</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" id="sched-plugins" value="1" checked>
          <label class="form-check-label" for="sched-plugins"><i class="fas fa-puzzle-piece text-info"></i> Plugins</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" id="sched-fw" value="1" checked>
          <label class="form-check-label" for="sched-fw"><i class="fas fa-code text-secondary"></i> Framework</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Schedule</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('js')
<script>
function testConnection() {
  var btn = document.getElementById('btn-test-connection');
  var status = document.getElementById('connection-status');
  btn.disabled = true;
  status.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

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
    status.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Connected</span>';
  })
  .catch(function() {
    status.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Error</span>';
  })
  .finally(function() { btn.disabled = false; });
}

function quickBackup(component) {
  var components = [component];
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

document.addEventListener('DOMContentLoaded', function() {
  // Create backup modal - start button
  document.getElementById('btn-start-backup')?.addEventListener('click', function() {
    var components = ['database']; // Always included
    if (document.getElementById('backup-uploads')?.checked) components.push('uploads');
    if (document.getElementById('backup-plugins')?.checked) components.push('plugins');
    if (document.getElementById('backup-framework')?.checked) components.push('framework');
    runBackup(components);
  });

  // Database backup from modal
  document.getElementById('btn-db-backup')?.addEventListener('click', function() {
    var components = ['database'];
    if (document.getElementById('db-opt-uploads')?.checked) components.push('uploads');
    if (document.getElementById('db-opt-plugins')?.checked) components.push('plugins');
    if (document.getElementById('db-opt-framework')?.checked) components.push('framework');
    document.getElementById('db-backup-progress').classList.remove('d-none');
    this.disabled = true;
    var btn = this;
    var originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';

    fetch('{{ route("backup.create") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ components: components }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) { location.reload(); }
      else { alert(data.message || 'Backup failed'); }
    })
    .catch(function(e) { alert('Error: ' + e.message); })
    .finally(function() { btn.innerHTML = originalText; btn.disabled = false; });
  });

  // Full backup from modal
  document.getElementById('btn-full-backup')?.addEventListener('click', function() {
    var components = ['database'];
    if (document.getElementById('full-opt-uploads')?.checked) components.push('uploads');
    if (document.getElementById('full-opt-plugins')?.checked) components.push('plugins');
    if (document.getElementById('full-opt-framework')?.checked) components.push('framework');
    document.getElementById('full-backup-progress').classList.remove('d-none');
    this.disabled = true;
    var btn = this;
    var originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';

    fetch('{{ route("backup.create") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ components: components }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) { location.reload(); }
      else { alert(data.message || 'Backup failed'); }
    })
    .catch(function(e) { alert('Error: ' + e.message); })
    .finally(function() { btn.innerHTML = originalText; btn.disabled = false; });
  });

  // Full backup select all/none
  document.getElementById('full-select-all')?.addEventListener('click', function() {
    ['full-opt-uploads', 'full-opt-plugins', 'full-opt-framework'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.checked = true;
    });
  });
  document.getElementById('full-select-none')?.addEventListener('click', function() {
    ['full-opt-uploads', 'full-opt-plugins', 'full-opt-framework'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.checked = false;
    });
  });

  // Incremental backup
  document.getElementById('btn-incremental-backup')?.addEventListener('click', function() {
    if (!confirm('Create an incremental backup? This includes only changes since the last full backup.')) return;
    var btn = this;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';
    btn.disabled = true;

    fetch('{{ route("backup.create") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ components: ['database', 'uploads', 'plugins', 'framework'] }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) { location.reload(); }
      else { alert(data.message || 'Backup failed'); }
    })
    .catch(function(e) { alert('Error: ' + e.message); })
    .finally(function() { btn.innerHTML = '<i class="fas fa-layer-group me-1"></i>Incremental Backup'; btn.disabled = false; });
  });

  // Schedule frequency toggle
  document.getElementById('sched-frequency')?.addEventListener('change', function() {
    document.getElementById('sched-dow-group').style.display = this.value === 'weekly' ? '' : 'none';
    document.getElementById('sched-dom-group').style.display = this.value === 'monthly' ? '' : 'none';
  });
});
</script>
@endpush
