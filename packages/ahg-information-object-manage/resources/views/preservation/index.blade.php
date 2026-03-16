@extends('ahg-theme-b5::layouts.app')

@section('title', 'Preservation Packages — ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container-fluid py-4">

  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('io.show', ['slug' => $io->slug ?? $io->id]) }}">{{ $io->title ?? 'Untitled' }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">Preservation Packages</li>
    </ol>
  </nav>

  @php
    /**
     * Format bytes into a human-readable string.
     */
    function formatBytes($bytes, $precision = 2) {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }

    $totalPackages = $aips->count() + $premisObjects->count();
    $sipCount = $aips->where('package_type', 'SIP')->count();
    $aipCount = $aips->where('package_type', 'AIP')->count() ?: $aips->count();
    $dipCount = $aips->where('package_type', 'DIP')->count();
  @endphp

  {{-- Statistics cards row --}}
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <i class="fas fa-box fa-2x text-primary mb-2"></i>
          <h3 class="mb-0">{{ $totalPackages }}</h3>
          <p class="text-muted mb-0">Total Packages</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <i class="fas fa-upload fa-2x text-info mb-2"></i>
          <h3 class="mb-0">{{ $sipCount }}</h3>
          <p class="text-muted mb-0">SIPs</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <i class="fas fa-archive fa-2x text-success mb-2"></i>
          <h3 class="mb-0">{{ $aipCount }}</h3>
          <p class="text-muted mb-0">AIPs</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <i class="fas fa-download fa-2x text-warning mb-2"></i>
          <h3 class="mb-0">{{ $dipCount }}</h3>
          <p class="text-muted mb-0">DIPs</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Actions bar --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <a href="{{ route('io.show', ['slug' => $io->slug ?? $io->id]) }}" class="btn btn-outline-secondary me-2">
        <i class="fas fa-arrow-left me-1"></i> Back
      </a>
      @auth
        <a href="#" class="btn btn-success" onclick="alert('Create Package form — migration in progress'); return false;">
          <i class="fas fa-plus me-1"></i> Create Package
        </a>
      @endauth
    </div>
    <div class="d-flex gap-2">
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="typeFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-filter me-1"></i> Type
        </button>
        <ul class="dropdown-menu" aria-labelledby="typeFilterDropdown">
          <li><a class="dropdown-item" href="#">All Types</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="#">SIP</a></li>
          <li><a class="dropdown-item" href="#">AIP</a></li>
          <li><a class="dropdown-item" href="#">DIP</a></li>
        </ul>
      </div>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="statusFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-tasks me-1"></i> Status
        </button>
        <ul class="dropdown-menu" aria-labelledby="statusFilterDropdown">
          <li><a class="dropdown-item" href="#">All Statuses</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="#">Pending</a></li>
          <li><a class="dropdown-item" href="#">Processing</a></li>
          <li><a class="dropdown-item" href="#">Stored</a></li>
          <li><a class="dropdown-item" href="#">Failed</a></li>
        </ul>
      </div>
    </div>
  </div>

  {{-- Packages table --}}
  @if($aips->isNotEmpty() || $premisObjects->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i> Packages</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Package Name / UUID</th>
                <th>Type</th>
                <th>Status</th>
                <th>Objects</th>
                <th>Size</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($aips as $aip)
                @php
                  $packageType = $aip->package_type ?? 'AIP';
                  $packageTypeUpper = strtoupper($packageType);
                  if ($packageTypeUpper === 'SIP') {
                      $typeBadgeClass = 'bg-info text-dark';
                  } elseif ($packageTypeUpper === 'AIP') {
                      $typeBadgeClass = 'bg-success';
                  } elseif ($packageTypeUpper === 'DIP') {
                      $typeBadgeClass = 'bg-warning text-dark';
                  } else {
                      $typeBadgeClass = 'bg-secondary';
                  }

                  $packageStatus = strtolower(trim($aip->status ?? 'unknown'));
                  if ($packageStatus === 'pending') {
                      $statusBadgeClass = 'bg-secondary';
                  } elseif ($packageStatus === 'processing') {
                      $statusBadgeClass = 'bg-warning text-dark';
                  } elseif ($packageStatus === 'uploading') {
                      $statusBadgeClass = 'bg-info text-dark';
                  } elseif ($packageStatus === 'stored' || $packageStatus === 'complete') {
                      $statusBadgeClass = 'bg-success';
                  } elseif ($packageStatus === 'verified') {
                      $statusBadgeClass = 'bg-primary';
                  } elseif ($packageStatus === 'failed' || $packageStatus === 'error') {
                      $statusBadgeClass = 'bg-danger';
                  } else {
                      $statusBadgeClass = 'bg-secondary';
                  }
                @endphp
                <tr>
                  <td>
                    <strong>{{ $aip->filename ?? $aip->name ?? 'Unnamed package' }}</strong>
                    <br>
                    <small class="text-muted"><code>{{ $aip->uuid ?? '—' }}</code></small>
                  </td>
                  <td><span class="badge {{ $typeBadgeClass }}">{{ $packageTypeUpper }}</span></td>
                  <td><span class="badge {{ $statusBadgeClass }}">{{ ucfirst($aip->status ?? 'Unknown') }}</span></td>
                  <td>{{ $aip->object_count ?? '—' }}</td>
                  <td>{{ isset($aip->size_on_disk) ? formatBytes($aip->size_on_disk) : '—' }}</td>
                  <td>{{ $aip->created_at ?? '—' }}</td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <a href="#" class="btn btn-outline-primary" title="View" onclick="alert('View package — migration in progress'); return false;">
                        <i class="fas fa-eye"></i>
                      </a>
                      @auth
                        <a href="#" class="btn btn-outline-secondary" title="Edit" onclick="alert('Edit package — migration in progress'); return false;">
                          <i class="fas fa-pencil-alt"></i>
                        </a>
                      @endauth
                      <a href="#" class="btn btn-outline-success" title="Download" onclick="alert('Download package — migration in progress'); return false;">
                        <i class="fas fa-download"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- PREMIS Objects --}}
    @if($premisObjects->isNotEmpty())
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-cube me-2"></i> PREMIS Objects</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>PUID</th>
                  <th>MIME Type</th>
                  <th>Size</th>
                  <th>Ingested</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($premisObjects as $po)
                  <tr>
                    <td><code>{{ $po->puid ?? '—' }}</code></td>
                    <td>{{ $po->mime_type ?? '—' }}</td>
                    <td>{{ isset($po->size) ? formatBytes($po->size) : '—' }}</td>
                    <td>{{ $po->date_ingested ?? '—' }}</td>
                    <td class="text-end">
                      <a href="#" class="btn btn-sm btn-outline-primary" title="View" onclick="alert('View PREMIS object — migration in progress'); return false;">
                        <i class="fas fa-eye"></i>
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endif

  @else

    {{-- Empty state --}}
    <div class="text-center py-5">
      <div class="mb-4">
        <i class="fas fa-box-open fa-4x text-muted"></i>
      </div>
      <h4 class="text-muted">No Preservation Packages</h4>
      <p class="text-muted mb-4">
        No preservation packages have been created for this resource yet.
      </p>
      @auth
        <a href="#" class="btn btn-success btn-lg" onclick="alert('Create Package form — migration in progress'); return false;">
          <i class="fas fa-plus me-1"></i> Create Package
        </a>
      @endauth
    </div>

  @endif

</div>
@endsection
