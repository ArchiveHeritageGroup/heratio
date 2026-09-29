@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $actor->authorized_form_of_name }}?</h1>
@endsection

@section('content')

  <form method="POST" action="{{ route('actor.destroy', $actor->slug) }}">
    @csrf
    @method('DELETE')

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a href="{{ route('actor.show', $actor->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
        <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
      </ul>
    </section>
  </form>

@endsection
