@extends('theme::layouts.1col')
@section('title', 'Training Data Sources — HTR')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">Vital Records HTR</a></li>
    <li class="breadcrumb-item active">Training Data Sources</li>
  </ol>
</nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-database me-2"></i>Training Data Sources</h1>

{{-- Training Stats Summary --}}
<div class="row mb-4">
  @foreach(['type_a' => 'Death Certificates', 'type_b' => 'Church/Civil Registers', 'type_c' => 'Narrative Documents'] as $type => $label)
    <div class="col-md-4">
      <div class="card">
        <div class="card-body text-center">
          <h5 class="mb-1">{{ $trainingStats[$type] ?? 0 }}</h5>
          <small class="text-muted">{{ $label }} images</small>
          @if(($trainingStats[$type] ?? 0) >= 50)
            <span class="badge bg-success ms-2">Ready</span>
          @else
            <span class="badge bg-warning ms-2">Need {{ 50 - ($trainingStats[$type] ?? 0) }} more</span>
          @endif
        </div>
      </div>
    </div>
  @endforeach
</div>

{{-- FamilySearch Credentials --}}
<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">
    <i class="fas fa-key me-2"></i>FamilySearch API Configuration
  </div>
  <div class="card-body">
    @if($fsConfigured)
      <div class="alert alert-success mb-0">
        <i class="fas fa-check-circle me-2"></i>FamilySearch API credentials are configured.
      </div>
    @else
      <div class="alert alert-warning mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>FamilySearch credentials not yet configured.
        Add to <code>.env</code> on the server:
      </div>
      <form action="{{ route('admin.ai.htr.saveFsConfig') }}" method="POST">
        @csrf
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Client ID</label>
            <input type="text" name="fs_client_id" class="form-control" placeholder="FAMILYSEARCH_CLIENT_ID" value="{{ old('fs_client_id') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Username</label>
            <input type="text" name="fs_username" class="form-control" placeholder="FAMILYSEARCH_USERNAME" value="{{ old('fs_username') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Password</label>
            <input type="password" name="fs_password" class="form-control" placeholder="FAMILYSEARCH_PASSWORD">
          </div>
        </div>
        <button type="submit" class="btn atom-btn-outline-success mt-3"><i class="fas fa-save me-1"></i>Save Credentials</button>
      </form>
    @endif
  </div>
</div>

