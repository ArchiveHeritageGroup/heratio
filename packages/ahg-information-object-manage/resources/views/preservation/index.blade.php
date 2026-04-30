@extends('theme::layouts.1col')

@section('title', 'Preservation Packages — ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container py-4">

  {{-- Breadcrumb --}}
  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', ['slug' => $io->slug ?? $io->id]) }}">{{ $io->title ?? 'Untitled' }}</a></li>
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

  {{-- Statistics cards row (matching AtoM colored style) --}}
  @php
    $totalSize = $aips->sum('size_on_disk') + $premisObjects->sum('size');
  @endphp
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card bg-primary text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="mb-0">{{ __('Total Packages') }}</h6>
              <h2 class="mb-0">{{ $totalPackages }}</h2>
              <small>{{ $totalSize > 0 ? formatBytes($totalSize) : '' }}</small>
            </div>
            <i class="fas fa-box fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-info text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="mb-0">{{ __('SIPs') }}</h6>
              <h2 class="mb-0">{{ $sipCount }}</h2>
              <small>{{ __('Submission') }}</small>
            </div>
            <i class="fas fa-upload fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-success text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="mb-0">{{ __('AIPs') }}</h6>
              <h2 class="mb-0">{{ $aipCount }}</h2>
              <small>{{ __('Archival') }}</small>
            </div>
            <i class="fas fa-archive fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card bg-warning text-dark h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="mb-0">{{ __('DIPs') }}</h6>
              <h2 class="mb-0">{{ $dipCount }}</h2>
              <small>{{ __('Dissemination') }}</small>
            </div>
            <i class="fas fa-download fa-2x opacity-50"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Actions bar --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <a href="{{ route('informationobject.show', ['slug' => $io->slug ?? $io->id]) }}" class="btn atom-btn-white me-2">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}
      </a>
      @auth
        <button type="button" class="btn atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#createPackageModal">
          <i class="fas fa-plus me-1"></i> {{ __('Create Package') }}
        </button>
      @endauth
    </div>
    <div class="d-flex gap-2">
      <div class="dropdown">
        <button class="btn atom-btn-white dropdown-toggle" type="button" id="typeFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-filter me-1"></i> {{ __('Type') }}
        </button>
        <ul class="dropdown-menu" aria-labelledby="typeFilterDropdown">
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-type="all">All Types</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-type="SIP">SIP</a></li>
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-type="AIP">AIP</a></li>
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-type="DIP">DIP</a></li>
        </ul>
      </div>
      <div class="dropdown">
        <button class="btn atom-btn-white dropdown-toggle" type="button" id="statusFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-tasks me-1"></i> {{ __('Status') }}
        </button>
        <ul class="dropdown-menu" aria-labelledby="statusFilterDropdown">
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-status="all">All Statuses</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-status="Pending">Pending</a></li>
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-status="Processing">Processing</a></li>
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-status="Stored">Stored</a></li>
          <li><a class="dropdown-item" href="javascript:void(0)" data-filter-status="Failed">Failed</a></li>
        </ul>
      </div>
    </div>
  </div>

  {{-- Packages table --}}
  @if($aips->isNotEmpty() || $premisObjects->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i> Packages</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Package Name / UUID') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Objects') }}</th>
                <th>{{ __('Size') }}</th>
                <th>{{ __('Created') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
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
                      <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id]) }}?view={{ $aip->id }}" class="btn atom-btn-white" title="{{ __('View package') }}">
                        <i class="fas fa-eye"></i>
                      </a>
                      @auth
                        <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id]) }}?edit={{ $aip->id }}" class="btn atom-btn-white" title="{{ __('Edit package') }}">
                          <i class="fas fa-pencil-alt"></i>
                        </a>
                      @endauth
                      <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id]) }}?download={{ $aip->id }}" class="btn atom-btn-outline-success" title="{{ __('Download package') }}">
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
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-cube me-2"></i> PREMIS Objects</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('PUID') }}</th>
                  <th>{{ __('MIME Type') }}</th>
                  <th>{{ __('Size') }}</th>
                  <th>{{ __('Ingested') }}</th>
                  <th class="text-end">{{ __('Actions') }}</th>
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
                      <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id]) }}?premis={{ $po->id ?? '' }}" class="btn btn-sm atom-btn-white" title="{{ __('View PREMIS events') }}">
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
      <h4 class="text-muted">{{ __('No Preservation Packages') }}</h4>
      <p class="text-muted mb-4">
        No preservation packages have been created for this resource yet.
      </p>
      @auth
        <button type="button" class="btn atom-btn-outline-success btn-lg" data-bs-toggle="modal" data-bs-target="#createPackageModal">
          <i class="fas fa-plus me-1"></i> {{ __('Create Package') }}
        </button>
      @endauth
    </div>

  @endif

</div>

{{-- Create Package Modal --}}
@auth
<div class="modal fade" id="createPackageModal" tabindex="-1" aria-labelledby="createPackageModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id]) }}" method="GET">
        <div class="modal-header">
          <h5 class="modal-title" id="createPackageModalLabel"><i class="fas fa-plus me-2"></i> Create Preservation Package</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="package_type" class="form-label">Package Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="package_type" id="package_type" class="form-select">
              <option value="SIP">{{ __('SIP (Submission Information Package)') }}</option>
              <option value="AIP" selected>{{ __('AIP (Archival Information Package)') }}</option>
              <option value="DIP">{{ __('DIP (Dissemination Information Package)') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="package_name" class="form-label">Package Name <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="text" name="package_name" id="package_name" class="form-control" placeholder="{{ __('Enter a descriptive name') }}">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-plus me-1"></i> {{ __('Create Package') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endauth

@endsection
