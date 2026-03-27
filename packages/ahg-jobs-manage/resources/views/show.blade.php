@extends('theme::layouts.1col')

@section('title', $job->name ?: 'Job #' . $job->id)
@section('body-class', 'show job')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ $job->name ?: 'Job #' . $job->id }}</h1>
      <span class="small text-muted">Job details</span>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <table class="table table-bordered mb-0">
        <tbody>
          <tr>
            <th style="width: 200px;">Id</th>
            <td>{{ $job->id }}</td>
          </tr>
          <tr>
            <th style="width: 200px;">Job name</th>
            <td>{{ $job->name }}</td>
          </tr>
          <tr>
            <th>Status</th>
            <td>
              @if($job->status_id == 184)
                <span class="badge bg-success">{{ $job->status_name ?: 'Completed' }}</span>
              @elseif($job->status_id == 185)
                <span class="badge bg-danger">{{ $job->status_name ?: 'Error' }}</span>
              @else
                <span class="badge bg-primary">{{ $job->status_name ?: 'Running' }}</span>
              @endif
            </td>
          </tr>
          <tr>
            <th>User</th>
            <td>{{ $job->user_name ?: $job->username ?: 'N/A' }}</td>
          </tr>
          <tr>
            <th>Created</th>
            <td>{{ $job->created_at ? \Carbon\Carbon::parse($job->created_at)->format('Y-m-d H:i:s') : 'N/A' }}</td>
          </tr>
          <tr>
            <th>Completed</th>
            <td>{{ $job->completed_at ? \Carbon\Carbon::parse($job->completed_at)->format('Y-m-d H:i:s') : 'N/A' }}</td>
          </tr>
          @if($job->object_id ?? null)
            <tr>
              <th>Associated record</th>
              <td>
                @if($job->object_slug ?? null)
                  <a href="{{ url('/' . $job->object_slug) }}">{{ $job->object_slug }}</a>
                @else
                  #{{ (int) $job->object_id }}
                @endif
              </td>
            </tr>
          @endif
          @if($job->download_path)
            <tr>
              <th>Download</th>
              <td>
                <a href="{{ $job->download_path }}" class="btn btn-sm atom-btn-white">
                  <i class="fas fa-download me-1"></i> Download
                </a>
              </td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  @if($job->output)
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Output</h5>
      </div>
      <div class="card-body p-0">
        <pre class="mb-0 p-3" style="max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ $job->output }}</pre>
      </div>
    </div>
  @endif

  @if($job->status_id == 185 && ($job->error_output ?? null))
    <div class="card mb-4">
      <div class="card-header bg-danger text-white">
        <h5 class="mb-0">Error(s)</h5>
      </div>
      <div class="card-body p-0">
        <div class="alert alert-danger mb-0 rounded-0 border-0">
          <pre class="mb-0" style="max-height: 500px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">{{ $job->error_output }}</pre>
        </div>
      </div>
    </div>
  @endif

  <div>
    <a href="{{ route('job.browse') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back to jobs
    </a>
  </div>
@endsection
