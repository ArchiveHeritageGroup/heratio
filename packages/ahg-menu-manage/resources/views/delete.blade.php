@extends('theme::layouts.1col')

@section('title')
  <h1>{{ __('Are you sure you want to delete %1% and all its descendants?', ['%1%' => $menu->label ?: $menu->name ?: 'Menu #' . $menu->id]) }}</h1>
@endsection

@section('content')

  <form method="POST" action="{{ route('menu.destroy', $menu->id) }}">
    @csrf
    @method('DELETE')

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('menu.edit', $menu->id) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="{{ __('Delete') }}"></li>
    </ul>

  </form>

@endsection
