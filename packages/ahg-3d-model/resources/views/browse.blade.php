@extends('theme::layouts.1col')

@section('title', '3D Models')
@section('body-class', 'browse 3d-models admin')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-cubes me-2"></i>3D Models</h1>
      <p class="text-muted mb-0">{{ number_format($totalCount) }} models in the archive</p>
    </div>
  </div>

  {{-- Statistics --}}
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-center">
        <div class="card-body py-3">
          <h3 class="mb-0">{{ number_format($totalCount) }}</h3>
          <small class="text-muted">Total 3D objects</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center border-success">
        <div class="card-body py-3">
          <h3 class="mb-0 text-success">{{ number_format($withThumbnails) }}</h3>
          <small class="text-muted">With thumbnails</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center border-warning">
        <div class="card-body py-3">
          <h3 class="mb-0 text-warning">{{ number_format($withoutThumbnails) }}</h3>
          <small class="text-muted">Without thumbnails</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Batch action --}}
  @if($withoutThumbnails > 0)
    <div class="mb-3">
      <form action="{{ route('admin.3d-models.batch-thumbnails') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn atom-btn-outline-success"
                onclick="return confirm('Generate thumbnails for {{ $withoutThumbnails }} 3D objects? This may take a while.');">
          <i class="fas fa-magic me-1"></i>Generate all missing thumbnails ({{ $withoutThumbnails }})
        </button>
      </form>
    </div>
  @endif

  {{-- Flash messages --}}
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      {{ session('warning') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if(session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      {{ session('info') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($totalCount === 0)
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="fas fa-cube fa-4x text-muted mb-3 d-block"></i>
        <h4>No 3D Models Yet</h4>
        <p class="text-muted">Upload 3D models from individual object pages.</p>
      </div>
    </div>
  @else
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-bordered table-hover mb-0">
          <thead>
            <tr>
              <th width="60">ID</th>
              <th>Filename</th>
              <th>Object</th>
              <th>Format</th>
              <th class="text-end">Size</th>
              <th class="text-center">Thumbnail</th>
              <th class="text-center">Multi-angle</th>
              <th width="180">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($models as $model)
              <tr>
                <td class="text-muted">{{ $model->id }}</td>
                <td>
                  <strong>{{ e($model->name) }}</strong>
                </td>
                <td>
                  @if($model->object_slug)
                    <a href="{{ url('/' . $model->object_slug) }}">
                      {{ e(\Illuminate\Support\Str::limit($model->object_title ?: 'Untitled', 40)) }}
                    </a>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  <span class="badge bg-secondary">{{ $model->format }}</span>
                </td>
                <td class="text-end">
                  <small>{{ number_format(($model->byte_size ?? 0) / 1024 / 1024, 2) }} MB</small>
                </td>
                <td class="text-center">
                  @if($model->has_thumbnail)
                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                  @else
                    <span class="badge bg-warning text-dark"><i class="fas fa-times"></i> No</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($model->has_multiangle)
                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                  @else
                    <span class="badge bg-warning text-dark"><i class="fas fa-times"></i> No</span>
                  @endif
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('admin.3d-models.thumbnail', $model->id) }}"
                       class="btn atom-btn-white"
                       title="{{ $model->has_thumbnail ? 'Regenerate thumbnail' : 'Generate thumbnail' }}">
                      <i class="fas fa-image me-1"></i>{{ $model->has_thumbnail ? 'Regen' : 'Generate' }}
                    </a>
                    <a href="{{ route('admin.3d-models.multiangle', $model->id) }}"
                       class="btn atom-btn-white"
                       title="{{ $model->has_multiangle ? 'Regenerate multi-angle' : 'Generate multi-angle' }}">
                      <i class="fas fa-th-large me-1"></i>Multi
                    </a>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Pagination --}}
    @if($totalPages > 1)
      <nav class="mt-3" aria-label="3D models pagination">
        <ul class="pagination justify-content-center">
          @if($page > 1)
            <li class="page-item">
              <a class="page-link" href="{{ route('admin.3d-models.browse', ['page' => $page - 1]) }}">
                &laquo; Previous
              </a>
            </li>
          @endif

          @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
            <li class="page-item {{ $i === $page ? 'active' : '' }}">
              <a class="page-link" href="{{ route('admin.3d-models.browse', ['page' => $i]) }}">
                {{ $i }}
              </a>
            </li>
          @endfor

          @if($page < $totalPages)
            <li class="page-item">
              <a class="page-link" href="{{ route('admin.3d-models.browse', ['page' => $page + 1]) }}">
                Next &raquo;
              </a>
            </li>
          @endif
        </ul>
      </nav>
    @endif
  @endif
@endsection
