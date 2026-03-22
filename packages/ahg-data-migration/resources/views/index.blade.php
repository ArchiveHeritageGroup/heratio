@extends('theme::layouts.1col')

@section('title', 'Data Migration')
@section('body-class', 'admin data-migration')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-exchange-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Data Migration</h1>
      <span class="small text-muted">Import, export, and manage field mappings</span>
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

  {{-- Action buttons — atom-btn-white matching AtoM --}}
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('data-migration.upload') }}" class="atom-btn-white">
      <i class="fas fa-upload"></i> New Import
    </a>
    <a href="{{ route('data-migration.batch-export') }}" class="atom-btn-white">
      <i class="fas fa-download"></i> Batch Export
    </a>
    <a href="{{ route('data-migration.jobs') }}" class="atom-btn-white">
      <i class="fas fa-tasks"></i> All Jobs
    </a>
    <a href="{{ route('data-migration.export') }}" class="atom-btn-white">
      <i class="fas fa-file-export"></i> Export Records
    </a>
    <a href="{{ route('data-migration.preservica-import') }}" class="atom-btn-white">
      <i class="fas fa-cloud-upload-alt"></i> Preservica Import
    </a>
    <a href="{{ route('data-migration.preservica-export') }}" class="atom-btn-white">
      <i class="fas fa-cloud-download-alt"></i> Preservica Export
    </a>
    <a href="{{ route('data-migration.import-results') }}" class="atom-btn-white">
      <i class="fas fa-list-alt"></i> Import Results
    </a>
  </div>

  <div class="row">
    {{-- Saved Mappings --}}
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"
             style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-map-signs"></i> Saved Mappings</h5>
          <span class="badge bg-light text-dark">{{ count($mappings) }}</span>
        </div>
        <div class="card-body p-0">
          @if(count($mappings) > 0)
            <div class="table-responsive">
              <table class="table table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Target Type</th>
                    <th>Category</th>
                    <th>Updated</th>
                    <th style="width:100px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($mappings as $mapping)
                    <tr>
                      <td>{{ $mapping['name'] }}</td>
                      <td><span class="badge bg-info text-dark">{{ $mapping['target_type'] }}</span></td>
                      <td>{{ $mapping['category'] ?? 'Custom' }}</td>
                      <td>{{ $mapping['updated_at'] ? \Carbon\Carbon::parse($mapping['updated_at'])->format('Y-m-d H:i') : '' }}</td>
                      <td>
                        <a href="{{ route('data-migration.export-mapping', $mapping['id']) }}"
                           class="atom-btn-outline-light btn-sm me-1" title="Export mapping">
                          <i class="fas fa-file-export"></i>
                        </a>
                        <form method="POST"
                              action="{{ route('data-migration.delete-mapping', $mapping['id']) }}"
                              class="d-inline"
                              onsubmit="return confirm('Delete this mapping?')">
                          @csrf
                          <button type="submit" class="atom-btn-outline-light btn-sm" title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="p-3 text-muted text-center">
              <i class="fas fa-info-circle"></i> No saved mappings yet. Create one during import.
            </div>
          @endif
        </div>
        @if(count($mappings) > 0)
          <div class="card-footer text-end">
            <label for="importMappingFile" class="atom-btn-white mb-0" style="cursor:pointer">
              <i class="fas fa-file-import"></i> Import Mapping File <span class="badge bg-secondary ms-1">Optional</span>
            </label>
            <form method="POST" action="{{ route('data-migration.import-mapping') }}"
                  enctype="multipart/form-data" class="d-inline">
              @csrf
              <input type="file" id="importMappingFile" name="mapping_file"
                     accept=".json" class="d-none"
                     onchange="this.form.submit()">
            </form>
          </div>
        @endif
      </div>
    </div>

    {{-- Recent Jobs --}}
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"
             style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-history"></i> Recent Jobs</h5>
          <a href="{{ route('data-migration.jobs') }}" class="badge bg-light text-dark text-decoration-none">
            View all
          </a>
        </div>
        <div class="card-body p-0">
          @if(isset($recentJobs) && count($recentJobs) > 0)
            <div class="table-responsive">
              <table class="table table-bordered mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Started</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($recentJobs as $job)
                    <tr>
                      <td>
                        <a href="{{ route('data-migration.job', $job['id']) }}">{{ $job['id'] }}</a>
                      </td>
                      <td>{{ $job['type'] ?? 'Import' }}</td>
                      <td>
                        <span class="badge bg-{{ $job['status'] === 'completed' ? 'success' : ($job['status'] === 'failed' ? 'danger' : 'warning text-dark') }}">
                          {{ ucfirst($job['status'] ?? 'queued') }}
                        </span>
                      </td>
                      <td>
                        <div class="progress" style="height:16px;min-width:80px">
                          <div class="progress-bar" style="width:{{ $job['progress'] ?? 0 }}%">
                            {{ $job['progress'] ?? 0 }}%
                          </div>
                        </div>
                      </td>
                      <td>{{ isset($job['created_at']) ? \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d H:i') : '' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="p-3 text-muted text-center">
              <i class="fas fa-info-circle"></i> No jobs yet.
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Quick Stats --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h3 mb-0">{{ $stats['total_imports'] ?? 0 }}</div>
          <div class="text-muted small">Total Imports</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h3 mb-0 text-success">{{ $stats['successful'] ?? 0 }}</div>
          <div class="text-muted small">Successful</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h3 mb-0 text-danger">{{ $stats['failed'] ?? 0 }}</div>
          <div class="text-muted small">Failed</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <div class="h3 mb-0 text-info">{{ $stats['total_records'] ?? 0 }}</div>
          <div class="text-muted small">Records Migrated</div>
        </div>
      </div>
    </div>
  </div>
@endsection
