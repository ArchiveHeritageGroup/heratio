{{-- TIFF PDF Merge Dashboard partial --}}
<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>TIFF/PDF Merge Queue</h5>
  </div>
  <div class="card-body">
    <div class="row text-center mb-3">
      <div class="col-md-3">
        <div class="border rounded p-3">
          <h4 class="mb-0">{{ $mergeStats['pending'] ?? 0 }}</h4>
          <small class="text-muted">Pending</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3">
          <h4 class="mb-0 text-primary">{{ $mergeStats['processing'] ?? 0 }}</h4>
          <small class="text-muted">Processing</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3">
          <h4 class="mb-0 text-success">{{ $mergeStats['completed'] ?? 0 }}</h4>
          <small class="text-muted">Completed</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="border rounded p-3">
          <h4 class="mb-0 text-danger">{{ $mergeStats['failed'] ?? 0 }}</h4>
          <small class="text-muted">Failed</small>
        </div>
      </div>
    </div>
    @if(empty($mergeJobs))
      <p class="text-muted text-center">No merge jobs in queue.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>ID</th><th>Source</th><th>Status</th><th>Created</th></tr></thead>
          <tbody>
            @foreach($mergeJobs ?? [] as $job)
              <tr>
                <td>{{ $job->id }}</td>
                <td>{{ $job->source ?? '-' }}</td>
                <td><span class="badge bg-{{ match($job->status ?? 'pending') { 'completed' => 'success', 'processing' => 'primary', 'failed' => 'danger', default => 'secondary' } }}">{{ $job->status ?? 'pending' }}</span></td>
                <td>{{ $job->created_at ?? '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
