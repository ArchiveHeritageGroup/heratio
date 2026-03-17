@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $io->title ?? '[Untitled]' }}?</h1>
@endsection

@section('content')

  <form method="POST" action="{{ route('informationobject.destroy', $io->slug) }}">
    @csrf
    @method('DELETE')

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
      </ul>
    </section>
  </form>

@endsection
