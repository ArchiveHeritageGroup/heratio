@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $user->authorized_form_of_name ?? $user->username }}?</h1>
@endsection

@section('content')
  <div class="alert alert-warning">
    This will permanently remove the user account <strong>{{ $user->username }}</strong> and all associated permissions.
  </div>

  <form method="POST" action="{{ route('user.destroy', $user->slug) }}">
    @csrf
    @method('DELETE')
    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('user.show', $user->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>
@endsection
