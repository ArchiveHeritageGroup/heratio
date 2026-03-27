@extends('theme::layouts.1col')

@section('title', __('Are you sure you want to delete :name?', ['name' => $page->title ?? __('Untitled')]))
@section('body-class', 'delete staticpage')

@section('content')

  <h1>{{ __('Are you sure you want to delete :name?', ['name' => $page->title ?? __('Untitled')]) }}</h1>

  <form method="POST" action="{{ route('staticpage.destroy', $slug) }}">
    @csrf
    @method('DELETE')

    <ul class="actions mb-3 nav gap-2">
      <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('staticpage.show', $slug) }}">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="{{ __('Delete') }}"></li>
    </ul>

  </form>

@endsection
