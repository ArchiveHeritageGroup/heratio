@extends('theme::layouts.1col')

@section('title', 'Menus')
@section('body-class', 'browse menus')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-bars me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Menus</h1>
      <span class="small text-muted">{{ number_format($total) }} menu items</span>
    </div>
  </div>

  @if(count($tree))
    <div class="card mb-4">
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          @include('ahg-menu-manage::partials.tree-node', ['nodes' => $tree, 'depth' => 0])
        </ul>
      </div>
    </div>
  @else
    <div class="alert alert-info">No menu items found.</div>
  @endif
@endsection
