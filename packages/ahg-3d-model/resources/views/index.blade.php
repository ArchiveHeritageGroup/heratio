@extends('theme::layouts.1col')

@section('title', '3D Models')
@section('body-class', 'browse model3d')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-cubes me-2"></i>3D Models</h1>
      <p class="text-muted mb-0">{{ number_format($total ?? 0) }} models in the archive</p>
    </div>
    <div>
      <a href="{{ route('admin.3d-models.settings') }}" class="btn atom-btn-white">
        <i class="fas fa-cog me-1"></i>Settings
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if(isset($models) && count($models))
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-bordered table-hover mb-0">
          <thead>
            <tr>
              <th style="width:60px"></th>
              <th>Model</th>
              <th>Object</th>
              <th>Format</th>
              <th>Size</th>
              <th>Status</th>
              <th style="width:100px">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($models as $m)
            <tr>
              <td>
                @if(!empty($m->thumbnail))
                  <img src="/uploads/{{ $m->thumbnail }}" alt="" class="rounded" style="width:50px;height:50px;object-fit:cover;">
                @else
                  <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                    <i class="fas fa-cube"></i>
                  </div>
                @endif
              </td>
              <td>
                <a href="{{ route('admin.3d-models.view', $m->id) }}">
                  <strong>{{ $m->model_title ?: ($m->original_filename ?? '') }}</strong>
                </a>
                @if(!empty($m->is_primary))
                  <span class="badge bg-primary ms-1">Primary</span>
                @endif
                @if(!empty($m->ar_enabled))
                  <span class="badge bg-success ms-1">AR</span>
                @endif
              </td>
              <td>
                @if(!empty($m->object_slug))
                  <a href="{{ url($m->object_slug) }}">
                    {{ Str::limit($m->object_title ?: 'Untitled', 40) }}
                  </a>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                <span class="badge bg-secondary">{{ strtoupper($m->format ?? '') }}</span>
              </td>
              <td>
                <small>{{ number_format(($m->file_size ?? 0) / 1048576, 2) }} MB</small>
              </td>
              <td>
                @if(!empty($m->is_public))
                  <span class="text-success"><i class="fas fa-check-circle"></i> Public</span>
                @else
                  <span class="text-warning"><i class="fas fa-eye-slash"></i> Hidden</span>
                @endif
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('admin.3d-models.view', $m->id) }}" class="btn atom-btn-white" title="View">
                    <i class="fas fa-eye"></i>
                  </a>
                  <a href="{{ route('admin.3d-models.edit', $m->id) }}" class="btn atom-btn-white" title="Edit">
                    <i class="fas fa-edit"></i>
                  </a>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    @if(($totalPages ?? 1) > 1)
      <nav class="mt-3" aria-label="3D models pagination">
        <ul class="pagination justify-content-center">
          @if($page > 1)
            <li class="page-item">
              <a class="page-link" href="{{ route('admin.3d-models.index', ['page' => $page - 1]) }}">&laquo; Previous</a>
            </li>
          @endif
          @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
            <li class="page-item {{ $i === $page ? 'active' : '' }}">
              <a class="page-link" href="{{ route('admin.3d-models.index', ['page' => $i]) }}">{{ $i }}</a>
            </li>
          @endfor
          @if($page < $totalPages)
            <li class="page-item">
              <a class="page-link" href="{{ route('admin.3d-models.index', ['page' => $page + 1]) }}">Next &raquo;</a>
            </li>
          @endif
        </ul>
      </nav>
    @endif
  @else
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="fas fa-cube fa-4x text-muted mb-3 d-block"></i>
        <h4>No 3D Models Yet</h4>
        <p class="text-muted">Upload 3D models from individual object pages.</p>
      </div>
    </div>
  @endif
@endsection
