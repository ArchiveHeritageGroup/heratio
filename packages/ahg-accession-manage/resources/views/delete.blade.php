@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $accession->title ?: $accession->identifier ?: '[Untitled]' }}?</h1>
@endsection

@section('content')

  <form method="POST" action="{{ route('accession.destroy', $accession->slug) }}">
    @csrf
    @method('DELETE')

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('accession.show', $accession->slug) }}" class="btn btn-outline-secondary">Cancel</a></li>
      <li><input class="btn btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>

@endsection
