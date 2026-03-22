@extends('theme::layouts.1col')

@section('title', $menu->label ?: $menu->name ?: 'Menu #' . $menu->id)
@section('body-class', 'show menu')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-bars me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column flex-grow-1">
      <h1 class="mb-0">{{ $menu->label ?: $menu->name ?: 'Menu #' . $menu->id }}</h1>
      <span class="small text-muted">Menu item details</span>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('menu.edit', $menu->id) }}" class="btn atom-btn-white">
        <i class="fas fa-pencil-alt me-1"></i> Edit
      </a>
      @if(!$menu->isProtected)
        <a href="{{ route('menu.confirmDelete', $menu->id) }}" class="btn atom-btn-outline-danger">
          <i class="fas fa-trash me-1"></i> Delete
        </a>
      @endif
    </div>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($menu->isProtected)
    <div class="alert alert-warning">
      <i class="fas fa-shield-alt me-1"></i>
      This is a protected menu item. It cannot be deleted and its internal name cannot be changed.
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-body">
      <table class="table table-bordered mb-0">
        <tbody>
          <tr>
            <th style="width: 200px;">Name</th>
            <td>{{ $menu->name }}</td>
          </tr>
          <tr>
            <th>Label</th>
            <td>{{ $menu->label ?: 'N/A' }}</td>
          </tr>
          <tr>
            <th>Description</th>
            <td>{{ $menu->description ?: 'N/A' }}</td>
          </tr>
          <tr>
            <th>Path</th>
            <td>{{ $menu->path ?: 'N/A' }}</td>
          </tr>
          <tr>
            <th>Parent ID</th>
            <td>
              @if($menu->parentId)
                <a href="{{ route('menu.show', $menu->parentId) }}">{{ $menu->parentId }}</a>
              @else
                N/A (root)
              @endif
            </td>
          </tr>
          <tr>
            <th>Lft / Rgt</th>
            <td>{{ $menu->lft }} / {{ $menu->rgt }}</td>
          </tr>
          <tr>
            <th>Serial number</th>
            <td>{{ $menu->serialNumber ?? 'N/A' }}</td>
          </tr>
          <tr>
            <th>Source culture</th>
            <td>{{ $menu->sourceCulture }}</td>
          </tr>
          <tr>
            <th>Created</th>
            <td>{{ $menu->createdAt ? \Carbon\Carbon::parse($menu->createdAt)->format('Y-m-d H:i:s') : 'N/A' }}</td>
          </tr>
          <tr>
            <th>Updated</th>
            <td>{{ $menu->updatedAt ? \Carbon\Carbon::parse($menu->updatedAt)->format('Y-m-d H:i:s') : 'N/A' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  @if($children->count())
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Children ({{ $children->count() }})</h5>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Label</th>
              <th>Path</th>
              <th>Lft</th>
              <th>Rgt</th>
            </tr>
          </thead>
          <tbody>
            @foreach($children as $child)
              <tr>
                <td>
                  <a href="{{ route('menu.show', $child->id) }}">{{ $child->name }}</a>
                </td>
                <td>{{ $child->label ?: '' }}</td>
                <td>{{ $child->path ?: '' }}</td>
                <td>{{ $child->lft }}</td>
                <td>{{ $child->rgt }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div>
    <a href="{{ route('menu.browse') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back to menus
    </a>
  </div>
@endsection
