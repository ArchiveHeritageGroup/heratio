@extends('theme::layouts.1col')

@section('title', 'Menus')
@section('body-class', 'browse menus')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-bars me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column flex-grow-1">
      <h1 class="mb-0">Menus</h1>
      <span class="small text-muted">{{ number_format($total) }} menu items</span>
    </div>
    <a href="{{ route('menu.create') }}" class="btn btn-outline-success">
      <i class="fas fa-plus me-1"></i> Add new
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if(count($tree))
    <div class="card mb-4">
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          @foreach($tree as $item)
            <li class="list-group-item" style="padding-left: {{ ($item['depth'] * 1.5) + 1 }}rem;">
              <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                  <a href="{{ route('menu.show', $item['id']) }}" class="fw-semibold text-decoration-none">
                    {{ $item['label'] ?: $item['name'] ?: '[Unnamed]' }}
                  </a>
                  @if($item['name'])
                    <span class="text-muted small ms-2">({{ $item['name'] }})</span>
                  @endif
                  @if($item['path'])
                    <span class="text-muted small ms-2">
                      <i class="fas fa-link fa-sm me-1"></i>{{ $item['path'] }}
                    </span>
                  @endif
                  @if($item['isProtected'])
                    <span class="badge bg-warning text-dark ms-2" title="Protected menu">
                      <i class="fas fa-shield-alt fa-sm"></i>
                    </span>
                  @endif
                </div>
                <div class="d-flex align-items-center gap-1">
                  @if($item['hasChildren'])
                    <span class="badge bg-secondary me-1">{{ intval(($item['rgt'] - $item['lft'] - 1) / 2) }}</span>
                  @endif
                  <form method="POST" action="{{ route('menu.moveUp', $item['id']) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Move up" {{ $loop->first ? 'disabled' : '' }}>
                      <i class="fas fa-arrow-up"></i>
                    </button>
                  </form>
                  <form method="POST" action="{{ route('menu.moveDown', $item['id']) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Move down" {{ $loop->last ? 'disabled' : '' }}>
                      <i class="fas fa-arrow-down"></i>
                    </button>
                  </form>
                  <a href="{{ route('menu.edit', $item['id']) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="fas fa-pencil-alt"></i>
                  </a>
                  @if(!$item['isProtected'])
                    <a href="{{ route('menu.confirmDelete', $item['id']) }}" class="btn btn-sm btn-outline-danger" title="Delete">
                      <i class="fas fa-trash"></i>
                    </a>
                  @endif
                </div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @else
    <div class="alert alert-info">No menu items found.</div>
  @endif
@endsection
