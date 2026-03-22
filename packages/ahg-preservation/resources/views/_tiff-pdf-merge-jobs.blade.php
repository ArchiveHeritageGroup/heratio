{{-- TIFF/PDF Merge Jobs Partial --}}
<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <i class="fas fa-file-pdf me-2"></i>TIFF/PDF Merge Jobs
    @if(Route::has('preservation.tiffpdfmerge.browse'))
    <a href="{{ route('preservation.tiffpdfmerge.browse') }}" class="btn btn-sm atom-btn-white float-end">View All</a>
    @endif
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>ID</th><th>Status</th><th>Files</th><th>Output</th><th>Created</th>
          </tr>
        </thead>
        <tbody>
          @forelse($mergeJobs ?? [] as $job)
          <tr>
            <td>{{ $job->id }}</td>
            <td>
              @if($job->status === 'completed') <span class="badge bg-success">Completed</span>
              @elseif($job->status === 'processing') <span class="badge bg-info">Processing</span>
              @elseif($job->status === 'failed') <span class="badge bg-danger">Failed</span>
              @else <span class="badge bg-warning text-dark">{{ ucfirst($job->status ?? 'pending') }}</span> @endif
            </td>
            <td>{{ $job->file_count ?? 0 }}</td>
            <td>{{ $job->output_filename ?? '-' }}</td>
            <td><small>{{ $job->created_at ?? '' }}</small></td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-center text-muted py-3">No merge jobs</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>