@extends('theme::layouts.1col')

@section('title', 'RiC Sync Dashboard')
@section('body-class', 'admin ric')

@section('content')
  {{-- Title bar with RIC Explorer button --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-project-diagram me-1"></i> RiC Sync Dashboard</h1>
    <a href="{{ route('ric.explorer') }}" target="_blank" class="btn btn-outline-info btn-sm">
      <i class="fas fa-external-link-alt"></i> RiC Explorer
    </a>
  </div>

  {{-- Status Cards Row --}}
  <div class="row g-3 mb-4">
    {{-- Fuseki Status --}}
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle text-muted mb-1">Fuseki Status</h6>
              <h3 class="mb-0 {{ ($fusekiStatus['online'] ?? false) ? 'text-success' : 'text-danger' }}">
                {{ ($fusekiStatus['online'] ?? false) ? 'Online' : 'Offline' }}
              </h3>
            </div>
            <div class="fs-1 {{ ($fusekiStatus['online'] ?? false) ? 'text-success' : 'text-danger' }}">
              <i class="fas fa-{{ ($fusekiStatus['online'] ?? false) ? 'check-circle' : 'times-circle' }}"></i>
            </div>
          </div>
          @if($fusekiStatus['online'] ?? false)
            <p class="mb-0 mt-2 text-muted small">
              @if(isset($fusekiStatus['triple_count']))
                <i class="fas fa-database"></i> {{ number_format($fusekiStatus['triple_count']) }} triples
              @elseif(!empty($fusekiStatus['has_data']))
                <i class="fas fa-check"></i> Connected with data
              @else
                <i class="fas fa-check"></i> Connected
              @endif
            </p>
          @endif
        </div>
      </div>
    </div>

    {{-- Queue Status --}}
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle text-muted mb-1">Queue</h6>
              <h3 class="mb-0" id="queue-count">
                <span class="spinner-border spinner-border-sm text-muted"></span>
              </h3>
            </div>
            <div class="fs-1 text-primary"><i class="fas fa-list-ol"></i></div>
          </div>
          <p class="mb-0 mt-2" id="queue-badges"></p>
        </div>
        <a href="{{ route('ric.queue') }}" class="card-footer text-decoration-none text-center small">
          Manage queue <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>

    {{-- Orphaned Triples --}}
    <div class="col-md-3">
      <div class="card h-100" id="orphan-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle text-muted mb-1">Orphaned Triples</h6>
              <h3 class="mb-0" id="orphan-count">
                <span class="spinner-border spinner-border-sm text-muted"></span>
              </h3>
            </div>
            <div class="fs-1 text-muted" id="orphan-icon"><i class="fas fa-unlink"></i></div>
          </div>
        </div>
        <a href="{{ route('ric.orphans') }}" class="card-footer text-decoration-none text-center small">
          Manage orphans <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>

    {{-- Sync Status --}}
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="card-subtitle text-muted mb-1">Sync Status</h6>
              <h3 class="mb-0 {{ ($syncSummary['synced'] ?? 0) > 0 ? 'text-success' : 'text-secondary' }}">
                {{ ($syncSummary['synced'] ?? 0) > 0 ? 'Active' : 'Disabled' }}
              </h3>
            </div>
            <div class="fs-1 {{ ($syncSummary['synced'] ?? 0) > 0 ? 'text-success' : 'text-secondary' }}">
              <i class="fas fa-sync"></i>
            </div>
          </div>
        </div>
        <a href="{{ route('ric.config') }}" class="card-footer text-decoration-none text-center small">
          Configuration <i class="fas fa-cog"></i>
        </a>
      </div>
    </div>
  </div>

  {{-- Charts Row --}}
  <div class="row g-3 mb-4">
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header" ><h5 class="mb-0">Record Activity (7 Days)</h5></div>
        <div class="card-body position-relative" style="min-height: 200px;">
          <div id="chart-loading-1" class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2 text-muted">Loading chart...</p>
          </div>
          <canvas id="syncTrendChart" height="200" style="display:none;"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header" ><h5 class="mb-0">Operations by Type</h5></div>
        <div class="card-body position-relative" style="min-height: 200px;">
          <div id="chart-loading-2" class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2 text-muted">Loading chart...</p>
          </div>
          <canvas id="operationsChart" height="200" style="display:none;"></canvas>
        </div>
      </div>
    </div>
  </div>

  {{-- Entity Status & Quick Actions --}}
  <div class="row g-3 mb-4">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header" ><h5 class="mb-0">Entity Sync Status</h5></div>
        <div class="card-body" id="entity-status-body">
          <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2 text-muted">Loading entity status...</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center" >
          <h5 class="mb-0">Quick Actions</h5>
        </div>
        <div class="card-body">
          <button type="button" class="btn btn-success w-100 mb-2" onclick="runManualSync()" id="sync-btn" disabled title="Checking sync configuration...">
            <i class="fas fa-sync-alt"></i> Sync to Fuseki
          </button>
          <div id="sync-readiness" class="small text-muted mb-2"><i class="fas fa-spinner fa-spin"></i> Checking configuration…</div>
          <div id="sync-status" class="mb-2" style="display:none;"></div>
          <hr>
          <button type="button" class="btn btn-outline-primary w-100 mb-2" onclick="runIntegrityCheck()">
            <i class="fas fa-check-circle"></i> Run Integrity Check
          </button>
          <button type="button" class="btn btn-outline-warning w-100 mb-2" onclick="previewCleanup()">
            <i class="fas fa-search"></i> Preview Cleanup
          </button>
          <button type="button" class="btn btn-outline-danger w-100 mb-2" onclick="executeCleanup()" id="cleanup-btn" disabled>
            <i class="fas fa-trash"></i> Execute Cleanup
          </button>
          <hr>
          <button type="button" class="btn btn-outline-info w-100 mb-2" onclick="runShaclValidation()" id="shacl-btn">
            <i class="fas fa-shield-alt"></i> SHACL Validate
          </button>
          <a href="{{ route('ric.logs') }}" class="btn btn-outline-secondary w-100">
            <i class="fas fa-list"></i> View Logs
          </a>
        </div>
      </div>

      {{-- External Links --}}
      @include('ahg-ric::_external-links')

      {{-- Integrity Results --}}
      <div class="card mt-3" id="integrity-results" style="display: none;">
        <div class="card-header" ><h5 class="mb-0">Integrity Check Results</h5></div>
        <div class="card-body" id="integrity-content"></div>
      </div>

      {{-- SHACL Validation Results --}}
      <div class="card mt-3" id="shacl-results" style="display: none;">
        <div class="card-header" ><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>SHACL Validation Results</h5></div>
        <div class="card-body" id="shacl-content"></div>
      </div>
    </div>
  </div>

  {{-- Recent Operations --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center" >
      <h5 class="mb-0">Recent Operations</h5>
      <small class="text-white-50" id="last-updated"></small>
    </div>
    <div class="card-body" id="recent-ops-body">
      <div class="text-center py-4">
        <div class="spinner-border text-primary"></div>
        <p class="mt-2 text-muted">Loading recent operations...</p>
      </div>
    </div>
  </div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
let syncTrendChart = null;
let operationsChart = null;

document.addEventListener('DOMContentLoaded', function() { loadDashboardData(); });

function loadDashboardData() {
  fetch('{{ route("ric.ajax-dashboard") }}')
    .then(r => r.json())
    .then(data => {
      updateQueueStatus(data.queue_status || {});
      updateOrphanCount(data.orphan_count || 0);
      updateEntityStatus(data.sync_summary || {});
      updateRecentOperations(data.recent_operations || []);
      updateCharts(data.sync_trend || [], data.operations_by_type || {});
      document.getElementById('last-updated').textContent = 'Updated: ' + data.timestamp;
    })
    .catch(err => {
      console.error('Dashboard load failed:', err);
      document.getElementById('entity-status-body').innerHTML =
        '<div class="alert alert-danger">Failed to load data. <a href="javascript:loadDashboardData()">Retry</a></div>';
    });
}

function updateQueueStatus(status) {
  var queued = status.queued || 0;
  var processing = status.processing || 0;
  var failed = status.failed || 0;
  document.getElementById('queue-count').textContent = queued.toLocaleString();
  var badges = '';
  if (processing > 0) badges += '<span class="badge bg-warning">' + processing + ' processing</span> ';
  if (failed > 0) badges += '<span class="badge bg-danger">' + failed + ' failed</span>';
  document.getElementById('queue-badges').innerHTML = badges;
}

function updateOrphanCount(count) {
  var el = document.getElementById('orphan-count');
  var icon = document.getElementById('orphan-icon');
  var card = document.getElementById('orphan-card');
  el.textContent = count.toLocaleString();
  el.className = 'mb-0 ' + (count > 0 ? 'text-warning' : 'text-success');
  icon.className = 'fs-1 ' + (count > 0 ? 'text-warning' : 'text-success');
  card.className = 'card h-100' + (count > 0 ? ' border-warning' : '');
}

function updateEntityStatus(summary) {
  if (!summary || Object.keys(summary).length === 0) {
    document.getElementById('entity-status-body').innerHTML = '<div class="alert alert-info mb-0">No sync status data available.</div>';
    return;
  }
  var html = '<table class="table table-sm table-hover mb-0"><thead><tr><th>Entity Type</th><th class="text-center">Synced</th><th class="text-center">Pending</th><th class="text-center">Failed</th><th class="text-center">Total</th><th></th></tr></thead><tbody>';
  for (var entityType in summary) {
    var synced = 0, pending = 0, failed = 0;
    summary[entityType].forEach(function(s) {
      if (s.sync_status === 'synced') synced = s.count;
      else if (s.sync_status === 'pending') pending = s.count;
      else if (s.sync_status === 'failed') failed = s.count;
    });
    var total = synced + pending + failed;
    html += '<tr><td><code>' + entityType + '</code></td>' +
      '<td class="text-center"><span class="badge bg-success">' + synced.toLocaleString() + '</span></td>' +
      '<td class="text-center"><span class="badge bg-warning text-dark">' + pending.toLocaleString() + '</span></td>' +
      '<td class="text-center"><span class="badge bg-danger">' + failed.toLocaleString() + '</span></td>' +
      '<td class="text-center">' + total.toLocaleString() + '</td>' +
      '<td class="text-end"><a href="{{ route("ric.sync-status") }}?entity_type=' + entityType + '" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td></tr>';
  }
  html += '</tbody></table>';
  document.getElementById('entity-status-body').innerHTML = html;
}

function updateRecentOperations(ops) {
  if (!ops || ops.length === 0) {
    document.getElementById('recent-ops-body').innerHTML = '<div class="alert alert-info mb-0">No recent operations.</div>';
    return;
  }
  var html = '<table class="table table-sm table-hover mb-0"><thead><tr><th>Time</th><th>Operation</th><th>Entity</th><th>Status</th></tr></thead><tbody>';
  ops.slice(0, 10).forEach(function(op) {
    var time = op.created_at ? new Date(op.created_at).toLocaleTimeString() : '';
    var statusClass = op.status === 'completed' ? 'success' : (op.status === 'failed' ? 'danger' : 'warning');
    var entityName = op.entity_name || ((op.entity_type || '') + '/' + (op.entity_id || ''));
    html += '<tr><td class="text-muted small">' + time + '</td>' +
      '<td><span class="badge bg-secondary">' + (op.operation || '') + '</span></td>' +
      '<td><strong>' + entityName + '</strong><br><small class="text-muted">' + (op.entity_type || '') + ' #' + (op.entity_id || '') + '</small></td>' +
      '<td><span class="badge bg-' + statusClass + '">' + (op.status || '') + '</span></td></tr>';
  });
  html += '</tbody></table>';
  document.getElementById('recent-ops-body').innerHTML = html;
}

function updateCharts(trendData, opsData) {
  document.getElementById('chart-loading-1').style.display = 'none';
  document.getElementById('chart-loading-2').style.display = 'none';
  document.getElementById('syncTrendChart').style.display = 'block';
  document.getElementById('operationsChart').style.display = 'block';

  if (trendData && trendData.length > 0) {
    if (syncTrendChart) syncTrendChart.destroy();
    syncTrendChart = new Chart(document.getElementById('syncTrendChart').getContext('2d'), {
      type: 'line',
      data: {
        labels: trendData.map(function(d) { return d.date; }),
        datasets: [
          { label: 'Success', data: trendData.map(function(d) { return d.success; }), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', fill: true, tension: 0.3 },
          { label: 'Failed', data: trendData.map(function(d) { return d.failure; }), borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', fill: true, tension: 0.3 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });
  }

  if (opsData && Object.keys(opsData).length > 0) {
    if (operationsChart) operationsChart.destroy();
    operationsChart = new Chart(document.getElementById('operationsChart').getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: Object.keys(opsData),
        datasets: [{ data: Object.values(opsData), backgroundColor: ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#6c757d', '#0dcaf0'] }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
  }
}

// Auto-refresh every 30 seconds
setInterval(loadDashboardData, 30000);

// Sync readiness gate — queries /ajax-sync-readiness once on load and re-checks every 60s.
function refreshSyncReadiness() {
  var btn = document.getElementById('sync-btn');
  var note = document.getElementById('sync-readiness');
  if (!btn || !note) return;
  fetch('{{ route("ric.ajax-sync-readiness") }}')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.ready) {
        btn.disabled = false;
        btn.title = '';
        note.className = 'small text-success mb-2';
        note.innerHTML = '<i class="fas fa-check-circle"></i> Sync ready.';
      } else {
        btn.disabled = true;
        var reasons = (data.reasons || []).join(' ');
        btn.title = 'Sync disabled: ' + reasons;
        note.className = 'small text-warning mb-2';
        note.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Sync not configured: ' + reasons;
      }
    })
    .catch(function(err) {
      btn.disabled = true;
      btn.title = 'Could not verify sync configuration.';
      note.className = 'small text-danger mb-2';
      note.innerHTML = '<i class="fas fa-times-circle"></i> Readiness check failed: ' + err.message;
    });
}
refreshSyncReadiness();
setInterval(refreshSyncReadiness, 60000);

// Manual Sync
var syncLogFile = null;
var syncPollTimer = null;

function runManualSync() {
  var btn = document.getElementById('sync-btn');
  var statusDiv = document.getElementById('sync-status');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting sync...';
  statusDiv.style.display = 'block';
  statusDiv.innerHTML = '<div class="alert alert-info py-1 small mb-0"><i class="fas fa-spinner fa-spin"></i> Launching sync process...</div>';

  fetch('{{ route("ric.ajax-sync") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        syncLogFile = data.log_file;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing (PID: ' + data.pid + ')';
        statusDiv.innerHTML = '<div class="alert alert-info py-1 small mb-0"><i class="fas fa-spinner fa-spin"></i> ' + data.message + '</div>';
        syncPollTimer = setInterval(pollSyncProgress, 3000);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync to Fuseki';
        statusDiv.innerHTML = '<div class="alert alert-danger py-1 small mb-0">Error: ' + (data.error || 'Unknown') + '</div>';
      }
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync to Fuseki';
      statusDiv.innerHTML = '<div class="alert alert-danger py-1 small mb-0">Failed: ' + err.message + '</div>';
    });
}

function pollSyncProgress() {
  if (!syncLogFile) return;
  fetch('{{ route("ric.ajax-sync-progress") }}?log_file=' + encodeURIComponent(syncLogFile))
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var btn = document.getElementById('sync-btn');
      var statusDiv = document.getElementById('sync-status');
      if (!data.running) {
        clearInterval(syncPollTimer);
        syncPollTimer = null;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync to Fuseki';
        var hasError = data.output && data.output.indexOf('ERROR') !== -1;
        var alertClass = hasError ? 'alert-warning' : 'alert-success';
        statusDiv.innerHTML = '<div class="alert ' + alertClass + ' py-1 small mb-0"><i class="fas fa-' + (hasError ? 'exclamation-triangle' : 'check-circle') + '"></i> Sync complete</div>' +
          '<pre class="small mt-1 mb-0 p-2 bg-dark text-light" style="max-height:150px; overflow-y:auto; font-size:0.7rem;">' + (data.output || 'No output') + '</pre>';
        loadDashboardData();
      } else {
        var lastLine = data.output ? data.output.split('\n').pop() : 'Processing...';
        statusDiv.innerHTML = '<div class="alert alert-info py-1 small mb-0"><i class="fas fa-spinner fa-spin"></i> ' + lastLine + '</div>';
      }
    });
}

function runIntegrityCheck() {
  var btn = event.target.closest('button');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Running...';
  fetch('{{ route("ric.ajax-integrity") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Run Integrity Check';
      if (data.success) {
        showIntegrityResults(data.report);
        document.getElementById('cleanup-btn').disabled = data.report.summary.orphaned_count === 0;
      } else {
        alert('Error: ' + data.error);
      }
    });
}

function showIntegrityResults(report) {
  document.getElementById('integrity-results').style.display = 'block';
  document.getElementById('integrity-content').innerHTML =
    '<div class="mb-2"><strong>Orphaned Triples:</strong> <span class="badge ' + (report.summary.orphaned_count > 0 ? 'bg-warning' : 'bg-success') + '">' + report.summary.orphaned_count + '</span></div>' +
    '<div class="mb-2"><strong>Missing Records:</strong> <span class="badge ' + (report.summary.missing_count > 0 ? 'bg-warning' : 'bg-success') + '">' + report.summary.missing_count + '</span></div>' +
    '<div class="mb-2"><strong>Inconsistencies:</strong> <span class="badge ' + (report.summary.inconsistency_count > 0 ? 'bg-warning' : 'bg-success') + '">' + report.summary.inconsistency_count + '</span></div>' +
    '<small class="text-muted">Checked: ' + report.checked_at + '</small>';
}

function previewCleanup() {
  fetch('{{ route("ric.ajax-cleanup") }}?dry_run=1', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        alert('Preview: ' + data.stats.orphans_found + ' orphans would be removed');
        document.getElementById('cleanup-btn').disabled = data.stats.orphans_found === 0;
      }
    });
}

function executeCleanup() {
  if (!confirm('Delete all orphaned triples? This cannot be undone.')) return;
  fetch('{{ route("ric.ajax-cleanup") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        alert('Cleanup complete. Removed ' + data.stats.triples_removed + ' triples.');
        loadDashboardData();
      }
    });
}

function runShaclValidation() {
  var btn = document.getElementById('shacl-btn');
  var resultsCard = document.getElementById('shacl-results');
  var content = document.getElementById('shacl-content');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validating...';
  resultsCard.style.display = 'block';
  content.innerHTML = '<div class="text-center py-3"><div class="spinner-border"></div><p class="mt-2">Running SHACL validation against RiC data...</p></div>';

  fetch('{{ route("ric.shacl-validate") }}')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-shield-alt"></i> SHACL Validate';

      if (!data.success) {
        content.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Validation failed') + '</div>';
        return;
      }

      var html = '<div class="mb-2"><small class="text-muted">Method: ' + (data.method || 'unknown') + '</small></div>';

      if (data.note) {
        html += '<div class="alert alert-info py-1 small mb-2">' + data.note + '</div>';
      }

      var results = data.results || {};

      if (results.conforms !== undefined) {
        var badge = results.conforms
          ? '<span class="badge bg-success fs-6"><i class="fas fa-check me-1"></i>Data Conforms</span>'
          : '<span class="badge bg-warning fs-6"><i class="fas fa-exclamation-triangle me-1"></i>Issues Found</span>';
        html += '<div class="mb-3">' + badge;
        if (results.total_violations !== undefined) html += ' <span class="badge bg-danger">' + results.total_violations + ' violation(s)</span>';
        if (results.total_warnings !== undefined) html += ' <span class="badge bg-warning text-dark">' + results.total_warnings + ' warning(s)</span>';
        html += '</div>';
      }

      if (results.details && results.details.length > 0) {
        html += '<table class="table table-sm table-striped mb-0"><thead><tr><th>Severity</th><th>Shape</th><th>Message</th><th>Count</th></tr></thead><tbody>';
        results.details.forEach(function(v) {
          var sevClass = v.severity === 'Violation' ? 'text-danger fw-bold' : (v.severity === 'Warning' ? 'text-warning' : 'text-info');
          html += '<tr><td class="' + sevClass + '">' + v.severity + '</td><td><code>' + v.shape + '</code></td><td>' + v.message + '</td><td>' + v.count + '</td></tr>';
        });
        html += '</tbody></table>';
      }

      if (results.validated_at) {
        html += '<small class="text-muted d-block mt-2">Validated at: ' + results.validated_at + '</small>';
      }

      // Handle raw results from Fuseki SHACL endpoint
      if (data.results_raw) {
        html += '<pre class="bg-dark text-light p-2 mt-2 small" style="max-height:300px;overflow:auto;">' + data.results_raw.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
      }

      content.innerHTML = html;
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-shield-alt"></i> SHACL Validate';
      content.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
}
</script>
@endpush
