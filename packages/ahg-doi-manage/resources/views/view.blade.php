@extends('theme::layouts.1col')

@section('title', 'DOI Details')
@section('body-class', 'admin doi view')

@section('content')
  @if(!($tablesExist ?? false))
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      The DOI management tables have not been created yet. Please run the database migration to set up DOI management.
    </div>
  @else
    <div class="multiline-header d-flex align-items-center mb-3">
      <i class="fas fa-3x fa-fingerprint me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column">
        <h1 class="mb-0">{{ $doi->doi }}</h1>
        <span class="small text-muted">DOI Details</span>
      </div>
      <div class="ms-auto">
        <a href="{{ route('doi.browse') }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-arrow-left me-1"></i> Back to Browse
        </a>
      </div>
    </div>

    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if(session('error'))
      <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <div class="row">
      <div class="col-lg-8">
        {{-- DOI Information card --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">DOI Information</div>
          <div class="card-body">
            <table class="table table-bordered mb-0">
              <tbody>
                <tr>
                  <th style="width: 200px;">DOI</th>
                  <td>
                    <a href="https://doi.org/{{ $doi->doi }}" target="_blank" class="text-monospace text-decoration-none">
                      <code>{{ $doi->doi }}</code>
                      <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                    </a>
                  </td>
                </tr>
                <tr>
                  <th>Record</th>
                  <td>
                    @if($doi->information_object_id)
                      <a href="{{ route('informationobject.show', $doi->information_object_id) }}">
                        {{ $doi->record_title ?: '[Untitled]' }}
                      </a>
                    @else
                      {{ $doi->record_title ?: '[Untitled]' }}
                    @endif
                  </td>
                </tr>
                <tr>
                  <th>Status</th>
                  <td>
                    @if($doi->status === 'findable')
                      <span class="badge bg-success">Findable</span>
                    @elseif($doi->status === 'registered')
                      <span class="badge bg-info">Registered</span>
                    @elseif($doi->status === 'deleted')
                      <span class="badge bg-danger">Deleted</span>
                    @else
                      <span class="badge bg-secondary">Draft</span>
                    @endif
                  </td>
                </tr>
                <tr>
                  <th>Minted At</th>
                  <td>{{ $doi->minted_at ? \Carbon\Carbon::parse($doi->minted_at)->format('Y-m-d H:i:s') : '-' }}</td>
                </tr>
                <tr>
                  <th>Last Sync</th>
                  <td>{{ !empty($doi->last_sync_at) ? \Carbon\Carbon::parse($doi->last_sync_at)->format('Y-m-d H:i:s') : '-' }}</td>
                </tr>
                <tr>
                  <th>Created</th>
                  <td>{{ $doi->created_at ? \Carbon\Carbon::parse($doi->created_at)->format('Y-m-d H:i:s') : '' }}</td>
                </tr>
                <tr>
                  <th>Last Updated</th>
                  <td>{{ $doi->updated_at ? \Carbon\Carbon::parse($doi->updated_at)->format('Y-m-d H:i:s') : '' }}</td>
                </tr>
                @if($doi->status === 'deleted')
                  <tr>
                    <th>Deactivated At</th>
                    <td>{{ !empty($doi->deactivated_at) ? \Carbon\Carbon::parse($doi->deactivated_at)->format('Y-m-d H:i:s') : '-' }}</td>
                  </tr>
                  @if(!empty($doi->deactivation_reason))
                    <tr>
                      <th>Deactivation Reason</th>
                      <td>{{ $doi->deactivation_reason }}</td>
                    </tr>
                  @endif
                @endif
              </tbody>
            </table>
          </div>
        </div>

        {{-- DataCite Metadata --}}
        @if(!empty($doi->metadata_json))
          <div class="card mb-4">
            <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">DataCite Metadata</div>
            <div class="card-body">
              <pre class="mb-0" style="max-height: 400px; overflow: auto;"><code>{{ json_encode(json_decode($doi->metadata_json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
            </div>
          </div>
        @endif

        {{-- Activity Log --}}
        <h3 class="mb-3">Activity Log</h3>
        @if(count($logs))
          <div class="table-responsive mb-3">
            <table class="table table-bordered table-striped mb-0">
              <thead>
                <tr>
                  <th>Action</th>
                  <th>Status Change</th>
                  <th>Performed At</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                @foreach($logs as $log)
                  <tr>
                    <td><span class="badge bg-secondary">{{ $log['event_type'] ?? $log['action'] ?? '' }}</span></td>
                    <td>
                      @if(!empty($log['status_before']) || !empty($log['status_after']))
                        {{ $log['status_before'] ?? '-' }} &rarr; {{ $log['status_after'] ?? '-' }}
                      @else
                        -
                      @endif
                    </td>
                    <td>{{ !empty($log['performed_at']) ? \Carbon\Carbon::parse($log['performed_at'])->format('Y-m-d H:i:s') : '' }}</td>
                    <td>
                      @if(!empty($log['details']))
                        <small class="text-muted">{{ \Illuminate\Support\Str::limit($log['details'], 100) }}</small>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="alert alert-info">No activity log entries for this DOI.</div>
        @endif
      </div>

      <div class="col-lg-4">
        {{-- Actions --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Actions</div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="{{ route('doi.view', $doi->id) }}?verify=1" class="btn atom-btn-white">
                <i class="fas fa-check-circle me-1"></i> Verify Resolution
              </a>

              @if($doi->status !== 'deleted')
                <a href="{{ route('doi.view', $doi->id) }}?sync=1" class="btn atom-btn-white">
                  <i class="fas fa-sync me-1"></i> Sync Metadata
                </a>
                <a href="{{ route('doi.view', $doi->id) }}?deactivate=1" class="btn atom-btn-outline-danger"
                   onclick="return confirm('Are you sure you want to deactivate this DOI?');">
                  <i class="fas fa-ban me-1"></i> Deactivate DOI
                </a>
              @else
                <a href="{{ route('doi.view', $doi->id) }}?reactivate=1" class="btn atom-btn-outline-success">
                  <i class="fas fa-redo me-1"></i> Reactivate DOI
                </a>
              @endif
            </div>
          </div>
        </div>

        {{-- Quick Links --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Quick Links</div>
          <div class="card-body">
            <ul class="list-unstyled mb-0">
              <li class="mb-2">
                <a href="https://doi.org/{{ $doi->doi }}" target="_blank">
                  <i class="fas fa-external-link-alt me-1"></i> doi.org landing page
                </a>
              </li>
              <li class="mb-2">
                <a href="https://api.datacite.org/dois/{{ urlencode($doi->doi) }}" target="_blank">
                  <i class="fas fa-code me-1"></i> DataCite API record
                </a>
              </li>
              @if($doi->information_object_id)
                <li>
                  <a href="{{ route('informationobject.show', $doi->information_object_id) }}">
                    <i class="fas fa-file-alt me-1"></i> View record
                  </a>
                </li>
              @endif
            </ul>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection
