@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $term->name }}?</h1>
@endsection

@section('content')

  @if($taxonomyName)
    <p class="text-muted">Taxonomy: {{ $taxonomyName }}</p>
  @endif

  <form method="POST" action="{{ route('term.destroy', $term->slug) }}">
    @csrf
    @method('DELETE')

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('term.show', $term->slug) }}" class="btn btn-outline-secondary">Cancel</a></li>
      <li><input class="btn btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>

@endsection
