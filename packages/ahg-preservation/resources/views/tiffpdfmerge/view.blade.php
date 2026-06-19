@extends('theme::layouts.1col')
@section('title', 'View Merge Job')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('preservation.index') }}">Preservation</a></li>
        <li class="breadcrumb-item"><a href="{{ route('preservation.tiffpdfmerge.browse') }}">Merge Jobs</a></li>
        <li class="breadcrumb-item active">Job #{{ $job->id ?? '' }}</li>
      </ol>
    </nav>

    <h1><i class="fas fa-file-pdf me-2"></i>Merge Job #{{ $job->id ?? '' }}</h1>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if(($job->status ?? '') === 'failed' && ($job->error_message ?? null))
    <div class="alert alert-danger"><strong>{{ __('Error') }}:</strong> {{ $job->error_message }}</div>
    @endif

    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">Job Details</div>
      <div class="card-body">
        <table class="table table-sm">
          <tr><th width="150">{{ __('Status') }}</th><td>
            @if(($job->status ?? '') === 'completed') <span class="badge bg-success">{{ __('Completed') }}</span>
            @elseif(($job->status ?? '') === 'failed') <span class="badge bg-danger">{{ __('Failed') }}</span>
            @else <span class="badge bg-info">{{ ucfirst($job->status ?? 'pending') }}</span> @endif
          </td></tr>
          <tr><th>{{ __('Output Format') }}</th><td>{{ strtoupper($job->output_format ?? '-') }}</td></tr>
          <tr><th>{{ __('Output File') }}</th><td>{{ $job->output_filename ?? '-' }}</td></tr>
          <tr><th>{{ __('Files Merged') }}</th><td>{{ $job->file_count ?? 0 }}</td></tr>
          @if(!empty($job->information_object_id))
          <tr><th>{{ __('Source Record') }}</th><td>
            #{{ $job->information_object_id }}
            @if(!empty($job->output_digital_object_id))
              <span class="badge bg-success ms-1"><i class="fas fa-paperclip me-1"></i>{{ __('Attached') }}</span>
            @endif
          </td></tr>
          @endif
          <tr><th>{{ __('Created') }}</th><td>{{ $job->created_at ?? '' }}</td></tr>
          <tr><th>{{ __('Completed') }}</th><td>{{ $job->completed_at ?? '-' }}</td></tr>
        </table>

        @if(($job->status ?? '') === 'completed' && ($job->output_path ?? null))
        <a href="{{ route('preservation.tiffpdfmerge.download', $job->id) }}" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>{{ __('Download Output') }}</a>
        @endif
        @if(!empty($recordSlug))
        <a href="{{ url('/'.$recordSlug) }}" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>{{ __('View Record') }}</a>
        @endif
      </div>
    </div>

    {{-- Source Files --}}
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-files me-2"></i>{{ __('Source Files') }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm mb-0">
            <thead><tr><th>#</th><th>{{ __('Filename') }}</th><th>{{ __('Size') }}</th><th>{{ __('Type') }}</th></tr></thead>
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