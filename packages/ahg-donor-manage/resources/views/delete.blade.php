@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $donor->authorized_form_of_name }}?</h1>
@endsection

@section('content')
  <form method="POST" action="{{ route('donor.destroy', $donor->slug) }}">
    @csrf
    @method('DELETE')
    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('donor.show', $donor->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>
@endsection
