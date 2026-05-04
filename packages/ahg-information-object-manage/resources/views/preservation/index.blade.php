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

  {{-- Flash messages from create / update / download actions --}}
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- View / Edit package detail panel (from ?view= or ?edit= query params) --}}
  @php $detailPkg = $viewPackage ?? $editPackage ?? null; @endphp
  @if($detailPkg)
    <div class="card mb-4 border-primary">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">
          <i class="fas fa-{{ $editPackage ? 'pencil-alt' : 'eye' }} me-2"></i>
          {{ $editPackage ? __('Edit package') : __('Package detail') }}: {{ $detailPkg->name }}
          <span class="badge bg-light text-dark ms-2">{{ $detailPkg->package_type }}</span>
          <span class="badge bg-secondary">{{ ucfirst($detailPkg->status ?? 'draft') }}</span>
        </h5>
        <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id]) }}" class="btn btn-sm atom-btn-white" title="{{ __('Close') }}">
          <i class="fas fa-times"></i>
        </a>
      </div>
      <div class="card-body">
        @if($editPackage)
          <form method="POST" action="{{ route('io.preservation.update', ['slug' => $io->slug ?? $io->id, 'id' => $detailPkg->id]) }}">
            @csrf
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">{{ __('Name') }}</label>
                <input type="text" name="name" class="form-control" value="{{ $detailPkg->name }}" maxlength="255" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ __('Status') }}</label>
                <select name="status" class="form-select">
                  @foreach(['draft', 'pending', 'processing', 'stored', 'exported', 'failed'] as $st)
                    <option value="{{ $st }}" {{ ($detailPkg->status ?? 'draft') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <code class="small text-muted">{{ $detailPkg->uuid }}</code>
              </div>
              <div class="col-12">
                <label class="form-label">{{ __('Description') }}</label>
                <textarea name="description" class="form-control" rows="3">{{ $detailPkg->description }}</textarea>
              </div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save changes') }}</button>
              <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id, 'view' => $detailPkg->id]) }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
            </div>
          </form>
        @else
          <dl class="row mb-3 small">
            <dt class="col-sm-3">{{ __('UUID') }}</dt>          <dd class="col-sm-9"><code>{{ $detailPkg->uuid }}</code></dd>
            <dt class="col-sm-3">{{ __('Type') }}</dt>           <dd class="col-sm-9">{{ $detailPkg->package_type }}</dd>
            <dt class="col-sm-3">{{ __('Status') }}</dt>          <dd class="col-sm-9">{{ ucfirst($detailPkg->status ?? 'draft') }}</dd>
            <dt class="col-sm-3">{{ __('Format') }}</dt>          <dd class="col-sm-9">{{ $detailPkg->package_format ?? 'bagit' }} ({{ $detailPkg->manifest_algorithm ?? 'sha256' }})</dd>
            <dt class="col-sm-3">{{ __('Object count') }}</dt>    <dd class="col-sm-9">{{ $detailPkg->object_count ?? 0 }}</dd>
            <dt class="col-sm-3">{{ __('Total size') }}</dt>      <dd class="col-sm-9">{{ formatBytes($detailPkg->total_size ?? 0) }}</dd>
            @if(!empty($detailPkg->source_path))<dt class="col-sm-3">{{ __('Source path') }}</dt><dd class="col-sm-9"><code>{{ $detailPkg->source_path }}</code></dd>@endif
            @if(!empty($detailPkg->export_path))<dt class="col-sm-3">{{ __('Export path') }}</dt><dd class="col-sm-9"><code>{{ $detailPkg->export_path }}</code></dd>@endif
            @if(!empty($detailPkg->description))<dt class="col-sm-3">{{ __('Description') }}</dt><dd class="col-sm-9">{{ $detailPkg->description }}</dd>@endif
            <dt class="col-sm-3">{{ __('Created') }}</dt>         <dd class="col-sm-9">{{ $detailPkg->created_at }}</dd>
          </dl>
          @auth
          <div class="d-flex gap-2 mb-3 flex-wrap">
            <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id, 'edit' => $detailPkg->id]) }}" class="btn atom-btn-outline-primary"><i class="fas fa-pencil-alt me-1"></i>{{ __('Edit') }}</a>
            @if(($detailPkg->status ?? 'draft') !== 'exported' || empty($detailPkg->export_path))
              <form method="POST" action="{{ route('io.preservation.export', ['slug' => $io->slug ?? $io->id, 'id' => $detailPkg->id]) }}" class="d-inline" onsubmit="return confirm('Build a BagIt zip for this package now? This may take a while for large packages.');">
                @csrf
                <button type="submit" class="btn atom-btn-outline-warning"><i class="fas fa-file-archive me-1"></i>{{ __('Build BagIt zip (export)') }}</button>
              </form>
            @endif
            <a href="{{ route('io.preservation', ['slug' => $io->slug ?? $io->id, 'download' => $detailPkg->id]) }}" class="btn atom-btn-outline-success"><i class="fas fa-download me-1"></i>{{ __('Download') }}</a>
          </div>
          @endauth
        @endif

        {{-- Files in this package --}}
        <h6 class="mt-3"><i class="fas fa-file me-1"></i>{{ __('Files') }} <span class="badge bg-secondary">{{ count($packageFiles ?? []) }}</span></h6>
        @if(empty($packageFiles) || count($packageFiles) === 0)
          <p class="text-muted small mb-2">{{ __('No files linked to this package.') }}</p>
        @else
          <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr><th>#</th><th>{{ __('Path') }}</th><th>{{ __('MIME') }}</th><th class="text-end">{{ __('Size') }}</th><th>{{ __('Role') }}</th></tr>
              </thead>
              <tbody>
                @foreach($packageFiles as $f)
                  <tr>
                    <td>{{ $f->sequence ?? '—' }}</td>
                    <td><code class="small">{{ $f->relative_path ?? $f->file_name }}</code></td>
                    <td class="small">{{ $f->mime_type ?? '—' }}</td>
                    <td class="text-end">{{ isset($f->file_size) ? formatBytes($f->file_size) : '—' }}</td>
                    <td><span class="badge bg-light text-dark">{{ $f->object_role ?? 'payload' }}</span></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        {{-- Audit-trail events --}}
        <h6 class="mt-2"><i class="fas fa-history me-1"></i>{{ __('Events') }} <span class="badge bg-secondary">{{ count($packageEvents ?? []) }}</span></h6>
        @if(empty($packageEvents) || count($packageEvents) === 0)
          <p class="text-muted small mb-0">{{ __('No events recorded.') }}</p>
        @else
          <ul class="list-unstyled small mb-0">
            @foreach($packageEvents as $ev)
              <li class="border-start border-3 ps-2 mb-2">
                <span class="badge bg-{{ ($ev->event_outcome ?? 'success') === 'success' ? 'success' : 'warning' }}">{{ ucfirst($ev->event_type ?? '?') }}</span>
                <span class="text-muted">{{ $ev->event_datetime ?? $ev->created_at ?? '' }}</span>
                @if(!empty($ev->agent_value))<span class="text-muted small">&middot; {{ $ev->agent_type ?? 'agent' }}: {{ $ev->agent_value }}</span>@endif
                <br>
                {{ $ev->event_detail ?? '' }}
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  @endif

  {{-- Packages table --}}
  @if($aips->isNotEmpty() || $premisObjects->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i> {{ __('Packages') }}</h5>
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
          <h5 class="mb-0"><i class="fas fa-cube me-2"></i> {{ __('PREMIS Objects') }}</h5>
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
      <form action="{{ route('io.preservation.create', ['slug' => $io->slug ?? $io->id]) }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="createPackageModalLabel"><i class="fas fa-plus me-2"></i> {{ __('Create Preservation Package') }}</h5>
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