{{-- FamilySearch Collections --}}
<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">
    <i class="fas fa-tree me-2"></i>FamilySearch Collections — SA Vital Records
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Collection</th>
          <th>Region</th>
          <th>Doc Type</th>
          <th>Downloaded</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sources as $src)
          @if($src['source'] === 'familysearch')
            <tr>
              <td>
                <strong>{{ $src['name'] }}</strong><br>
                <small class="text-muted">{{ $src['description'] }}</small><br>
                <code class="small">ID: {{ $src['collection_id'] }}</code>
              </td>
              <td><span class="badge bg-secondary">{{ $src['region'] ?? 'All' }}</span></td>
              <td>
                @if($src['doc_type'] === 'type_a')
                  <span class="badge bg-danger">Type A — Deaths</span>
                @elseif($src['doc_type'] === 'type_b')
                  <span class="badge bg-primary">Type B — Registers</span>
                @else
                  <span class="badge bg-info">Type C — Narrative</span>
                @endif
              </td>
              <td>
                <span class="fw-bold">{{ $src['downloaded'] ?? 0 }}</span> images
              </td>
              <td class="text-end">
                <div class="input-group input-group-sm justify-content-end" style="max-width:240px; margin-left:auto;">
                  <input type="number" class="form-control download-count" value="50" min="1" max="1000" data-collection="{{ $src['collection_id'] }}" data-doctype="{{ $src['doc_type'] }}">
                  <button class="btn atom-btn-outline-success btn-download" data-collection="{{ $src['collection_id'] }}" data-doctype="{{ $src['doc_type'] }}" @if(!$fsConfigured) disabled title="Configure API key first" @endif>
                    <i class="fas fa-download me-1"></i>Download
                  </button>
                </div>
              </td>
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- Internet Archive Collections --}}
<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">
    <i class="fas fa-archive me-2"></i>Internet Archive Collections
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Collection</th>
          <th>Doc Type</th>
          <th>Downloaded</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sources as $src)
          @if($src['source'] === 'internet_archive')
            <tr>
              <td>
                <strong>{{ $src['name'] }}</strong><br>
                <small class="text-muted">{{ $src['description'] }}</small><br>
                <code class="small">ID: {{ $src['collection_id'] }}</code>
              </td>
              <td><span class="badge bg-info">Type C — Narrative</span></td>
              <td><span class="fw-bold">{{ $src['downloaded'] ?? 0 }}</span> images</td>
              <td class="text-end">
                <div class="input-group input-group-sm justify-content-end" style="max-width:240px; margin-left:auto;">
                  <input type="number" class="form-control download-count" value="50" min="1" max="1000" data-collection="{{ $src['collection_id'] }}" data-doctype="{{ $src['doc_type'] }}">
                  <button class="btn atom-btn-outline-success btn-download" data-collection="{{ $src['collection_id'] }}" data-doctype="{{ $src['doc_type'] }}">
                    <i class="fas fa-download me-1"></i>Download
                  </button>
                </div>
              </td>
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- Active Download Jobs --}}
<div class="card mb-4" id="jobs-card" @if(empty($jobs)) style="display:none;" @endif>
  <div class="card-header" style="background: var(--ahg-primary); color: white;">
    <i class="fas fa-tasks me-2"></i>Download Jobs
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" id="jobs-table">
      <thead>
        <tr>
          <th>Job ID</th>
          <th>Source</th>
          <th>Collection</th>
          <th>Type</th>
          <th>Progress</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($jobs as $job)
          <tr id="job-{{ $job['id'] }}">
            <td><code>{{ $job['id'] }}</code></td>
            <td>{{ ucfirst($job['source'] ?? '') }}</td>
            <td>{{ $job['collection_id'] ?? '' }}</td>
            <td>{{ $job['doc_type'] ?? '' }}</td>
            <td>{{ $job['downloaded'] ?? 0 }} / {{ $job['requested'] ?? 0 }}</td>
            <td>
              @if(($job['status'] ?? '') === 'completed')
                <span class="badge bg-success">Completed</span>
              @elseif(($job['status'] ?? '') === 'downloading')
                <span class="badge bg-warning text-dark">Downloading...</span>
              @elseif(($job['status'] ?? '') === 'failed')
                <span class="badge bg-danger">Failed</span>
              @else
                <span class="badge bg-secondary">{{ $job['status'] ?? 'pending' }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-muted text-center">No jobs yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Quick Links --}}
<div class="d-flex gap-2 mt-3">
  <a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-outline-success">
    <i class="fas fa-pen me-1"></i>Annotate Downloaded Images
  </a>
  <a href="{{ route('admin.ai.htr.training') }}" class="btn atom-btn-outline-success">
    <i class="fas fa-graduation-cap me-1"></i>Training Status
  </a>
  <a href="{{ route('admin.ai.htr.dashboard') }}" class="btn atom-btn-white">
    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
  </a>
</div>

@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const htrUrl = @json(rtrim(env('HTR_SERVICE_URL', 'http://192.168.0.115:5006'), '/'));

  // Download button handlers
  document.querySelectorAll('.btn-download').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const collectionId = this.dataset.collection;
      const docType = this.dataset.doctype;
      const countInput = this.closest('.input-group').querySelector('.download-count');
      const count = parseInt(countInput.value) || 50;

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Queuing...';

      fetch(htrUrl + '/download-batch', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({collection_id: collectionId, count: count, doc_type: docType}),
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          btn.innerHTML = '<i class="fas fa-check me-1"></i>Queued';
          btn.classList.remove('atom-btn-outline-success');
          btn.classList.add('atom-btn-outline-success');

          // Show jobs card and add row
          document.getElementById('jobs-card').style.display = '';
          const tbody = document.querySelector('#jobs-table tbody');
          const emptyRow = tbody.querySelector('td[colspan]');
          if (emptyRow) emptyRow.closest('tr').remove();

          const tr = document.createElement('tr');
          tr.id = 'job-' + data.job_id;
          tr.innerHTML = '<td><code>' + data.job_id + '</code></td>' +
            '<td>' + (data.source || '') + '</td>' +
            '<td>' + collectionId + '</td>' +
            '<td>' + docType + '</td>' +
            '<td>0 / ' + count + '</td>' +
            '<td><span class="badge bg-warning text-dark">Downloading...</span></td>';
          tbody.prepend(tr);

          // Poll status
          pollJob(data.job_id, count);
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-download me-1"></i>Download';
        }
      })
      .catch(err => {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download me-1"></i>Download';
      });
    });
  });

  function pollJob(jobId, total) {
    const interval = setInterval(function() {
      fetch(htrUrl + '/download-status/' + jobId)
        .then(r => r.json())
        .then(data => {
          if (!data.success) { clearInterval(interval); return; }
          const job = data.job;
          const row = document.getElementById('job-' + jobId);
          if (row) {
            row.children[4].textContent = (job.downloaded || 0) + ' / ' + (job.requested || total);
            if (job.status === 'completed') {
              row.children[5].innerHTML = '<span class="badge bg-success">Completed</span>';
              clearInterval(interval);
            } else if (job.status === 'failed') {
              row.children[5].innerHTML = '<span class="badge bg-danger">Failed</span>';
              clearInterval(interval);
            }
          }
        })
        .catch(() => {});
    }, 3000);
  }
});
</script>
@endpush
