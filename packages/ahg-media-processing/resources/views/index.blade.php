@extends('theme::layouts.1col')

@section('title', 'Media Processing')
@section('body-class', 'admin media-processing')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-photo-video"></i> Media Processing</h1>
    <div>
      <a href="{{ route('media-processing.watermark-settings') }}" class="btn atom-btn-white">
        <i class="fas fa-stamp"></i> Watermark Settings
      </a>
    </div>
  </div>
  <p class="text-muted mb-4">Manage image derivatives, thumbnails, and reference images for digital objects.</p>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show">
      {{ session('warning') }}
      @if(session('batch_errors'))
        <ul class="mb-0 mt-2">
          @foreach(session('batch_errors') as $batchError)
            <li>{{ $batchError }}</li>
          @endforeach
        </ul>
      @endif
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Stats Cards --}}
  <div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card shadow-sm border-start border-4 border-primary">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small text-uppercase">Total Masters</div>
              <div class="h3 mb-0">{{ number_format($stats['total_masters']) }}</div>
            </div>
            <div class="text-primary opacity-50"><i class="fas fa-images fa-2x"></i></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card shadow-sm border-start border-4 border-success">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small text-uppercase">With Thumbnails</div>
              <div class="h3 mb-0">{{ number_format($stats['with_thumbnails']) }}</div>
            </div>
            <div class="text-success opacity-50"><i class="fas fa-th-large fa-2x"></i></div>
          </div>
          @if($stats['total_masters'] > 0)
            <div class="progress mt-2" style="height: 4px;">
              <div class="progress-bar bg-success" style="width: {{ round(($stats['with_thumbnails'] / $stats['total_masters']) * 100) }}%"></div>
            </div>
          @endif
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card shadow-sm border-start border-4 border-info">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small text-uppercase">With References</div>
              <div class="h3 mb-0">{{ number_format($stats['with_references']) }}</div>
            </div>
            <div class="text-info opacity-50"><i class="fas fa-expand fa-2x"></i></div>
          </div>
          @if($stats['total_masters'] > 0)
            <div class="progress mt-2" style="height: 4px;">
              <div class="progress-bar bg-info" style="width: {{ round(($stats['with_references'] / $stats['total_masters']) * 100) }}%"></div>
            </div>
          @endif
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card shadow-sm border-start border-4 border-warning">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small text-uppercase">Missing Derivatives</div>
              <div class="h3 mb-0">{{ number_format(max($stats['missing_thumbnails'], $stats['missing_references'])) }}</div>
            </div>
            <div class="text-warning opacity-50"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
          </div>
          <div class="small text-muted mt-1">
            {{ number_format($stats['missing_thumbnails']) }} thumbs / {{ number_format($stats['missing_references']) }} refs
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Action Buttons --}}
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-tools"></i> Batch Operations</h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <form method="POST" action="{{ route('media-processing.batch-regenerate') }}">
            @csrf
            <input type="hidden" name="type" value="all">
            <input type="hidden" name="limit" value="100">
            <button type="submit" class="btn atom-btn-outline-success w-100"
              {{ $stats['missing_thumbnails'] == 0 && $stats['missing_references'] == 0 ? 'disabled' : '' }}
              onclick="return confirm('Regenerate missing derivatives for up to 100 objects? This may take a while.')">
              <i class="fas fa-sync-alt"></i> Regenerate All Missing
            </button>
            <small class="text-muted d-block mt-1">Process up to 100 objects missing thumbnails or references</small>
          </form>
        </div>
        <div class="col-md-4">
          <form method="POST" action="{{ route('media-processing.batch-regenerate') }}">
            @csrf
            <input type="hidden" name="type" value="thumbnail">
            <input type="hidden" name="limit" value="100">
            <button type="submit" class="btn atom-btn-outline-success w-100"
              {{ $stats['missing_thumbnails'] == 0 ? 'disabled' : '' }}
              onclick="return confirm('Regenerate missing thumbnails for up to 100 objects?')">
              <i class="fas fa-th-large"></i> Regenerate Thumbnails
            </button>
            <small class="text-muted d-block mt-1">{{ number_format($stats['missing_thumbnails']) }} masters missing thumbnails</small>
          </form>
        </div>
        <div class="col-md-4">
          <form method="POST" action="{{ route('media-processing.batch-regenerate') }}">
            @csrf
            <input type="hidden" name="type" value="reference">
            <input type="hidden" name="limit" value="100">
            <button type="submit" class="btn atom-btn-white w-100 text-white"
              {{ $stats['missing_references'] == 0 ? 'disabled' : '' }}
              onclick="return confirm('Regenerate missing reference images for up to 100 objects?')">
              <i class="fas fa-expand"></i> Regenerate References
            </button>
            <small class="text-muted d-block mt-1">{{ number_format($stats['missing_references']) }} masters missing references</small>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Missing Derivatives Table --}}
  @if($missingDerivatives->isNotEmpty())
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-exclamation-circle text-warning"></i> Masters Missing Derivatives</h5>
      <span class="badge bg-warning text-dark">{{ $missingDerivatives->count() }} shown</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Object Title</th>
              <th>Filename</th>
              <th>MIME Type</th>
              <th>Size</th>
              <th class="text-center">Thumb</th>
              <th class="text-center">Ref</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($missingDerivatives as $master)
            <tr>
              <td><code>{{ $master->id }}</code></td>
              <td>
                @if($master->object_id)
                  <a href="{{ url('/informationobject/' . $master->object_id) }}">
                    {{ \Illuminate\Support\Str::limit($master->object_title ?? 'Untitled', 40) }}
                  </a>
                @else
                  <span class="text-muted">No linked object</span>
                @endif
              </td>
              <td><small>{{ \Illuminate\Support\Str::limit($master->name, 30) }}</small></td>
              <td><small>{{ $master->mime_type }}</small></td>
              <td><small>{{ $master->byte_size ? number_format($master->byte_size / 1024, 1) . ' KB' : '-' }}</small></td>
              <td class="text-center">
                @if($master->has_thumbnail)
                  <i class="fas fa-check-circle text-success"></i>
                @else
                  <i class="fas fa-times-circle text-danger"></i>
                @endif
              </td>
              <td class="text-center">
                @if($master->has_reference)
                  <i class="fas fa-check-circle text-success"></i>
                @else
                  <i class="fas fa-times-circle text-danger"></i>
                @endif
              </td>
              <td class="text-end">
                <form method="POST" action="{{ route('media-processing.regenerate', $master->id) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm atom-btn-white" title="Regenerate derivatives">
                    <i class="fas fa-sync-alt"></i>
                  </button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  {{-- Recent Derivatives Table --}}
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-history"></i> Recent Derivatives</h5>
      <span class="badge bg-secondary">{{ $recentDerivatives->count() }} shown</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Object Title</th>
              <th>Master</th>
              <th>Derivative</th>
              <th>Type</th>
              <th>MIME</th>
              <th>Size</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentDerivatives as $derivative)
            <tr>
              <td><code>{{ $derivative->id }}</code></td>
              <td>
                @if($derivative->object_id)
                  <a href="{{ url('/informationobject/' . $derivative->object_id) }}">
                    {{ \Illuminate\Support\Str::limit($derivative->object_title ?? 'Untitled', 35) }}
                  </a>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td><small>{{ \Illuminate\Support\Str::limit($derivative->master_name, 25) }}</small></td>
              <td><small>{{ \Illuminate\Support\Str::limit($derivative->name, 25) }}</small></td>
              <td>
                <span class="badge {{ $derivative->usage_id == 142 ? 'bg-success' : 'bg-info' }}">
                  {{ $usageLabels[$derivative->usage_id] ?? 'Unknown' }}
                </span>
              </td>
              <td><small>{{ $derivative->mime_type }}</small></td>
              <td><small>{{ $derivative->byte_size ? number_format($derivative->byte_size / 1024, 1) . ' KB' : '-' }}</small></td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No derivatives found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
