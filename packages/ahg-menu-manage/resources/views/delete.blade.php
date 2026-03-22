@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete "{{ $menu->label ?: $menu->name ?: 'Menu #' . $menu->id }}"?</h1>
@endsection

@section('content')

  @if($menu->isProtected)
    <div class="alert alert-danger">
      <i class="fas fa-shield-alt me-1"></i>
      This is a protected menu item and cannot be deleted.
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('menu.show', $menu->id) }}" class="btn atom-btn-white">Back</a></li>
    </ul>
  @else
    @if($menu->hasChildren)
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <strong>Warning:</strong> This menu item has child items. Deleting it will also delete all of its children.
      </div>
    @endif

    <form method="POST" action="{{ route('menu.destroy', $menu->id) }}">
      @csrf
      @method('DELETE')

      <ul class="actions mb-3 nav gap-2">
        <li><a href="{{ route('menu.show', $menu->id) }}" class="btn atom-btn-white">Cancel</a></li>
        <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
      </ul>
    </form>
  @endif

@endsection
