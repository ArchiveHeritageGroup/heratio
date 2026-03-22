@extends('theme::layouts.1col')
@section('title', 'View Merge Job')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('preservation.index') }}">Preservation</a></li>
        <li class="breadcrumb-item"><a href="{{ route('preservation.tiffpdfmerge.browse') }}">Merge Jobs</a></li>
        <li class="breadcrumb-item active">Job #{{ $job->id ?? '' }}</li>
      </ol>
    </nav>

    <h1><i class="fas fa-file-pdf me-2"></i>Merge Job #{{ $job->id ?? '' }}</h1>

    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">Job Details</div>
      <div class="card-body">
        <table class="table table-sm">
          <tr><th width="150">Status</th><td>
            @if(($job->status ?? '') === 'completed') <span class="badge bg-success">Completed</span>
            @elseif(($job->status ?? '') === 'failed') <span class="badge bg-danger">Failed</span>
            @else <span class="badge bg-info">{{ ucfirst($job->status ?? 'pending') }}</span> @endif
          </td></tr>
          <tr><th>Output Format</th><td>{{ strtoupper($job->output_format ?? '-') }}</td></tr>
          <tr><th>Output File</th><td>{{ $job->output_filename ?? '-' }}</td></tr>
          <tr><th>Files Merged</th><td>{{ $job->file_count ?? 0 }}</td></tr>
          <tr><th>Created</th><td>{{ $job->created_at ?? '' }}</td></tr>
          <tr><th>Completed</th><td>{{ $job->completed_at ?? '-' }}</td></tr>
        </table>

        @if(($job->status ?? '') === 'completed' && ($job->output_path ?? null))
        <a href="{{ $job->output_path }}" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>Download Output</a>
        @endif
      </div>
    </div>

    {{-- Source Files --}}
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-files me-2"></i>Source Files</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead><tr><th>#</th><th>Filename</th><th>Size</th><th>Type</th></tr></thead>
            <tbody>
              @forelse($sourceFiles ?? [] as $file)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $file->filename ?? '-' }}</td>
                <td>{{ number_format($file->size ?? 0) }} bytes</td>
                <td>{{ $file->mime_type ?? '-' }}</td>
              </tr>
              @empty
              <tr><td colspan="4" class="text-center text-muted py-3">No source files recorded</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection