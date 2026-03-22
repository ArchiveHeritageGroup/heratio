@extends('theme::layouts.1col')
@section('title', 'TIFF/PDF Merge Jobs')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-list me-2"></i>TIFF/PDF Merge Jobs</h1>
      <a href="{{ route('preservation.tiffpdfmerge.index') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-plus me-1"></i>New Merge</a>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              <th>ID</th><th>Status</th><th>Output Format</th><th>Files</th><th>Output</th><th>Created</th><th>Actions</th>
            </tr></thead>
            <tbody>
              @forelse($jobs ?? [] as $job)
              <tr>
                <td>{{ $job->id }}</td>
                <td>
                  @if($job->status === 'completed') <span class="badge bg-success">Completed</span>
                  @elseif($job->status === 'processing') <span class="badge bg-info">Processing</span>
                  @elseif($job->status === 'failed') <span class="badge bg-danger">Failed</span>
                  @else <span class="badge bg-warning text-dark">{{ ucfirst($job->status ?? 'pending') }}</span> @endif
                </td>
                <td>{{ strtoupper($job->output_format ?? '-') }}</td>
                <td>{{ $job->file_count ?? 0 }}</td>
                <td>{{ $job->output_filename ?? '-' }}</td>
                <td><small class="text-muted">{{ $job->created_at ?? '' }}</small></td>
                <td><a href="{{ route('preservation.tiffpdfmerge.view', $job->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
              </tr>
              @empty
              <tr><td colspan="7" class="text-center text-muted py-3">No merge jobs yet</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection